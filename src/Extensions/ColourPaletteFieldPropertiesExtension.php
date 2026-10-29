<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\Core\Extension;
use Toast\ColourPalettes\Fields\ColourPaletteField;
use Toast\ColourPalettes\Models\Colour;

/**
 * Tells the CMS what each swatch in a colour palette does to a block.
 *
 * For every swatch, the custom properties the block's colour is written as -
 * the same ones {@link ElementColourExtension} puts on the block, taken from
 * the same place - so the live preview can apply a swatch to the page without
 * knowing how colours are named or how their contrast is worked out. "None"
 * is every one of those properties cleared.
 *
 * Only for a field that is a has_one, which is what the ID on the end of its
 * name says. It goes on the field as `data-css-colours`, keyed by colour ID.
 *
 * Applied to the field itself, at the point it has its swatches, rather than
 * from the element's form: a project adds its colour fields from an extension
 * of its own, and extensions run in the order they are configured.
 *
 * Only registered when toastnz/colourpalettes is installed - see
 * _config/colourpalettes.yml.
 *
 * @extends Extension<ColourPaletteField>
 */
class ColourPaletteFieldPropertiesExtension extends Extension
{
    protected function updateColourPaletteSource(array &$options): void
    {
        $relation = $this->owner->getName();

        if (!str_ends_with($relation, 'ID')) {
            return;
        }

        $ids = array_filter(array_map('intval', array_keys($options)));
        $colours = [];

        foreach ($ids ? Colour::get()->byIDs($ids) : [] as $colour) {
            $colours[$colour->ID] = $this->getProperties($colour, $relation);
        }

        // The singleton is only there for the names of the properties
        $colours[0] = array_fill_keys(array_keys($this->getProperties(Colour::singleton(), $relation)), null);

        $this->owner->setAttribute('data-css-colours', json_encode($colours));
    }

    /**
     * @return array<string, string> custom property => value
     */
    private function getProperties(Colour $colour, string $relation): array
    {
        $properties = [];

        foreach ($colour->getCSSVarsArray($relation) as $declaration) {
            [$name, $value] = array_pad(explode(':', rtrim(trim($declaration), ';'), 2), 2, '');
            $properties[trim($name)] = trim($value);
        }

        return $properties;
    }
}
