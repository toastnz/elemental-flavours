<?php

namespace Toast\Flavours\Tasks;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\BuildTask;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Toast\Flavours\Elements\PageContentElement;
use Toast\Flavours\Extensions\PageContentExtension;

/**
 * Moves toastnz/blocks-layouts content onto elemental blocks.
 *
 * Reports what it would do and changes nothing until you pass --commit, because
 * this is the sort of thing that gets run once against real content and the
 * time to find out it mapped the wrong field is before it has written.
 *
 * Tell it which class becomes which:
 *
 *     Toast\Flavours\Tasks\MigrateBlocksTask:
 *       block_map:
 *         Toast\Blocks\TextBlock: Toast\Elements\TextElement
 *         Toast\Blocks\AccordionBlock: Toast\Elements\AccordionElement
 *       item_map:
 *         Toast\Blocks\Items\AccordionItem: Toast\Elements\Items\AccordionItem
 *
 * Everything else follows from the two schemas. A field is copied when both
 * sides have one of that name, a has_one when both sides have that relation,
 * and the layout comes from the Template class the block stored. Anything on
 * the block with nowhere to go is listed rather than dropped quietly, which is
 * how you find out a field was renamed.
 *
 * Publishing follows the old content rather than the run: an element is
 * published only if the block it came from was, and a page is never published
 * on an author's behalf - see publishArea().
 */
class MigrateBlocksTask extends BuildTask
{
    use Configurable;

    protected static string $commandName = 'flavours-migrate-blocks';

    protected string $title = 'Flavours: migrate blocks-layouts content to elemental';

    protected static string $description = 'Copies toastnz/blocks-layouts blocks onto elemental blocks. Reports only, unless run with --commit.';

    /**
     * Old block class => new element class.
     */
    private static array $block_map = [];

    /**
     * Old item class => new item class.
     */
    private static array $item_map = [];

    /**
     * The old block that marked where the page's own content sat. It is not
     * copied - PageContentExtension already gives the page an element of its
     * own - but its position is, so the content stays where the author put it.
     */
    private static string $page_content_block = 'Toast\Blocks\PageContentBlock';

    /**
     * Blocks to pass over entirely.
     */
    private static array $ignore_blocks = [];

    /**
     * Fields that changed name on the way across, per old class: old class =>
     * [old field => new field]. Applies to subclasses of the class named too,
     * and to items as well as blocks.
     *
     *     field_map:
     *       Toast\Blocks\CollectionBlock:
     *         Type: CollectionType
     */
    private static array $field_map = [];

    /**
     * Never copied: identity, versioning, and the fields whose meaning is
     * different on the other side.
     */
    private const SKIP_FIELDS = [
        'ID', 'ClassName', 'Created', 'LastEdited', 'Version', 'RecordID',
        'Sort', 'SortOrder', 'ParentID', 'Template', 'CSSFile',
    ];

    private array $notes = [];

    /**
     * Block ID => number of pages it is on, filled once per run.
     */
    private ?array $pageCounts = null;

    public function getOptions(): array
    {
        return [
            new InputOption('commit', null, InputOption::VALUE_NONE, 'Actually write the elements. Without this, nothing changes.'),
            new InputOption('force', null, InputOption::VALUE_NONE, 'Migrate a page even if it already has elements on it'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $commit = (bool) $input->getOption('commit');
        $force = (bool) $input->getOption('force');

        // Elemental only creates elemental areas while reading Stage, and a CLI
        // task starts with no reading mode set at all.
        Versioned::set_stage(Versioned::DRAFT);

        $map = (array) MigrateBlocksTask::config()->get('block_map');

        if (!$map) {
            $output->writeln('<error>Nothing in block_map. See the class docblock for what it wants.</error>');

            return Command::FAILURE;
        }

        $output->writeln($commit ? 'Writing elements.' : 'Reporting only — pass --commit to write.');
        $output->writeln('');

        $pageContentBlock = (string) MigrateBlocksTask::config()->get('page_content_block');
        $ignore = (array) MigrateBlocksTask::config()->get('ignore_blocks');

        $migrated = 0;
        $skipped = 0;

        foreach ($this->pagesWithBlocks() as $page) {
            $blocks = $page->ContentBlocks()->sort('SortOrder');

            if (!$blocks->exists()) {
                continue;
            }

            if (!$page->hasMethod('ElementalArea')) {
                $this->note("{$page->Title}: no elemental area — is ElementalPageExtension applied to this page type?");
                $skipped++;

                continue;
            }

            $output->writeln("<options=bold>{$page->Title}</>");

            // Asked before anything is written to the page, which is what
            // decides whether it can be published again afterwards.
            $pageWasClean = $page->isPublished() && !$page->stagesDiffer();
            $area = $page->ElementalArea();

            // Elemental creates a page's area on write, but only while reading
            // the draft stage, and dev/build runs with no reading mode set at
            // all - so a site can have the extension applied and still have no
            // areas. Rather than refuse, make the one this page needs.
            if (!$area->exists()) {
                if (!$commit) {
                    $output->writeln('  would create an elemental area first');
                } else {
                    $page->ensureElementalAreasExist($page->getElementalRelations());
                    $page->write();
                    $area = $page->ElementalArea();
                }
            }

            $alreadyMigrated = $area->exists()
                && $area->Elements()->exclude('ClassName', PageContentElement::class)->exists();

            if ($alreadyMigrated && !$force) {
                $this->note("{$page->Title}: already has elements, left alone. Use --force to add to it anyway.");
                $skipped++;
                $output->writeln('  skip   already migrated');
                $output->writeln('');

                continue;
            }

            // An area that does not exist yet has an UnsavedRelationList, which
            // cannot be asked for a maximum. The page content element is left
            // out: this run decides where it goes, so where it already is must
            // not push everything else down.
            $sort = $area->exists()
                ? (int) $area->Elements()->exclude('ClassName', PageContentElement::class)->max('Sort')
                : 0;
            $changed = false;

            foreach ($blocks as $block) {
                if (in_array($block->ClassName, $ignore, true)) {
                    $output->writeln("  skip   {$block->ClassName}");

                    continue;
                }

                if ($block->ClassName === $pageContentBlock) {
                    $changed = $this->placePageContent($page, ++$sort, $commit, $output) || $changed;

                    continue;
                }

                $elementClass = $map[$block->ClassName] ?? null;

                if (!$elementClass || !class_exists($elementClass)) {
                    $this->note("No mapping for {$block->ClassName} (on {$page->Title})");
                    $output->writeln("  <error>unmapped</error> {$block->ClassName}");

                    continue;
                }

                $shares = $this->pageCount($block);

                if ($shares > 1) {
                    $this->note(sprintf(
                        '"%s" was one block on %d pages and becomes %d separate blocks. Editing one will no longer change the others.',
                        $block->Title ?: $block->ClassName,
                        $shares,
                        $shares
                    ));
                }

                $publish = $block->isPublished();

                if ($publish && $block->stagesDiffer()) {
                    $this->note(sprintf(
                        '"%s" (block #%d) had unpublished changes. The element was published with them - check it before going live.',
                        $block->Title ?: $block->ClassName,
                        $block->ID
                    ));
                }

                $this->convert($block, $elementClass, ++$sort, $commit, $publish, $area);
                $changed = true;
                $output->writeln(sprintf(
                    '  %-6s %s -> %s%s%s',
                    $commit ? 'wrote' : 'would',
                    $block->Title ?: $block->ClassName,
                    $elementClass,
                    $publish ? '' : '  (draft)',
                    $shares > 1 ? "  (one of $shares copies)" : ''
                ));
                $migrated++;
            }

            if ($commit && $changed) {
                $this->publishArea($page, $area, $pageWasClean);
            }

            $output->writeln('');
        }

        foreach ($this->notes as $note) {
            $output->writeln("<comment>note</comment> $note");
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '%s %d block(s); %d page(s) skipped.',
            $commit ? 'Migrated' : 'Would migrate',
            $migrated,
            $skipped
        ));

        return Command::SUCCESS;
    }

    /**
     * Put the page's own content where its old page content block was.
     *
     * The element itself comes from PageContentExtension, not from here - the
     * extension is what keeps exactly one on the page from now on - so all this
     * does is make sure it exists and give it the old block's place in the run.
     *
     * @return bool Whether anything was written, so a re-run that finds the
     * content already in place does not publish the page again.
     */
    private function placePageContent(SiteTree $page, int $sort, bool $commit, PolyOutput $output): bool
    {
        if (!$page->hasExtension(PageContentExtension::class)) {
            $this->note(sprintf(
                '%s: had a page content block, but %s does not have PageContentExtension, so its content stays in the page template.',
                $page->Title,
                $page->ClassName
            ));
            $output->writeln('  skip   page content (no PageContentExtension on this page type)');

            return false;
        }

        $existing = PageContentElement::findOnPage($page);

        if ($existing && (int) $existing->Sort === $sort && $existing->isPublished() && !$existing->stagesDiffer()) {
            $output->writeln("  ok     page content already at position $sort");

            return false;
        }

        $output->writeln(sprintf('  %-6s page content at position %d', $commit ? 'placed' : 'would', $sort));

        if (!$commit) {
            return false;
        }

        PageContentElement::ensureOnPage($page);
        $element = PageContentElement::findOnPage($page);

        if (!$element) {
            return false;
        }

        $element->Sort = $sort;
        $element->write();
        $element->publishSingle();

        return true;
    }

    /**
     * Make what was migrated visible on the live site without publishing the
     * page's own unreviewed edits.
     *
     * The area and its published elements go live regardless. The page only
     * matters because the live page row is what points at the area, and that
     * pointer was only just written to draft: a page that was published with
     * no pending changes is published again to carry it over, and a page with
     * pending changes is left for its author, since publishing it would push
     * their half-finished edits live along with the blocks.
     */
    private function publishArea(SiteTree $page, ElementalArea $area, bool $pageWasClean): void
    {
        if (!$area->exists()) {
            return;
        }

        $area->publishSingle();

        if (!$page->isPublished()) {
            return;
        }

        if ($pageWasClean) {
            $page->publishSingle();

            return;
        }

        $this->note(sprintf(
            '%s has unpublished changes of its own, so it was not published. Its blocks go live the next time it is.',
            $page->Title
        ));
    }

    /**
     * How many pages this block is linked to.
     *
     * A blocks-layouts block is a many_many, so one record can sit on any
     * number of pages. An element belongs to the page it is on, so a shared
     * block becomes a copy on each page - the content survives, the sharing
     * does not, and an author who has been editing one block to change three
     * pages needs telling.
     *
     * Counted once, from the join table, for every block at once.
     */
    private function pageCount(DataObject $block): int
    {
        if ($this->pageCounts === null) {
            $this->pageCounts = [];
            $join = DataObject::getSchema()->manyManyComponent(SiteTree::class, 'ContentBlocks')
                ?? DataObject::getSchema()->manyManyComponent('Page', 'ContentBlocks');

            if ($join) {
                $childField = $join['childField'];
                $rows = SQLSelect::create(
                    ["\"$childField\"", 'Pages' => 'COUNT(*)'],
                    "\"{$join['join']}\""
                )->setGroupBy("\"$childField\"")->execute();

                foreach ($rows as $row) {
                    $this->pageCounts[(int) $row[$childField]] = (int) $row['Pages'];
                }
            }
        }

        return $this->pageCounts[(int) $block->ID] ?? 1;
    }

    private function pagesWithBlocks(): iterable
    {
        foreach (SiteTree::get() as $page) {
            if ($page->hasMethod('ContentBlocks')) {
                yield $page;
            }
        }
    }

    private function convert(
        DataObject $block,
        string $elementClass,
        int $sort,
        bool $commit,
        bool $publish,
        ElementalArea $area
    ): BaseElement {
        /** @var BaseElement $element */
        $element = $elementClass::create();
        $element->ParentID = $area->ID;
        $element->Sort = $sort;
        $element->Style = $this->styleFromTemplate($block->Template);

        $this->copyFields($block, $element);

        if (!$commit) {
            return $element;
        }

        // Written before the has_ones are copied, so a link copied across can
        // be given this element as its owner.
        $element->write();

        $this->copyHasOnes($block, $element);
        $this->copyItems($block, $element);
        $this->copyManyManys($block, $element);

        $element->write();

        if ($publish) {
            $element->publishRecursive();
        }

        return $element;
    }

    /**
     * The old Template held a class name like Toast\Blocks\Split\TextBlock, and
     * the layout is the second-to-last part of it.
     */
    private function styleFromTemplate(?string $template): string
    {
        $parts = array_values(array_filter(explode('\\', (string) $template)));

        return count($parts) >= 2 ? $parts[count($parts) - 2] : 'Default';
    }

    private function copyFields(DataObject $block, DataObject $element): void
    {
        $blockFields = DataObject::getSchema()->databaseFields(get_class($block));
        $elementFields = DataObject::getSchema()->databaseFields(get_class($element));
        $hasOneFields = array_map(fn ($relation) => $relation . 'ID', array_keys((array) $block->hasOne()));
        $renamed = $this->getFieldMap($block);

        foreach ($elementFields as $field => $spec) {
            $source = array_search($field, $renamed, true) ?: $field;

            if (in_array($field, MigrateBlocksTask::SKIP_FIELDS, true)
                || in_array($field, $hasOneFields, true)
                || !isset($blockFields[$source])
            ) {
                continue;
            }

            $element->$field = $block->$source;
        }

        // Anything the block carried that has no home is worth saying out loud.
        foreach ($blockFields as $field => $spec) {
            if (in_array($field, MigrateBlocksTask::SKIP_FIELDS, true)
                || isset($elementFields[$field])
                || isset($elementFields[$renamed[$field] ?? ''])
                || $block->$field === null
                || $block->$field === ''
                || $block->$field === 0
            ) {
                continue;
            }

            $this->note(get_class($block) . ".$field has no matching field on " . get_class($element) . " (block #{$block->ID})");
        }
    }

    /**
     * The renames that apply to this record: its own class's and its parents'.
     *
     * @return array<string, string> old field => new field
     */
    private function getFieldMap(DataObject $record): array
    {
        $map = (array) MigrateBlocksTask::config()->get('field_map');
        $renamed = [];

        foreach (ClassInfo::ancestry($record) as $class) {
            $renamed = [...$renamed, ...(array) ($map[$class] ?? [])];
        }

        return $renamed;
    }

    /**
     * has_ones are copied by ID, which is right for a file or a page - the
     * element points at the same one the block did.
     *
     * A linkfield Link is different: it belongs to the record that holds it,
     * and records its owner. Pointing the element at the block's Link would
     * leave the link claiming the old block as its owner, and deleting that
     * block later would delete the link out from under the element. So a Link
     * is copied, and the copy is owned by the element.
     */
    private function copyHasOnes(DataObject $block, DataObject $element): void
    {
        $blockHasOne = (array) $block->hasOne();

        foreach ((array) $element->hasOne() as $relation => $class) {
            if ($relation === 'Parent' || !isset($blockHasOne[$relation])) {
                continue;
            }

            $idField = $relation . 'ID';

            if (!$block->$idField) {
                continue;
            }

            if (is_string($class) && class_exists(Link::class) && is_a($class, Link::class, true)) {
                $element->$idField = $this->copyLink($block->$relation(), $element, $relation);

                continue;
            }

            $element->$idField = $block->$idField;
        }
    }

    private function copyLink(?DataObject $link, DataObject $owner, string $relation): int
    {
        if (!$link || !$link->exists()) {
            return 0;
        }

        $copy = $link->duplicate(false);
        $copy->OwnerID = $owner->ID;
        $copy->OwnerClass = $owner->ClassName;
        $copy->OwnerRelation = $relation;
        $copy->write();

        return (int) $copy->ID;
    }

    /**
     * has_many children, remapped one class to another.
     *
     * Items are written to draft only. The element owns them, so publishing the
     * element publishes them with it - and an element left as a draft leaves
     * its items as drafts too.
     */
    private function copyItems(DataObject $block, BaseElement $element): void
    {
        $itemMap = (array) MigrateBlocksTask::config()->get('item_map');

        foreach ((array) $element->hasMany() as $relation => $itemClass) {
            if (!$block->hasMethod($relation)) {
                continue;
            }

            // A has_many can be declared as Class.Relation; only the class is
            // wanted here.
            $itemClass = explode('.', (string) $itemClass)[0];

            foreach ($block->$relation()->sort('SortOrder') as $index => $source) {
                $target = $itemMap[$source->ClassName] ?? $itemClass;

                if (!class_exists($target)) {
                    $this->note("No mapping for item {$source->ClassName}");

                    continue;
                }

                $item = $target::create();
                $item->ParentID = $element->ID;
                $item->Sort = $index + 1;

                $this->copyFields($source, $item);
                $item->write();

                // After the write, for the same reason as on the element: a
                // copied Link needs an owner that exists.
                $this->copyHasOnes($source, $item);
                $item->write();
            }
        }
    }

    private function copyManyManys(DataObject $block, BaseElement $element): void
    {
        $blockManyMany = (array) $block->manyMany();
        $blockExtra = (array) $block->manyManyExtraFields();
        $elementExtra = (array) $element->manyManyExtraFields();

        foreach ((array) $element->manyMany() as $relation => $spec) {
            if (!isset($blockManyMany[$relation]) || !$block->hasMethod($relation)) {
                continue;
            }

            // Extra fields on the join carry the things the relation itself
            // cannot say - a slider's image order lives in one. Adding a record
            // without them silently reorders the whole relation, and the
            // difference does not show up anywhere until someone looks at the
            // page. Only fields both sides declare are copied.
            $fields = array_keys(array_intersect_key(
                $blockExtra[$relation] ?? [],
                $elementExtra[$relation] ?? []
            ));

            foreach ($block->$relation() as $related) {
                $extra = [];

                foreach ($fields as $field) {
                    $extra[$field] = $related->$field;
                }

                $element->$relation()->add($related, $extra);
            }
        }
    }

    /**
     * Notes are collected rather than printed where they happen, so a page with
     * twenty blocks does not bury the same warning twenty times.
     */
    private function note(string $message): void
    {
        if (!in_array($message, $this->notes, true)) {
            $this->notes[] = $message;
        }
    }
}
