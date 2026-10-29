/*------------------------------------------------------------------
Live colour preview: the page half

Runs inside the CMS preview frame. When the CMS says a block's colours have
changed, puts the custom properties it names on the block - on the block's own
style, which is where the server writes them (ElementColourExtension), so a
colour taken off a block falls back to the theme's default for it.

The CMS half is cmsColours.js.
------------------------------------------------------------------*/

const SOURCE = 'elemental-flavours';

// Only in a preview. Opened in a tab of its own, the page is left exactly as a
// visitor sees it.
if (window.self !== window.top) {
  window.addEventListener('message', ({ source, data }) => {
    // Only the CMS window that framed this page. A page in a frame can be
    // framed by anybody, and what is asked for is only ever custom properties
    // on a block, but there is no reason to take it from anyone else.
    if (source !== window.parent || !data || data.source !== SOURCE) {
      return;
    }

    const block = document.getElementById(data.anchor);

    if (!block) {
      return;
    }

    Object.entries(data.properties || {}).forEach(([name, value]) => {
      if (!name.startsWith('--')) {
        return;
      }

      if (value === null) {
        block.style.removeProperty(name);
      } else {
        block.style.setProperty(name, value);
      }
    });
  });
}
