<?php

namespace Toast\Flavours\Extensions;

use Toast\Flavours\Elements\PageContentElement;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Versioned\Versioned;

/**
 * Lets a page type position its own content among its blocks.
 *
 * This is what decides which of two ways a page is put together.
 *
 * Without it, a page renders the way any Silverstripe page does - its own Layout
 * template - and that template says where its blocks go by rendering
 * $ElementalArea itself. The developer decides where the blocks sit.
 *
 * With it, the page's template becomes one block in the run: the block area is
 * rendered instead of the template, and this block renders the template from
 * wherever the author has dragged it. The author decides where the content
 * sits, relative to the blocks.
 *
 * So apply it per page type, to the ones whose own content should be movable:
 *
 *     Toast\Pages\HomePage:
 *       extensions:
 *         - Toast\Flavours\Extensions\PageContentExtension
 *
 * A page type that has it must not render $ElementalArea in its own template.
 * That template is rendered from inside the area, so it would be asking the
 * area to render itself.
 *
 * Every page of that type gets one {@link PageContentElement}, kept in step
 * from here on save and reconciled for the whole site on dev/build. Removing
 * the extension and running dev/build takes the blocks away again.
 *
 * The page must already have an elemental area - normally through elemental's
 * own ElementalPageExtension.
 *
 * @extends Extension<SiteTree>
 */
class PageContentExtension extends Extension
{
    /**
     * Which elemental area holds the page content block, for a page type with
     * more than one.
     */
    private static string $page_content_area = 'ElementalArea';

    /**
     * Whether this page's own template is rendered from inside the block run.
     *
     * The templates ask this to decide which way round to put the page
     * together. A page type without this extension has no such method, and an
     * unknown variable in a template is simply empty - which is the right
     * answer for it.
     */
    public function HasPageContentBlock(): bool
    {
        return PageContentElement::findOnPage($this->owner) !== null;
    }

    protected function onAfterWrite(): void
    {
        // Only ever alter the draft stage. Writing while reading Live - during
        // a publish, for instance - would create a second block against the
        // live record.
        if (Versioned::get_stage() !== Versioned::DRAFT) {
            return;
        }

        PageContentElement::ensureOnPage($this->owner);
    }
}
