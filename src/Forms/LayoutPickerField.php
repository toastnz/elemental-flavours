<?php

namespace Toast\Flavours\Forms;

use Toast\Flavours\Layouts\Layout;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Model\ArrayData;

/**
 * A radio group that shows each layout as a picture rather than a line of text.
 *
 * Extends OptionsetField rather than replacing it so that validation, readonly
 * transformation and the plain server-rendered fallback all keep working: if
 * the CMS javascript never loads, an author still gets a usable set of radio
 * buttons instead of an empty box.
 */
class LayoutPickerField extends OptionsetField
{
    protected $schemaComponent = 'LayoutPickerField';

    /**
     * Keyed by layout name, matching the keys of the field's source.
     *
     * @var array<string, Layout>
     */
    protected array $layouts = [];

    /**
     * @param array<string, Layout> $layouts
     */
    public static function createForLayouts(
        string $name,
        ?string $title,
        array $layouts,
        mixed $value = null
    ): static {
        $source = [];

        foreach ($layouts as $layoutName => $layout) {
            $source[$layoutName] = $layout->getLabel();
        }

        return static::create($name, $title, $source, $value)->setLayouts($layouts);
    }

    /**
     * @param array<string, Layout> $layouts
     */
    public function setLayouts(array $layouts): static
    {
        $this->layouts = $layouts;

        return $this;
    }

    /**
     * @return array<string, Layout>
     */
    public function getLayouts(): array
    {
        return $this->layouts;
    }

    public function getLayout(string $name): ?Layout
    {
        return $this->layouts[$name] ?? null;
    }

    /**
     * True once every option can be told apart by its picture. A picker where
     * only some layouts have an icon reads as broken, so the template and the
     * React component both fall back to plain labels in that case.
     */
    public function hasIcons(): bool
    {
        if ($this->layouts === []) {
            return false;
        }

        foreach ($this->layouts as $layout) {
            if (!$layout->hasIcon()) {
                return false;
            }
        }

        return true;
    }

    public function getSchemaStateDefaults()
    {
        $data = parent::getSchemaStateDefaults();

        // Merge rather than rebuild, so the disabled flags and any future keys
        // the parent adds to each option survive.
        foreach ($data['source'] as $index => $option) {
            $layout = $this->getLayout((string) ($option['value'] ?? ''));

            $data['source'][$index] = array_merge($option, [
                'icon' => $layout?->getIcon(),
                'iconClass' => $layout?->getIconClass(),
            ]);
        }

        return $data;
    }

    public function getSchemaDataDefaults()
    {
        $data = parent::getSchemaDataDefaults();
        $data['data']['hasIcons'] = $this->hasIcons();

        return $data;
    }

    protected function getFieldOption($value, $title, $odd)
    {
        /** @var ArrayData $option */
        $option = parent::getFieldOption($value, $title, $odd);
        $layout = $this->getLayout((string) $value);

        // HTMLFragment rather than HTMLText: the icon is a whole <svg> element
        // read from a developer-controlled file, and running it through the
        // shortcode parser would only give it a chance to mangle the markup.
        $option->setField('Icon', $layout?->getIcon()
            ? DBField::create_field('HTMLFragment', $layout->getIcon())
            : null);
        $option->setField('IconClass', $layout?->getIconClass());

        return $option;
    }

    public function Type()
    {
        return 'optionset layoutpicker';
    }
}
