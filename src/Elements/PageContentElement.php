<?php

namespace Toast\Flavours\Elements;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Toast\Flavours\Extensions\PageContentExtension;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;

/**
 * Marks where the page's own content sits among the blocks.
 *
 * A page template renders its content in one fixed place. This block lets an
 * author drag that content up and down the page relative to the blocks, which
 * is the whole of its job - it has nothing of its own to edit.
 *
 * It renders the page's Layout template through the page's own controller, so
 * each page type keeps one template in the place Silverstripe already looks for
 * it. Nothing needs writing twice, and a page type with no blocks at all still
 * renders exactly as it did.
 *
 * The block is managed rather than added: applying
 * {@link PageContentExtension} to a page type puts one on every page of that
 * type, and taking the extension off removes them again. Authors can reorder it
 * but cannot add or delete it, because a page with two of them would render its
 * content twice and a page with none would lose its content entirely.
 */
class PageContentElement extends BaseElement
{
    private static $table_name = 'Flavours_PageContentElement';

    private static $singular_name = 'page content';

    private static $plural_name = 'page content';

    private static $description = 'Positions the page\'s own content among the blocks';

    private static $icon = 'font-icon-block-virtual-page';

    /**
     * The page's content is already indexed as part of the page itself.
     * Indexing it again through this block would double every match.
     */
    private static $search_indexable = false;

    private static $displays_title_in_template = false;

    /**
     * Rendered without a block holder - see the template for why.
     */
    private static $controller_template = 'PageContentHolder';

    /**
     * Pages whose Layout template is currently being rendered, keyed by page
     * ID. See forTemplate() for what this guards against.
     */
    private static array $rendering = [];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Every field, not a list of them. This block holds nothing: it marks a
        // position. A heading or a colour set here would be filled in once and
        // then never appear anywhere, and naming the fields one by one only
        // works until the next extension adds another.
        foreach (array_keys($fields->getDataFields()) as $name) {
            $fields->removeByName($name);
        }

        $fields->addFieldToTab('Root.Main', LiteralField::create(
            'FlavoursPageContentInfo',
            '<p class="message">' . _t(
                __CLASS__ . '.INFO',
                'This block marks where the page\'s own content appears. Drag it above or below the other blocks to move the content up or down the page. There is nothing to fill in here - edit the content on the Page tab.'
            ) . '</p>'
        ));

        return $fields;
    }

    /**
     * Authors do not add this block; applying the extension to a page type
     * does. Returning false here is also what keeps it out of elemental's
     * "Add block" list, which asks the singleton.
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * Nor do they delete it. A page that lost this block would lose its own
     * content from the front end with nothing on screen to explain why.
     */
    public function canDelete($member = null)
    {
        return false;
    }

    public function forTemplate($holder = true): string
    {
        $page = $this->getPage();

        if (!$page instanceof SiteTree || !$page->exists()) {
            return '';
        }

        // Only ever one block does the rendering. Creation is managed, so this
        // is a guard against data rather than against authors - a page
        // duplicated by an older version of this module, say.
        if (!$this->isFirstOnPage()) {
            return '';
        }

        if (isset(PageContentElement::$rendering[$page->ID])) {
            return $this->recursionNotice();
        }

        PageContentElement::$rendering[$page->ID] = true;

        try {
            return (string) SSViewer::create($this->getLayoutTemplates($page))
                ->process($this->getPageController($page));
        } finally {
            // In a finally block so an exception raised inside the page's own
            // Layout template cannot leave the guard set for the rest of the
            // request, which would blank every later render of this block.
            unset(PageContentElement::$rendering[$page->ID]);
        }
    }

    /**
     * Put one on every page that should have one, and take away the ones that
     * should not be there any more.
     *
     * Called on dev/build, which is where a page type gaining or losing
     * {@link PageContentExtension} takes effect. Both directions live in one
     * method because they are the same question asked twice, and splitting them
     * is how one of them ends up not being called.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        // Only the base class does the work; running once per subclass would
        // repeat the whole sweep for no gain.
        if (static::class !== PageContentElement::class) {
            return;
        }

        $added = 0;
        $removed = 0;

        // Draft, said out loud. dev/build runs with no reading mode set, and
        // both the sweep and the writes below are meant for the draft stage.
        Versioned::withVersionedMode(function () use (&$added, &$removed) {
            Versioned::set_stage(Versioned::DRAFT);

            foreach (SiteTree::get() as $page) {
                if ($page->hasExtension(PageContentExtension::class) && PageContentElement::ensureOnPage($page)) {
                    $added++;
                }
            }

            foreach (PageContentElement::get() as $element) {
                $page = $element->getPage();

                if ($page && $page->hasExtension(PageContentExtension::class)) {
                    continue;
                }

                // Unpublished first, so removing the extension takes the block
                // off the live site rather than leaving an orphan rendering there.
                $element->doUnpublish();
                $element->delete();
                $removed++;
            }
        });

        if ($added) {
            DB::alteration_message("Added $added page content block(s)", 'created');
        }

        if ($removed) {
            DB::alteration_message("Removed $removed page content block(s)", 'deleted');
        }
    }

    /**
     * Make sure this page has exactly one page content block.
     *
     * @return bool Whether one had to be created.
     */
    public static function ensureOnPage(DataObject $page): bool
    {
        $area = PageContentElement::getAreaForPage($page);

        if (!$area || !$area->exists()) {
            return false;
        }

        if (PageContentElement::findOnPage($page)) {
            return false;
        }

        $element = PageContentElement::create();
        $element->ParentID = $area->ID;
        $element->Title = _t(__CLASS__ . '.TITLE', 'Page content');
        $element->write();

        return true;
    }

    /**
     * This page's block, if it has one.
     */
    public static function findOnPage(DataObject $page): ?PageContentElement
    {
        $area = PageContentElement::getAreaForPage($page);

        if (!$area || !$area->exists()) {
            return null;
        }

        return PageContentElement::get()->filter('ParentID', $area->ID)->first();
    }

    /**
     * The elemental area this block belongs in, named by config so a page with
     * more than one area can say which of them holds its content.
     */
    protected static function getAreaForPage(DataObject $page): ?ElementalArea
    {
        $relation = (string) $page->config()->get('page_content_area');

        if (!$relation || !$page->hasMethod($relation)) {
            return null;
        }

        $area = $page->$relation();

        return $area instanceof ElementalArea ? $area : null;
    }

    /**
     * Whether this is the block that actually renders the page content.
     */
    public function isFirstOnPage(): bool
    {
        if (!$this->ParentID) {
            return true;
        }

        $first = PageContentElement::get()
            ->filter('ParentID', $this->ParentID)
            ->sort('Sort')
            ->first();

        return !$first || (int) $first->ID === (int) $this->ID;
    }

    /**
     * The templates that make up `$Layout` for this page.
     *
     * Built the same way ContentController::getViewer() builds them - the
     * record's templates first, then the controller's - so a page type whose
     * template is found only through its controller still resolves.
     */
    protected function getLayoutTemplates(SiteTree $page): array
    {
        $controller = $this->getPageController($page);

        return [
            'type' => 'Layout',
            ...$page->getViewerTemplates(),
            ...SSViewer::get_templates_by_class(get_class($controller), '', Controller::class),
        ];
    }

    /**
     * Render against the live controller where there is one, so the page's
     * Layout template sees the same request, form state and cached data it
     * would in its usual position. Only build a controller when there is no
     * page controller in play - a CMS preview of the block on its own, say.
     */
    protected function getPageController(SiteTree $page): ContentController
    {
        $current = Controller::curr();

        // Compared by ID rather than identity: the controller's record and the
        // one reached through this block's elemental area are separate
        // instances of the same page.
        if ($current instanceof ContentController) {
            $record = $current->data();

            if ($record instanceof SiteTree && (int) $record->ID === (int) $page->ID) {
                return $current;
            }
        }

        return ModelAsController::controller_for($page);
    }

    /**
     * Reached when the page's Layout template renders the elemental area that
     * this block lives in, so rendering the Layout template renders this block
     * again, for ever.
     *
     * The fix is in the theme, so the message is aimed at a developer and only
     * appears in dev. On a live site the block renders nothing rather than
     * exhausting memory partway through the page.
     */
    protected function recursionNotice(): string
    {
        if (!Director::isDev()) {
            return '';
        }

        return '<!-- ' . static::class . ': the page Layout template renders the elemental area '
            . 'that contains this block. Move $ElementalArea out of the Layout template and into '
            . 'the page template so the two do not render each other. -->';
    }
}
