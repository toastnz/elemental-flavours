<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * What goes on the <elemental-flavour> a block is rendered in.
 *
 * The holder (templates/DNADesign/Elemental/Layout/FlavourHolder.ss) is the
 * block's root element, so it is where a project's stylesheet and scripts hang
 * the block's classes and attributes. This gives it elemental's own classes and
 * two hooks for a project to add to them, so a project never needs to override
 * the holder template to put a class on a block.
 *
 * @extends Extension<BaseElement>
 */
class ElementHolderExtension extends Extension
{
    /**
     * Elemental's own holder classes, then whatever an extension adds through
     * `updateHolderClasses(array &$classes)`.
     */
    public function getHolderClasses(): string
    {
        $classes = [
            'element',
            strtolower((string) $this->owner->getSimpleClassName()),
            $this->owner->getStyleVariant(),
            $this->owner->ExtraClass,
        ];

        $this->owner->extend('updateHolderClasses', $classes);

        // Split first, so a class added as part of "base modifier" is still
        // only written once.
        $classes = preg_split('/\s+/', implode(' ', array_map('strval', $classes)), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_unique($classes));
    }

    /**
     * Extra attributes for the holder, for a block whose script or stylesheet
     * reads one off its root element.
     *
     * A block opts in by defining provideHolderAttributes(), and an extension
     * through `updateHolderAttributes(array &$attributes)`. Named provide*,
     * the way elemental names provideBlockSchema(), and deliberately unlike
     * the template variable: PHP matches method names case-insensitively and
     * the view layer tries `$Name()` before `getName()`, so a hook called
     * holderAttributes() would be what `$HolderAttributes` called, and its raw
     * array would be handed to the view layer to render.
     *
     * A value of `true` is written as a bare attribute (`data-scroll-progress`).
     */
    public function getHolderAttributes(): DBHTMLText
    {
        $attributes = $this->owner->hasMethod('provideHolderAttributes')
            ? (array) $this->owner->provideHolderAttributes()
            : [];

        $this->owner->extend('updateHolderAttributes', $attributes);

        $rendered = '';

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            $rendered .= $value === true
                ? ' ' . Convert::raw2att($name)
                : ' ' . Convert::raw2att($name) . '="' . Convert::raw2att($value) . '"';
        }

        // Always a field, never null: the template prints this straight into
        // the tag, and a null there is handed to the view layer as something
        // to render rather than as nothing to print.
        return DBField::create_field('HTMLFragment', $rendered);
    }
}
