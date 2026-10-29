<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\View\Requirements;
use Toast\ColourPalettes\Fields\ColourPaletteField;

/**
 * Makes toastnz/colourpalettes swatches work inside elemental's in-line editor.
 *
 * ColourPaletteField draws its swatches in Field() and a .ss template, both of
 * which only run when a form is rendered server-side. Elemental's in-line form
 * is built by the React form builder from a schema, so none of that runs: the
 * field falls back to the OptionsetField component it inherits and an author
 * gets a list of radio buttons labelled with raw hex values.
 *
 * There is no updateSchema* hook on FormField, so the fields are reached where
 * they exist and told which component to use and what each option actually
 * looks like.
 *
 * Hooked onto the form factory rather than onto the element's own
 * updateCMSFields, because a project adds its colour fields from an extension
 * of its own and extensions run in the order they are configured - so walking
 * the list during updateCMSFields finds whatever happens to have been added by
 * then, and on this site that was nothing. updateFormFields runs once the
 * record's fields are completely built. It is also exactly the right scope:
 * this factory builds the in-line form, which is the only place the problem
 * exists.
 *
 * Optional in both directions: it does nothing unless colourpalettes is
 * installed, and colourpalettes needs no changes for it to work.
 *
 * Applied to two things: the form factory, where the fields are caught and
 * retargeted, and LeftAndMain, which is the only place early enough to get the
 * palette's stylesheet onto the CMS page.
 */
class ColourPaletteFieldExtension extends Extension
{
    /**
     * Load the palette's own stylesheet with the CMS.
     *
     * ColourPaletteField requires it from Field(), which never runs for a form
     * built as a schema - and by then the CMS page has loaded, so requiring it
     * while building the schema would be too late to reach the browser. The
     * React component emits that stylesheet's markup, so this is what makes it
     * look like the field it is replacing.
     */
    protected function onInit(): void
    {
        if (class_exists(ColourPaletteField::class)) {
            Requirements::css('toastnz/colourpalettes: client/dist/styles/main.css');
        }
    }

    protected function updateFormFields(
        FieldList $fields,
        ?RequestHandler $controller,
        string $name,
        array $context
    ): void {
        if (!class_exists(ColourPaletteField::class)) {
            return;
        }

        foreach ($fields->getDataFields() as $field) {
            if (!$field instanceof ColourPaletteField) {
                continue;
            }

            $field->setSchemaComponent('ColourPaletteField');

            // `source` is one of the keys getSchemaStateDefaults() already
            // defines, which is why replacing it here survives - anything not
            // already in the defaults would be dropped.
            $field->setSchemaState(['source' => $this->describeColours($field)]);

            // What each swatch does to a block, for the live preview. Set on
            // the field by ColourPaletteFieldPropertiesExtension, which is
            // only there when the preview is - hence the check.
            if ($colours = $field->getAttribute('data-css-colours')) {
                $field->setSchemaData(['data' => ['colours' => json_decode($colours, true)]]);
            }
        }
    }

    /**
     * The field's source is [colour id => hex], because the hex is what the
     * server-side template paints the swatch with. The React component needs
     * the name as well, and the brightness so a tick drawn on the swatch can be
     * light or dark.
     */
    private function describeColours(ColourPaletteField $field): array
    {
        $described = [];

        foreach ($field->getSource() as $value => $hex) {
            $colour = $field->getColour($value);

            $described[] = [
                'value' => (string) $value,
                // Falls back to the hex, which is what an author saw before and
                // is better than an empty swatch if a colour has no name.
                'title' => $colour && $colour->Title ? $colour->Title : (string) $hex,
                'hex' => $colour ? '#' . ltrim((string) $colour->HexValue, '#') : null,
                'brightness' => $colour && $colour->hasMethod('getBrightness') ? $colour->getBrightness() : null,
                // A theme colour is labelled by the variable it sets rather
                // than its name, which is what the .ss template does.
                'cssName' => $colour?->CSSName,
                'isThemeColour' => (bool) ($colour?->IsThemeColour),
                'disabled' => false,
            ];
        }

        return $described;
    }
}
