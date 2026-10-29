<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Forms\ElementalAreaField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Moves the block editor onto a tab of its own.
 *
 * Elemental puts it on the Content tab, above the metadata. On a page type with
 * a few fields of its own that is fine; on one with several it means scrolling
 * past the page's settings to reach the blocks, which are the thing an author
 * came to edit. blocks-layouts gave them their own tab and this puts it back.
 *
 * Off unless a tab is named, so the module does not rearrange a CMS that has
 * not asked for it:
 *
 *     Page:
 *       elemental_area_tab: 'Root.Blocks'
 *
 * @extends Extension<SiteTree>
 */
class ElementalAreaTabExtension extends Extension
{
    private static ?string $elemental_area_tab = null;

    /**
     * Runs after elemental has added the field, which is what the order of the
     * extensions list decides - this one is applied after ElementalPageExtension
     * so there is something to move by the time it looks.
     */
    protected function updateCMSFields(FieldList $fields): void
    {
        $tab = $this->owner->config()->get('elemental_area_tab');

        if (!$tab) {
            return;
        }

        foreach ((array) $this->owner->getElementalRelations() as $relation) {
            $field = $fields->dataFieldByName($relation);

            // Only ever the block editor. A page that has replaced it, or one
            // where elemental decided not to add it at all, is left alone.
            if (!$field instanceof ElementalAreaField) {
                continue;
            }

            $fields->removeByName($relation);
            $fields->addFieldToTab($tab, $field);
        }
    }
}
