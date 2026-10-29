<?php

namespace Toast\Flavours\Layouts;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Core\Path;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

/**
 * Works out which layouts an element offers, and what each one looks like in
 * the CMS picker.
 *
 * A layout can arrive from either direction and both are first class:
 *
 *  - a template file, e.g. ImageElement_TextLeft.ss anywhere on the theme
 *    cascade, which is how a developer adds a layout without touching YAML;
 *  - the `layouts` config on the element class, which is how a layout gets a
 *    label, an icon, or gets turned off.
 *
 * Elemental's own `styles` config is read too, so a project already using
 * upstream style variants keeps working with no changes.
 */
class LayoutRegistry implements Flushable
{
    use Configurable;
    use Injectable;

    /**
     * The layout offered first, and the one an element falls back to when its
     * saved layout is no longer available.
     */
    private static string $default_layout = 'Default';

    /**
     * Template suffixes that are never layouts.
     *
     * Elemental builds `<Class>_<AreaRelationName>` and `<Class>_EditorPreview`
     * template names of its own. Without this list a project that adds an
     * ImageElement_EditorPreview.ss to restyle the CMS summary would find
     * "Editor Preview" offered to authors as a layout.
     *
     * Area relation names are added automatically; only add to this if you
     * build other suffixed templates by hand.
     */
    private static array $reserved_template_suffixes = [
        'EditorPreview',
    ];

    protected ?CacheInterface $cache = null;

    /**
     * Per-request memo. The PSR-16 cache still costs a serialise and a lookup
     * per call, and a single CMS form build asks for the same class repeatedly.
     */
    private array $memo = [];

    public function setCache(CacheInterface $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    public function getCache(): CacheInterface
    {
        // Resolved lazily as well as injected, so that a registry built
        // directly - in a test, or before config is applied - still caches
        // rather than fataling on a null.
        if (!$this->cache) {
            $this->setCache(Injector::inst()->get(CacheInterface::class . '.FlavoursLayouts'));
        }

        return $this->cache;
    }

    public static function flush(): void
    {
        LayoutRegistry::singleton()->getCache()->clear();
    }

    /**
     * Every layout this element class offers, keyed by name, in the order they
     * should appear in the CMS.
     *
     * @return array<string, Layout>
     */
    public function getLayouts(string $elementClass): array
    {
        $cacheKey = $this->getCacheKey($elementClass);

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        $definitions = $this->getCache()->get($cacheKey);

        if ($definitions === null) {
            $definitions = $this->buildDefinitions($elementClass);
            $this->getCache()->set($cacheKey, $definitions);
        }

        $layouts = [];

        foreach ($definitions as $name => $definition) {
            $layouts[$name] = new Layout(
                $name,
                $definition['label'],
                $definition['icon'],
                $definition['iconClass']
            );
        }

        return $this->memo[$cacheKey] = $layouts;
    }

    public function hasLayout(string $elementClass, ?string $name): bool
    {
        return $name !== null
            && $name !== ''
            && array_key_exists($name, $this->getLayouts($elementClass));
    }

    /**
     * The layout to use when an element has none, or has one that has since
     * been removed. Prefers the configured default, then whatever is first.
     */
    public function getDefaultLayout(string $elementClass): ?string
    {
        $layouts = $this->getLayouts($elementClass);

        if ($layouts === []) {
            return null;
        }

        $default = (string) LayoutRegistry::config()->get('default_layout');

        if (array_key_exists($default, $layouts)) {
            return $default;
        }

        return array_key_first($layouts);
    }

    /**
     * Build the cacheable form of the layout list: plain arrays rather than
     * Layout objects, so a class change in a later deploy can never leave an
     * unserialisable object in a warm cache.
     *
     * @return array<string, array{label: string, icon: ?string, iconClass: ?string}>
     */
    protected function buildDefinitions(string $elementClass): array
    {
        $layoutConfig = (array) (Config::inst()->get($elementClass, 'layouts') ?: []);
        $styleConfig = (array) (Config::inst()->get($elementClass, 'styles') ?: []);

        // `layouts` describes layouts; it does not create them. A key there with
        // no template behind it is a typo or a leftover, and offering it would
        // give authors a choice that renders as the default. Elemental's own
        // `styles` are the one exception - a CSS-only variant is upstream's
        // intent - so they are offered with or without a template.
        $names = $this->sortNames(
            array_unique([
                ...$this->discoverLayoutNames($elementClass),
                ...array_map('strval', array_keys($styleConfig)),
            ]),
            $layoutConfig,
            $styleConfig
        );

        $definitions = [];

        foreach ($names as $name) {
            $entry = (array) ($layoutConfig[$name] ?? []);

            if ($this->isDisabled($elementClass, $name, $entry)) {
                continue;
            }

            $definitions[$name] = [
                'label' => (string) ($entry['label'] ?? $styleConfig[$name] ?? FormField::name_to_label($name)),
                'icon' => $this->readIcon($entry['icon'] ?? null),
                'iconClass' => isset($entry['icon_class']) ? (string) $entry['icon_class'] : null,
            ];
        }

        return $definitions;
    }

    /**
     * Config order wins where it is given, because a developer listing layouts
     * in YAML has usually listed them in the order authors should see them.
     * Everything else is alphabetical so the picker does not reshuffle itself
     * when a file is added, with the default pinned to the front.
     */
    protected function sortNames(array $names, array $layoutConfig, array $styleConfig): array
    {
        $default = (string) LayoutRegistry::config()->get('default_layout');
        $preferred = [$default, ...array_keys($layoutConfig), ...array_keys($styleConfig)];

        $ordered = [];

        foreach ($preferred as $name) {
            if (in_array($name, $names, true) && !in_array($name, $ordered, true)) {
                $ordered[] = $name;
            }
        }

        $remaining = array_values(array_diff($names, $ordered));
        sort($remaining, SORT_NATURAL | SORT_FLAG_CASE);

        return [...$ordered, ...$remaining];
    }

    protected function isDisabled(string $elementClass, string $name, array $entry): bool
    {
        // An explicit `disabled` beats exclude_layouts in both directions, so a
        // project can re-enable one layout the site config has excluded.
        if (array_key_exists('disabled', $entry)) {
            return (bool) $entry['disabled'];
        }

        $excluded = (array) (Config::inst()->get($elementClass, 'exclude_layouts') ?: []);

        return in_array($name, array_map('strval', $excluded), true);
    }

    /**
     * Find layout templates on the theme cascade.
     *
     * Walks the same class chain elemental renders through, so a layout
     * declared once against a parent element is offered on every subclass.
     *
     * @return string[]
     */
    protected function discoverLayoutNames(string $elementClass): array
    {
        $loader = ThemeResourceLoader::inst();
        $base = $loader->getBase();
        $themePaths = $loader->getThemePaths(SSViewer::get_themes());
        $reserved = $this->getReservedSuffixes();

        $names = [];

        foreach ($this->getTemplateNames($elementClass) as $templateName) {
            $directory = dirname($templateName);
            $shortName = basename($templateName);

            foreach ($themePaths as $themePath) {
                $segments = [$base, $themePath, 'templates'];

                // dirname() reports "." for a class in the global namespace,
                // which Path::join would turn into a literal "." directory.
                if ($directory !== '.') {
                    $segments[] = $directory;
                }

                $pattern = Path::join($segments) . '/' . $shortName . '_*.ss';

                foreach (glob($pattern) ?: [] as $file) {
                    $suffix = substr(basename($file, '.ss'), strlen($shortName) + 1);

                    // A suffix with an underscore in it is one of elemental's
                    // compound template names, e.g. _EditorPreview_TextLeft or
                    // _ElementalArea_Hero. The layout in those is already
                    // offered by its own single-suffix template.
                    if (!preg_match('/^[A-Za-z0-9]+$/', $suffix)) {
                        continue;
                    }

                    if (in_array($suffix, $reserved, true)) {
                        continue;
                    }

                    $names[$suffix] = $suffix;
                }
            }
        }

        return array_values($names);
    }

    /**
     * Template base names for an element class and its element ancestors,
     * mirroring {@link BaseElement::getRenderTemplates()}.
     *
     * @return string[] e.g. ["Toast/Elements/ImageElement", "Toast/Elements/MediaElement"]
     */
    protected function getTemplateNames(string $elementClass): array
    {
        $templateNames = [];

        foreach (array_reverse(ClassInfo::ancestry($elementClass)) as $class) {
            if ($class === BaseElement::class || $class === DataObject::class) {
                break;
            }

            $templateNames[] = str_replace('\\', '/', $class);
        }

        return $templateNames;
    }

    /**
     * Suffixes elemental generates itself, which must never be read as layouts.
     *
     * The area relation names are looked up rather than assumed, because a page
     * with two elemental areas renders `<Class>_SidebarElements.ss` and that is
     * an area-specific template, not a layout an author may choose.
     *
     * @return string[]
     */
    protected function getReservedSuffixes(): array
    {
        $cacheKey = 'reserved-suffixes';
        $reserved = $this->getCache()->get($cacheKey);

        if ($reserved !== null) {
            return $reserved;
        }

        $reserved = (array) LayoutRegistry::config()->get('reserved_template_suffixes');

        foreach (ClassInfo::classesWithExtension(ElementalAreasExtension::class) as $class) {
            foreach ((array) Config::inst()->get($class, 'has_one') as $relation => $relationClass) {
                if (is_a($relationClass, ElementalArea::class, true)) {
                    $reserved[] = $relation;
                }
            }
        }

        $reserved = array_values(array_unique($reserved));
        $this->getCache()->set($cacheKey, $reserved);

        return $reserved;
    }

    /**
     * Read an SVG off disk ready to be dropped straight into CMS markup.
     *
     * Everything before the opening <svg> is dropped: an XML prolog or a
     * doctype is legal in a standalone .svg file and invalid once inlined in
     * an HTML document, where it renders as stray text above the icon.
     */
    protected function readIcon(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $resolved = ModuleResourceLoader::singleton()->resolvePath($path);

        // resolvePath() returns a path relative to the project root for a
        // vendor: reference and leaves an already absolute path alone, so only
        // put the root in front when there isn't one there.
        $absolute = $this->isAbsolutePath($resolved)
            ? $resolved
            : Path::join(BASE_PATH, $resolved);

        if (!is_file($absolute) || !is_readable($absolute)) {
            return null;
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return null;
        }

        $start = stripos($contents, '<svg');

        return $start === false ? null : substr($contents, $start);
    }

    /**
     * Framework's Path helper has no absolute test of its own. A Windows path
     * ("C:\\site") doesn't begin with a separator, so check for that shape as
     * well rather than trusting a leading slash.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * Themes are part of the key because a subsite can run a different theme,
     * and the same element class then has a different set of layout templates
     * available to it.
     */
    protected function getCacheKey(string $elementClass): string
    {
        return 'layouts-' . md5($elementClass . '|' . implode(',', SSViewer::get_themes()));
    }
}
