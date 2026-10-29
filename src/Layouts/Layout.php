<?php

namespace Toast\Flavours\Layouts;

/**
 * One layout a content author can pick for an element.
 *
 * The name is the template suffix and nothing more: a layout named "TextLeft"
 * on an ImageElement renders ImageElement_TextLeft.ss. Elemental already builds
 * that template name from the Style field in
 * {@link \DNADesign\Elemental\Models\BaseElement::getRenderTemplates()}, which
 * is why this module never resolves a template itself.
 */
class Layout
{
    /**
     * @param string $name Template suffix, e.g. "TextLeft"
     * @param string $label Shown under the icon in the CMS picker
     * @param string|null $icon Inline SVG markup, already read from disk
     * @param string|null $iconClass CSS class for an icon font or sprite
     */
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly ?string $icon = null,
        private readonly ?string $iconClass = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getIconClass(): ?string
    {
        return $this->iconClass;
    }

    public function hasIcon(): bool
    {
        return $this->icon !== null || $this->iconClass !== null;
    }

    /**
     * The shape the CMS picker is given, both server-side and through the form
     * schema. Keeping one method for both means the React component and the
     * .ss fallback can never drift apart.
     */
    public function toOption(): array
    {
        return [
            'value' => $this->name,
            'title' => $this->label,
            'icon' => $this->icon,
            'iconClass' => $this->iconClass,
        ];
    }
}
