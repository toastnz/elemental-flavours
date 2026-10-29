const ROOT = 'flavours-block-jump';
const OPEN = 'show';

/**
 * Opens and closes the block menu in a form's action bar.
 *
 * The CMS ships Bootstrap's stylesheet but not its javascript, so
 * `.dropdown-menu` is already drawn correctly and only needs the class putting
 * on it. That is all this does.
 *
 * Listening on the document rather than binding to the button: the form is
 * replaced wholesale every time an author moves to another block, and anything
 * bound to the old markup goes with it.
 */
const closeAll = (except = null) => {
  document.querySelectorAll(`.${ROOT}`).forEach((menu) => {
    if (menu === except) {
      return;
    }

    menu.querySelector(`.${ROOT}__menu`)?.classList.remove(OPEN);
    menu.querySelector(`.${ROOT}__toggle`)?.setAttribute('aria-expanded', 'false');
  });
};

const init = () => {
  document.addEventListener('click', (event) => {
    const toggle = event.target.closest(`.${ROOT}__toggle`);

    if (!toggle) {
      // A click anywhere else closes an open menu, including a click on one of
      // its own links - the CMS loads the next block over the top of this form
      // and the menu would otherwise be left open on the way out.
      closeAll();
      return;
    }

    event.preventDefault();

    const root = toggle.closest(`.${ROOT}`);
    const menu = root.querySelector(`.${ROOT}__menu`);
    const isOpen = menu.classList.contains(OPEN);

    closeAll(root);
    menu.classList.toggle(OPEN, !isOpen);
    toggle.setAttribute('aria-expanded', String(!isOpen));

    if (!isOpen) {
      // A page can have more blocks than the menu is tall, and the one an
      // author is on is the landmark they read the rest against.
      menu.querySelector(`.${ROOT}__item--current`)?.scrollIntoView({ block: 'center' });
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAll();
    }
  });
};

export default init;
