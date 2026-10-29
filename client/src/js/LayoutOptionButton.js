import React from 'react';
import PropTypes from 'prop-types';
import classnames from 'classnames';
import { Button } from 'reactstrap';

/**
 * One layout inside the picker popover.
 *
 * Built to the same shape as elemental's ElementOptionButton - a btn__icon
 * followed by a btn__title - so admin's .popover-option-set__button styles it
 * exactly as it styles the add-block popover, and the two read as one CMS
 * rather than two that nearly match.
 *
 * The only addition is the inline SVG branch. A layout is usually a diagram of
 * a page rather than a glyph, so it may arrive as markup instead of an icon
 * font class; it is sized to sit on the same line as a glyph would.
 */
const LayoutOptionButton = ({ icon, svg, className, children, ...props }) => (
  <Button
    className={classnames('btn--icon-xl', 'flavours-layout-option', className)}
    type="button"
    {...props}
  >
    {svg && (
      <span
        className="btn__icon flavours-layout-option__svg"
        aria-hidden="true"
        dangerouslySetInnerHTML={{ __html: svg }}
      />
    )}

    {!svg && icon && <span className={classnames('btn__icon', 'layout-icons', icon)} aria-hidden="true" />}

    <span className="btn__title">{children}</span>
  </Button>
);

LayoutOptionButton.propTypes = {
  icon: PropTypes.string,
  svg: PropTypes.string,
  className: PropTypes.string,
};

export default LayoutOptionButton;
