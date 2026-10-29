<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Extension;
use Toast\ColourPalettes\Models\Colour;

/**
 * Writes a block's colours as custom properties on its holder.
 *
 * Every has_one an element has to a colour becomes the properties that colour
 * defines for that relation - `--_primary-colour` and its contrast pair for a
 * PrimaryColour - in the holder's own `style`, where a stylesheet reads them.
 *
 * On the element rather than in the page head so that the live preview in the
 * CMS has exactly one thing to change, and so that taking a colour off a block
 * leaves it with whatever the theme gave the element by default. A head rule
 * keyed on the block's anchor could be overridden but never taken away.
 *
 * Only registered when toastnz/colourpalettes is installed - see
 * _config/colourpalettes.yml.
 *
 * @extends Extension<BaseElement>
 */
class ElementColourExtension extends Extension
{
    protected function updateHolderAttributes(array &$attributes): void
    {
        $declarations = [];

        foreach ($this->owner->hasOne() as $relation => $class) {
            if (!is_string($class) || !is_a($class, Colour::class, true)) {
                continue;
            }

            $colour = $this->owner->$relation();

            if ($colour->exists()) {
                array_push($declarations, ...$colour->getCSSVarsArray($relation));
            }
        }

        if (!$declarations) {
            return;
        }

        // Before any style the block has of its own, which is meant to win
        $attributes['style'] = trim(implode(' ', $declarations) . ' ' . ($attributes['style'] ?? ''));
    }
}
