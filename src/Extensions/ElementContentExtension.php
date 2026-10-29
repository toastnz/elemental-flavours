<?php

namespace Toast\Flavours\Extensions;

use DNADesign\Elemental\Forms\EditFormFactory;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;

/**
 * Gives every element a heading and a body, the two things nearly every block
 * turns out to need.
 *
 * Elemental's own Title doubles as both the CMS label for a block and an
 * optional on-page heading, which leaves an author guessing which one they are
 * editing. Here Title is only ever the CMS label - `displays_title_in_template`
 * is turned off in this module's config - and Heading is the one that shows on
 * the page.
 *
 * @extends Extension<BaseElement>
 */
class ElementContentExtension extends Extension
{
    private static array $db = [
        'Heading' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        // The scaffolder appends extension fields after the element's own, so
        // a block with its own fields would otherwise show Heading and Content
        // below them - after the things they are meant to introduce.
        $fields->removeByName(['Heading', 'Content']);

        $heading = TextField::create('Heading', _t(__CLASS__ . '.HEADING', 'Heading'))
            ->setDescription(_t(
                __CLASS__ . '.HEADING_DESCRIPTION',
                'The heading shown at the top of this block on the page. Leave it empty for no heading.'
            ));

        $content = HTMLEditorField::create('Content', _t(__CLASS__ . '.CONTENT', 'Content'))
            ->setRows((int) EditFormFactory::config()->get('html_field_rows'));

        // insertAfter returns false when Title is absent, which happens on an
        // element that has removed it. Testing the first insert decides for
        // both, so a half-failure cannot leave one field added twice.
        if (!$fields->insertAfter('Title', $content)) {
            $fields->addFieldsToTab('Root.Main', [$heading, $content]);

            return;
        }

        $fields->insertAfter('Title', $heading);
    }

    /**
     * Show the heading under the block's name in the CMS element list.
     *
     * BaseElement::getSummary() returns an empty string and has no extension
     * hook, so the summary is set on the schema the editor is given instead.
     * An element that implements getSummary() itself still wins.
     */
    protected function updateBlockSchema(array &$blockSchema): void
    {
        if (empty($blockSchema['content']) && $this->owner->Heading) {
            $blockSchema['content'] = $this->owner->Heading;
        }
    }
}
