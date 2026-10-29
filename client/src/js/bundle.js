import Injector from 'lib/Injector';
import ColourPaletteField from './ColourPaletteField';
import LayoutPickerField from './LayoutPickerField';
import blockJumpMenu from './blockJumpMenu';

const register = () => {
  Injector.component.registerMany({
    ColourPaletteField,
    LayoutPickerField,
  });

  blockJumpMenu();
};

// Registration has to happen before silverstripe/admin calls Injector.load(),
// which it does from window.onload - so DOMContentLoaded is the right moment.
// It has to cope with already being past that moment as well: the CMS loads
// this bundle in a document that may already be parsed, and a listener added
// then never fires, leaving the field with no component and an empty space
// where the picker should be.
if (window.document.readyState === 'loading') {
  window.document.addEventListener('DOMContentLoaded', register, { once: true });
} else {
  register();
}
