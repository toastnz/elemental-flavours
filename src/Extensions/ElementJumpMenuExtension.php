<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\HTML;

/**
 * A menu of every block on the page, beside the previous and next arrows.
 *
 * The arrows step; this jumps. Editing block nineteen of twenty-four and
 * wanting block three is otherwise a trip back to the list, and the list is a
 * different screen with a different scroll position.
 *
 * It goes in the form's own action bar, which is the strip the CMS already
 * keeps for moving around a record, rather than into the form body - a field in
 * the body would have to be laid out around the tabs and the editor, and that
 * is a fight with the CMS's own chrome that has no winner.
 *
 * The entries are plain links, the same as the arrows, so the CMS routes them
 * the way it routes those: as an ajax load, with the unsaved changes prompt
 * that `discard-confirmation` brings.
 */
class ElementJumpMenuExtension extends Extension
{
    protected function updateFormActions(FieldList $actions): void
    {
        $record = $this->owner->getRecord();

        if (!$record instanceof BaseElement || !$record->exists()) {
            return;
        }

        $rightGroup = $actions->fieldByName('RightGroup');

        if (!$rightGroup instanceof CompositeField) {
            return;
        }

        $blocks = $this->getBlocks();

        // One block is not a list worth opening, and the arrows beside it are
        // already both disabled.
        if (count($blocks) < 2) {
            return;
        }

        $menu = LiteralField::create('BlockJumpMenu', $this->buildMenu($blocks, $record));
        $menu->setAllowHTML(true);

        // Before the arrows, so the bar reads as a place and then a direction.
        $children = $rightGroup->getChildren();

        if ($children->fieldByName('PreviousAndNextGroup')) {
            $children->insertBefore('PreviousAndNextGroup', $menu);

            return;
        }

        $rightGroup->push($menu);
    }

    /**
     * The blocks as the grid has them, so the menu is in the same order as the
     * arrows and the list an author came from.
     *
     * @return BaseElement[]
     */
    protected function getBlocks(): array
    {
        return $this->owner->getGridField()->getManipulatedList()->toArray();
    }

    protected function buildMenu(array $blocks, BaseElement $current): string
    {
        $gridField = $this->owner->getGridField();
        $position = 0;
        $items = '';

        foreach ($blocks as $index => $block) {
            $isCurrent = (int) $block->ID === (int) $current->ID;

            if ($isCurrent) {
                $position = $index + 1;
            }

            $link = $gridField->addAllStateToUrl(
                Controller::join_links($gridField->Link(), 'item', $block->ID)
            );

            $label = $block->Title ?: $block->getType();

            $items .= HTML::createTag('li', [], HTML::createTag(
                'a',
                [
                    'href' => $link,
                    'class' => 'dropdown-item flavours-block-jump__item discard-confirmation'
                        . ($isCurrent ? ' flavours-block-jump__item--current' : ''),
                    'aria-current' => $isCurrent ? 'true' : null,
                ],
                sprintf(
                    '<span class="flavours-block-jump__number">%d</span>'
                        . '<span class="flavours-block-jump__label">%s</span>'
                        . '<span class="flavours-block-jump__type">%s</span>',
                    $index + 1,
                    Convert::raw2xml($label),
                    Convert::raw2xml($block->getType())
                )
            ));
        }

        // Says where in the page this block sits, which is the question an
        // author has just before they want the menu at all.
        $toggle = HTML::createTag(
            'button',
            [
                'type' => 'button',
                'class' => 'btn btn-secondary dropdown-toggle flavours-block-jump__toggle',
                'aria-expanded' => 'false',
                'aria-haspopup' => 'true',
                'title' => _t(__CLASS__ . '.JUMP', 'Go to another block on this page'),
            ],
            sprintf(
                _t(__CLASS__ . '.POSITION', 'Block %d of %d') ?? 'Block %d of %d',
                $position,
                count($blocks)
            )
        );

        return HTML::createTag(
            'div',
            ['class' => 'flavours-block-jump dropdown me-2'],
            $toggle . HTML::createTag(
                'ul',
                ['class' => 'dropdown-menu dropdown-menu-end flavours-block-jump__menu'],
                $items
            )
        );
    }
}
