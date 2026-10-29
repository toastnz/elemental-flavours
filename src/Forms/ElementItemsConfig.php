<?php

namespace Toast\Flavours\Forms;

use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Grid configuration for a list of {@link \Toast\Flavours\Models\ElementItem}.
 *
 * Exists so every list of items in every element behaves the same way, and
 * because both of the changes below are easy to leave out and each produces a
 * bug that looks like something else:
 *
 *  - without orderable rows the Sort column is never written, and the items
 *    come back in id order however the author drags them;
 *  - the default delete action unlinks rather than deletes, so rows an author
 *    thinks they removed stay in the table for ever.
 *
 * The versioned publish and unpublish actions on each row come from
 * `versioned_gridfield_extensions`, which ElementItem sets - silverstripe/
 * versioned reads that config and swaps in VersionedGridFieldItemRequest by
 * itself, so there is nothing to wire up here.
 */
class ElementItemsConfig extends GridFieldConfig_RecordEditor
{
    public function __construct($itemsPerPage = null, string $sortField = 'Sort')
    {
        parent::__construct($itemsPerPage);

        $this->addComponent(GridFieldOrderableRows::create($sortField));

        $this->removeComponentsByType(GridFieldDeleteAction::class);
        $this->addComponent(new GridFieldDeleteAction(false));

        $this->extend('updateConfig');
    }
}
