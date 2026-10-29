import React from 'react';
import PropTypes from 'prop-types';
import classnames from 'classnames';
import fieldHolder from 'components/FieldHolder/FieldHolder';

/**
 * The swatch picker from toastnz/colourpalettes, for elemental's in-line form.
 *
 * This deliberately emits the same markup as that module's own .ss template
 * rather than a tidier structure of its own, so its stylesheet applies
 * unchanged: the 50px swatches, the tick outlined in white so it reads on any
 * colour, the "unset" swatch divided off from the rest, the rule between theme
 * colours and the others, and the hover label that flips above or below
 * depending on which half of the swatch the pointer is over.
 *
 * The two empty divs inside each label are what drive that flip. They look like
 * nothing and they are load-bearing.
 */
const ColourPaletteField = ({
  id,
  name,
  value,
  source,
  readOnly,
  disabled,
  onChange,
  extraClass,
}) => {
  const options = source || [];
  const isDisabled = Boolean(readOnly || disabled);

  const handleChange = (option) => (event) => {
    if (typeof onChange === 'function') {
      onChange(event, { id, value: option.value });
    }
  };

  return (
    <ul
      className={classnames('optionset', 'colourpalette', extraClass, { readonly: isDisabled })}
      role="listbox"
    >
      {options.map((option, index) => {
        const optionId = `${id}-${option.value}`;
        const isNone = !option.hex;
        // Their stylesheet keys the "unset" swatch off the option's value, and
        // stripes the list odd and even.
        const liClass = classnames(
          index % 2 ? 'even' : 'odd',
          `val${option.value}`,
          { 'theme-colour': option.isThemeColour }
        );

        return (
          <li key={option.value} className={liClass}>
            <input
              id={optionId}
              className="radio"
              type="radio"
              role="option"
              name={name}
              value={option.value}
              checked={`${option.value}` === `${value}`}
              disabled={isDisabled || option.disabled}
              onChange={handleChange(option)}
              data-brightness={option.brightness || undefined}
            />

            <label
              htmlFor={optionId}
              title={isNone ? 'None' : option.title}
              style={isNone ? undefined : { backgroundColor: option.hex }}
            >
              {!isNone && (
                <>
                  <div />
                  <div />
                  <span>
                    {option.isThemeColour && option.cssName ? option.cssName : option.title}
                    <br />
                    <small>{option.hex}</small>
                  </span>
                </>
              )}
            </label>
          </li>
        );
      })}
    </ul>
  );
};

ColourPaletteField.propTypes = {
  id: PropTypes.string.isRequired,
  name: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  source: PropTypes.arrayOf(PropTypes.shape({
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    title: PropTypes.string,
    hex: PropTypes.string,
    cssName: PropTypes.string,
    brightness: PropTypes.string,
    isThemeColour: PropTypes.bool,
  })),
  onChange: PropTypes.func,
  readOnly: PropTypes.bool,
  disabled: PropTypes.bool,
  extraClass: PropTypes.string,
};

export { ColourPaletteField as Component };

export default fieldHolder(ColourPaletteField);
