/*------------------------------------------------------------------
Live colour preview: the CMS half

Picking a swatch in a block's colour field tells the preview frame what that
colour does to the block, so the author sees it as they pick rather than after
saving. previewColours.js is the half that runs in the frame.

The server decides what a swatch means: each colour field carries, per swatch,
the custom properties to put on the block (ColourPaletteFieldPropertiesExtension).
All this does is pass them on - it knows nothing about how colours are named or
how their contrast is worked out.

One listener on the document rather than anything bound to the field: the form
is replaced wholesale every time an author moves to another block.
------------------------------------------------------------------*/

const SOURCE = 'elemental-flavours';

/**
 * The anchor of the block a form is editing. Elemental puts the block's preview
 * address in every block form as a hidden StageLink, and that address ends in
 * the anchor the page renders the block with.
 */
const getAnchor = (form) => {
  try {
    return new URL(form.querySelector('input[name$="StageLink"]').value).hash.slice(1);
  } catch {
    return '';
  }
};

document.addEventListener('change', ({ target }) => {
  const palette = target.closest('ul.colourpalette[data-css-colours]');

  if (!palette || !target.form) {
    return;
  }

  // Looked up each time: the frame is replaced whenever the author changes
  // view mode, and one kept from last time is a window that no longer exists.
  const frame = document.querySelector('iframe[src*="CMSPreview"]');
  const anchor = getAnchor(target.form);
  const properties = JSON.parse(palette.dataset.cssColours)[target.value];

  if (!frame || !frame.contentWindow || !anchor || !properties) {
    return;
  }

  // To the frame's own origin rather than ours: a preview of a page on another
  // domain is still a preview.
  frame.contentWindow.postMessage({ source: SOURCE, anchor, properties }, new URL(frame.src).origin);
});
