import React, { useState } from 'react';
import PropTypes from 'prop-types';
import classnames from 'classnames';
import i18n from 'i18n';
import { inject } from 'lib/Injector';
import fieldHolder from 'components/FieldHolder/FieldHolder';
import LayoutOptionButton from './LayoutOptionButton';

/**
 * Picks the layout an element renders with.
 *
 * Deliberately the same interaction as elemental's "Add block" button - a
 * trigger showing the current choice, opening a searchable popover of the
 * alternatives - so there is one way to pick a thing from a picture in this
 * CMS rather than two that look different.
 */
const LayoutPickerField = ({
  PopoverOptionSetComponent,
  id,
  name,
  value,
  source,
  data,
  readOnly,
  disabled,
  onChange,
  extraClass,
}) => {
  const [isOpen, setIsOpen] = useState(false);

  const options = source || [];
  const hasIcons = Boolean(data && data.hasIcons);
  const isDisabled = Boolean(readOnly || disabled);
  const toggleId = `${id}_Toggle`;

  // Falling back to the first option matters on an element whose saved layout
  // has been removed: the server has already decided what it will render with,
  // and the trigger should say the same thing.
  const selected = options.find((option) => `${option.value}` === `${value}`) || options[0];

  const toggle = () => setIsOpen((open) => !open);

  const handleSelect = (option) => (event) => {
    event.preventDefault();
    setIsOpen(false);

    if (typeof onChange === 'function' && `${option.value}` !== `${value}`) {
      onChange(event, { id, value: option.value });
    }
  };

  const buttons = options.map((option) => ({
    key: option.value,
    content: option.title,
    icon: option.iconClass,
    onClick: handleSelect(option),
    className: classnames({
      'flavours-layout-option--active': selected && `${option.value}` === `${selected.value}`,
    }),
    buttonProps: {
      svg: option.icon,
      disabled: option.disabled,
    },
  }));

  return (
    <div className={classnames('flavours-layout-picker', extraClass)}>
      {/* Kept so a plain form post carries the value too. The React form
          submits from redux state and does not need it. */}
      <input type="hidden" name={name} value={value === null ? '' : value} readOnly />

      <button
        type="button"
        id={toggleId}
        className="btn btn-secondary flavours-layout-picker__toggle"
        onClick={toggle}
        disabled={isDisabled}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
      >
        {hasIcons && selected && (
          <span className="flavours-layout-picker__thumbnail" aria-hidden="true">
            {selected.icon && <span dangerouslySetInnerHTML={{ __html: selected.icon }} />}
            {!selected.icon && selected.iconClass && (
              <span className={classnames('layout-icons', selected.iconClass)} />
            )}
          </span>
        )}

        <span className="flavours-layout-picker__title">
          {selected ? selected.title : i18n._t('Flavours.CHOOSE_LAYOUT', 'Choose a layout')}
        </span>

        <span className="flavours-layout-picker__caret font-icon-down-open" aria-hidden="true" />
      </button>

      {!isDisabled && (
        <PopoverOptionSetComponent
          buttons={buttons}
          // `className` and not `extraClass`: PopoverOptionSet only reads
          // className, and its default value is the class its own styles hang
          // off, so it has to be repeated here rather than replaced.
          className="popover-option-set flavours-layout-picker__popover"
          // Search earns its place once there are more layouts than fit on
          // screen; below that it is a box to tab past.
          disableSearch={options.length < 8}
          searchPlaceholder={i18n._t('Flavours.SEARCH_LAYOUTS', 'Search layouts')}
          isOpen={isOpen}
          placement="bottom-start"
          target={toggleId}
          toggle={toggle}
          ButtonComponent={LayoutOptionButton}
        />
      )}
    </div>
  );
};

LayoutPickerField.propTypes = {
  id: PropTypes.string.isRequired,
  name: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  source: PropTypes.arrayOf(PropTypes.shape({
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    title: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    icon: PropTypes.string,
    iconClass: PropTypes.string,
    disabled: PropTypes.bool,
  })),
  data: PropTypes.shape({
    hasIcons: PropTypes.bool,
  }),
  onChange: PropTypes.func,
  readOnly: PropTypes.bool,
  disabled: PropTypes.bool,
  extraClass: PropTypes.string,
};

export { LayoutPickerField as Component };

export default inject(
  ['PopoverOptionSet'],
  (PopoverOptionSetComponent) => ({ PopoverOptionSetComponent }),
  () => 'LayoutPickerField'
)(fieldHolder(LayoutPickerField));
