/*------------------------------------------------------------------
<elemental-flavour> custom element

The root element of every elemental block (see the module's FlavourHolder.ss),
and usable by hand for any other band of the page that should behave like one.
Ported from toastnz/blocks-layouts' <colour-block>, which works the same way.

Adds classes to each block based on its neighbours' background colours,
so themes can remove doubled-up spacing between same-coloured blocks:

- `collapsed-top`     the previous <elemental-flavour> has the same background
- `collapsed-bottom`  the next <elemental-flavour> has the same background
- `transparent`       the block has no background colour of its own

A transparent block is treated as having the page (body) background.
All blocks are re-checked together, once per animation frame, whenever
something that could change a background happens.

Public API, for themes:
- block.resize()                        re-check this block and its neighbours
- block.getCurrentBackgroundColour()    the block's computed background colour
- block.hasSameColourAsOtherBlock(el)   compare with another <elemental-flavour>
- block.hasBackgroundColourEqualTo(c)   compare with any CSS colour
- customElements.get('elemental-flavour').refreshAll()
------------------------------------------------------------------*/

const TAG_NAME = 'elemental-flavour';
const MANAGED_CLASSES = ['collapsed-top', 'collapsed-bottom', 'transparent'];

/** Connected blocks, in no particular order */
const blocks = new Set();

/** Normalised page background, refreshed with every check */
let pageColour = null;

/** Pending animation frame for the next check */
let refreshFrame = 0;

/*------------------------------------------------------------------
Colour helpers
------------------------------------------------------------------*/

/**
 * The alpha channel of a computed CSS colour, e.g. 0.5 for
 * "rgba(0, 0, 0, 0.5)", "rgb(0 0 0 / 50%)" or "oklch(0.5 0.1 20 / 0.5)".
 */
function getAlpha(colour) {
  if (colour === 'transparent') return 0;

  // Modern syntax: "... / alpha)"
  const modern = /\/\s*([\d.]+)(%?)\s*\)$/.exec(colour);
  if (modern) return parseFloat(modern[1]) / (modern[2] ? 100 : 1);

  // Legacy syntax: "rgba(r, g, b, alpha)"
  const legacy = /^(?:rgba|hsla)\(([^)]*)\)$/.exec(colour);
  if (legacy) {
    const parts = legacy[1].split(',');
    if (parts.length === 4) return parseFloat(parts[3]);
  }

  return 1;
}

/**
 * A comparable form of a computed colour. Browsers serialise computed
 * colours consistently, so equal colours give equal strings. Fully
 * transparent colours fall back to the page colour.
 */
function normaliseComputedColour(colour) {
  const value = (colour || '').trim().toLowerCase();

  if (!value || getAlpha(value) === 0) return pageColour;

  return value;
}

/** Resolves arbitrary CSS colours (hex, names, hsl()...) to their computed form */
const colourProbe = {
  element: null,
  cache: new Map(),

  resolve(colour) {
    if (!colour) return null;
    if (this.cache.has(colour)) return this.cache.get(colour);

    if (!this.element) {
      this.element = document.createElement('span');
      this.element.hidden = true;
      document.documentElement.appendChild(this.element);
    }

    this.element.style.color = '';
    this.element.style.color = colour;

    // An invalid colour leaves the style empty
    const resolved = this.element.style.color ? getComputedStyle(this.element).color : null;
    this.cache.set(colour, resolved);

    return resolved;
  },
};

/*------------------------------------------------------------------
Refreshing
------------------------------------------------------------------*/

/** Check every block in one batch: read all colours first, then write classes */
function refreshAll() {
  refreshFrame = 0;

  if (!document.body) return;

  pageColour = normaliseComputedColour(getComputedStyle(document.body).backgroundColor) || null;

  const colours = new Map();
  blocks.forEach((block) => colours.set(block, block.getNormalisedColour()));
  blocks.forEach((block) => block.applyColourClasses(colours));
}

/** Queue a check for the next animation frame (multiple requests are merged) */
function scheduleRefresh() {
  if (!refreshFrame) refreshFrame = window.requestAnimationFrame(refreshAll);
}

/** Whether a class change involved anything other than the classes this script manages */
function isExternalClassChange(mutation) {
  const strip = (value) => (value || '')
    .split(/\s+/)
    .filter((name) => name && !MANAGED_CLASSES.includes(name))
    .sort()
    .join(' ');

  return strip(mutation.oldValue) !== strip(mutation.target.getAttribute('class'));
}

/** Watches each block for style/class changes, and each block container for added/removed siblings */
const observer = new MutationObserver((mutations) => {
  const relevant = mutations.some((mutation) => (
    mutation.type === 'childList'
    || mutation.attributeName !== 'class'
    || isExternalClassChange(mutation)
  ));

  if (relevant) scheduleRefresh();
});

/** Containers already being watched for sibling changes */
const observedParents = new WeakSet();

function observeParent(parent) {
  if (!parent || observedParents.has(parent)) return;

  observedParents.add(parent);
  observer.observe(parent, { childList: true });
}

/*------------------------------------------------------------------
Custom element
------------------------------------------------------------------*/

class ElementalFlavour extends HTMLElement {
  /** Re-check every block on the page */
  static refreshAll() {
    scheduleRefresh();
  }

  connectedCallback() {
    blocks.add(this);

    // Style/class changes on the block can change its background
    observer.observe(this, {
      attributes: true,
      attributeFilter: ['style', 'class'],
      attributeOldValue: true,
    });

    // Adding or removing elements next to the block changes its neighbours
    observeParent(this.parentElement);

    this.getCurrentBackgroundColour();
    scheduleRefresh();
  }

  disconnectedCallback() {
    blocks.delete(this);

    // Former neighbours need re-checking
    scheduleRefresh();
  }

  /** The block's computed background colour (also stored as `backgroundColour`) */
  getCurrentBackgroundColour() {
    this.backgroundColour = window.getComputedStyle(this).backgroundColor;
    return this.backgroundColour;
  }

  /** The block's background in comparable form; transparent blocks use the page colour */
  getNormalisedColour() {
    return normaliseComputedColour(this.getCurrentBackgroundColour());
  }

  /** Whether another <elemental-flavour> has the same (effective) background colour */
  hasSameColourAsOtherBlock(block) {
    if (!blocks.has(block)) return false;

    return this.getNormalisedColour() === block.getNormalisedColour();
  }

  /**
   * Whether this block's background matches the given CSS colour. With no
   * colour, compares against the page background.
   */
  hasBackgroundColourEqualTo(value) {
    const target = value ? normaliseComputedColour(colourProbe.resolve(value)) : pageColour;

    return this.getNormalisedColour() === target;
  }

  /** Toggle the collapsed/transparent classes using pre-read colours */
  applyColourClasses(colours) {
    const colour = colours.get(this);
    const sameAs = (sibling) => blocks.has(sibling) && colours.get(sibling) === colour;

    this.classList.toggle('collapsed-top', sameAs(this.previousElementSibling));
    this.classList.toggle('collapsed-bottom', sameAs(this.nextElementSibling));
    this.classList.toggle('transparent', getAlpha((this.backgroundColour || '').trim().toLowerCase()) === 0);
  }

  /** Re-check this block and its neighbours */
  resize() {
    scheduleRefresh();
  }
}

/*------------------------------------------------------------------
Setup
------------------------------------------------------------------*/

// Another script (e.g. a theme) may already provide <elemental-flavour>
if (!window.customElements.get(TAG_NAME)) {
  window.customElements.define(TAG_NAME, ElementalFlavour);

  // Page/theme background changes (e.g. dark mode toggles)
  [document.documentElement, document.body].forEach((element) => {
    if (element) observer.observe(element, { attributes: true, attributeFilter: ['style', 'class', 'data-theme'] });
  });

  // Late stylesheets (deferred CSS, combined files) can change backgrounds
  document.addEventListener('load', (event) => {
    if (event.target instanceof HTMLLinkElement) scheduleRefresh();
  }, true);

  // Media queries can change backgrounds at different sizes and colour schemes
  let resizeTimeout = 0;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(scheduleRefresh, 150);
  });
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', scheduleRefresh);

  window.addEventListener('load', scheduleRefresh);
  window.addEventListener('pageshow', scheduleRefresh);
}
