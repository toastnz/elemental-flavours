<?php

namespace Toast\Flavours\Models;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Base for the repeated rows inside an element - accordion panels, gallery
 * images, logos, statistics.
 *
 * Subclasses declare their own `Parent` has_one back to the element:
 *
 *     private static $has_one = ['Parent' => AccordionElement::class];
 *
 * and the element declares the other half, which is the part that is easy to
 * forget and expensive to get wrong:
 *
 *     private static $has_many = ['Items' => AccordionItem::class];
 *     private static $owns = ['Items'];
 *     private static $cascade_deletes = ['Items'];
 *     private static $cascade_duplicates = ['Items'];
 *
 * Items are versioned like the element that owns them, so editing one does not
 * change the live site until the element is published. `$owns` is what makes
 * publishing the element publish its items too - without it an author
 * publishes a block and sees none of their changes.
 */
class ElementItem extends DataObject implements CMSPreviewable
{
    private static $table_name = 'Flavours_ElementItem';

    private static $singular_name = 'item';

    private static $plural_name = 'items';

    private static $db = [
        'Sort' => 'Int',
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $default_sort = 'Sort';

    private static $indexes = [
        'Sort' => true,
    ];

    private static $versioned_gridfield_extensions = true;

    /**
     * Turns the CMS preview on for a row.
     *
     * The navigator reads both of these off the record's own class before it
     * will offer a draft or a published link, and elemental sets them on the
     * block. Without them a row's edit form still says it is previewable and
     * still draws the view mode dropdowns - it just has no address to put in
     * the panel, so switching to split view shows an empty frame.
     */
    private static $show_stage_link = true;

    private static $show_live_link = true;

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            // Sort is managed by dragging the rows, and the parent is implied
            // by which element the author opened.
            $fields->removeByName(['Sort', 'ParentID', 'LinkTracking', 'FileTracking']);
        });

        return parent::getCMSFields();
    }

    /**
     * The element this item belongs to, or null on an item that has not been
     * attached yet.
     */
    public function getElement(): ?BaseElement
    {
        if (!$this->hasMethod('Parent')) {
            return null;
        }

        $parent = $this->Parent();

        return $parent instanceof BaseElement && $parent->exists() ? $parent : null;
    }

    /**
     * An id for this item that is stable and unique on the page, so a template
     * can link straight to it and the CMS preview can scroll to it.
     */
    public function getAnchor(): string
    {
        $element = $this->getElement();

        return ($element ? $element->getAnchor() : 'item') . '-' . $this->ID;
    }

    public function PreviewLink($action = null): ?string
    {
        $element = $this->getElement();

        if (!$element) {
            return null;
        }

        $link = $element->PreviewLink($action);

        if (!$link) {
            return null;
        }

        // The element's own anchor is on the end of that link; swap in this
        // item's so the preview lands on the right row.
        return preg_replace('/#.*$/', '', $link) . '#' . $this->getAnchor();
    }

    public function getCMSEditLink(): ?string
    {
        // There is no addressable URL for a row inside a nested grid, so the
        // closest honest answer is the element that holds it.
        return $this->getElement()?->getCMSEditLink();
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }

    /**
     * Permissions follow the element. An author who may edit the block may
     * edit its rows; one who may not, may not - and the check stays right
     * automatically when the element's own rules change.
     */
    public function canView($member = null)
    {
        return $this->elementCan(__FUNCTION__, $member);
    }

    public function canEdit($member = null)
    {
        return $this->elementCan(__FUNCTION__, $member);
    }

    public function canDelete($member = null)
    {
        return $this->elementCan(__FUNCTION__, $member);
    }

    public function canCreate($member = null, $context = [])
    {
        if ($element = $this->getElement()) {
            return $element->canCreate($member, $context);
        }

        // A row being created has no parent yet, so fall back to the element
        // class it will belong to.
        $elementClass = $this->getElementClass();

        return $elementClass
            ? singleton($elementClass)->canCreate($member, $context)
            : parent::canCreate($member, $context);
    }

    /**
     * The element class this item belongs to, read from the `Parent` has_one a
     * subclass declares.
     */
    public function getElementClass(): ?string
    {
        $hasOne = (array) static::config()->get('has_one');
        $class = $hasOne['Parent'] ?? null;

        return is_string($class) && is_a($class, BaseElement::class, true) ? $class : null;
    }

    private function elementCan(string $method, $member): bool
    {
        $element = $this->getElement();

        return $element ? (bool) $element->$method($member) : (bool) parent::$method($member);
    }
}
