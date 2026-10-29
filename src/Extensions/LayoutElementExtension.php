<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use Toast\Flavours\Forms\LayoutPickerField;
use Toast\Flavours\Layouts\LayoutRegistry;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;

/**
 * Gives every element a choice of layout.
 *
 * This rides on elemental's own Style field rather than adding another column:
 * BaseElement::getRenderTemplates() already turns Style into a template suffix
 * and falls back to the unsuffixed template, so the whole rendering half of the
 * feature is upstream behaviour. What is added here is everything around the
 * edges - finding the layouts, picking one with a picture, and coping when a
 * layout an author already chose is taken away.
 *
 * @extends Extension<BaseElement>
 */
class LayoutElementExtension extends Extension
{
    /**
     * Per-layout settings, keyed by layout name (the template suffix).
     *
     * Toast\Elements\ImageElement:
     *   layouts:
     *     Default:
     *       label: 'Default'
     *       icon_class: 'layout-icons-image-large-left'
     *     Hero:
     *       icon: 'app/client/images/layout-icons/hero.svg'
     *     Wide:
     *       disabled: true
     *
     * Supported keys are label, icon (a path, resolved the same way as any
     * module resource), icon_class, and disabled.
     */
    private static array $layouts = [];

    /**
     * Layout names to hide from the picker, for when the only thing being said
     * is "not this one".
     */
    private static array $exclude_layouts = [];

    protected function updateCMSFields(FieldList $fields): void
    {
        $layouts = $this->getAvailableLayouts();

        // Elemental scaffolds its own Style dropdown from the `styles` config.
        // Ours replaces it, and removing it unconditionally also covers the
        // case where this element has no layouts at all.
        $fields->removeByName('Style');

        if ($notice = $this->getMissingLayoutNotice()) {
            $this->insertAtTopOfMain($fields, $notice);
        }

        // One layout is not a choice, and an empty picker is just a puzzle.
        if (count($layouts) < 2) {
            return;
        }

        $this->insertAtTopOfMain($fields, LayoutPickerField::createForLayouts(
            'Style',
            _t(__CLASS__ . '.LAYOUT', 'Layout'),
            $layouts,
            $this->owner->Style
        ));
    }

    /**
     * Keep the stored layout honest.
     *
     * An element whose layout has been removed would otherwise keep the old
     * name in the database and hand it to getStyleVariant(), putting a class on
     * the holder for a layout that no longer renders.
     */
    protected function onBeforeWrite(): void
    {
        if ($this->owner->Style && $this->hasLayout($this->owner->Style)) {
            return;
        }

        $default = LayoutRegistry::singleton()->getDefaultLayout(get_class($this->owner));

        // Null means this element type has no layouts, in which case Style is
        // none of our business - a project may still be using it for upstream
        // style variants that carry no template.
        if ($default !== null) {
            $this->owner->Style = $default;
        }
    }

    /**
     * Add the default layout to the end of the template chain.
     *
     * Elemental falls back from `<Class>_<Style>` to `<Class>`, which is the
     * right answer when a layout is an optional variation of a base template.
     * Here every layout carries a suffix, so a block whose layout has been
     * deleted has nothing left to fall back to and renders as nothing at all -
     * on the live site, before anyone re-saves it.
     *
     * Appended rather than inserted, so a project that does keep an unsuffixed
     * template still has that used first.
     */
    protected function updateRenderTemplates(array &$templates, string $suffix): void
    {
        $default = LayoutRegistry::singleton()->getDefaultLayout(get_class($this->owner));

        if ($default === null || $this->owner->Style === $default) {
            return;
        }

        foreach ($templates as $class => $variations) {
            $templates[$class][] = $class . $suffix . '_' . $default;
        }
    }

    /**
     * The class elemental puts on the element holder.
     *
     * Elemental has already reduced this to an empty string unless the value
     * appears in the `styles` config, so it is rebuilt here from the layout
     * list. Kebab-cased to match how the rest of this project names classes,
     * so a Hero layout is `.hero` and TextLeft is `.text-left`.
     */
    protected function updateStyleVariant(string &$style): void
    {
        $layout = (string) $this->owner->Style;

        $style = $this->hasLayout($layout)
            ? strtolower(preg_replace('/(?<!^)([A-Z][a-z])/', '-$1', $layout))
            : '';
    }

    /**
     * @return array<string, \Toast\Flavours\Layouts\Layout>
     */
    public function getAvailableLayouts(): array
    {
        return LayoutRegistry::singleton()->getLayouts(get_class($this->owner));
    }

    public function hasLayout(?string $name): bool
    {
        return LayoutRegistry::singleton()->hasLayout(get_class($this->owner), $name);
    }

    /**
     * Told to the author when the layout they picked has since been removed.
     *
     * Worth saying out loud rather than quietly swapping the layout: the block
     * will look different on the site from the moment the template goes, and
     * an author who is not told will assume they broke it.
     */
    protected function getMissingLayoutNotice(): ?LiteralField
    {
        $saved = (string) $this->owner->Style;

        if (!$saved || $this->hasLayout($saved) || !$this->owner->isInDB()) {
            return null;
        }

        $replacement = LayoutRegistry::singleton()->getDefaultLayout(get_class($this->owner));

        $message = $replacement
            ? _t(
                __CLASS__ . '.LAYOUT_REMOVED',
                'The layout this block was using is no longer available. It is showing with the {replacement} layout instead - choose the one you want below and save.',
                ['replacement' => $replacement]
            )
            : _t(
                __CLASS__ . '.LAYOUT_REMOVED_NO_REPLACEMENT',
                'The layout this block was using is no longer available, so it is showing with its standard appearance.'
            );

        return LiteralField::create(
            'FlavoursLayoutNotice',
            '<div class="alert alert-warning">' . htmlspecialchars($message, ENT_QUOTES) . '</div>'
        );
    }

    /**
     * Put a field above everything else on the Content tab.
     *
     * Title is elemental's first visible field, so inserting before it lands
     * the picker at the top. Falling back to appending covers an element that
     * has hidden its Title.
     */
    protected function insertAtTopOfMain(FieldList $fields, $field): void
    {
        if (!$fields->insertBefore('Title', $field)) {
            $fields->addFieldToTab('Root.Main', $field);
        }
    }
}
