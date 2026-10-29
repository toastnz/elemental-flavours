<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\Core\Extension;

/**
 * Says what kind of block each row in the CMS list is.
 *
 * An author looking at a list of blocks sees the title they gave it and the
 * first line of its content, which between them often don't say whether they
 * are looking at a text block, an image block or a slider - and the only thing
 * on screen that does is the icon, which has to be learnt before it helps.
 *
 * Status flags are Silverstripe's own way of putting a small label beside a
 * record's title, and elemental renders them next to the block's name already,
 * so this needs no javascript and no template: a line of config turns it on,
 * and removing the extension turns it off.
 *
 * The type goes in front of the versioning flags so a row reads as what it is
 * before it reads as how it stands - "Text Block", then "Draft".
 */
class ElementTypeFlagExtension extends Extension
{
    protected function updateStatusFlags(array &$flags): void
    {
        $flags = [
            'element-type' => [
                'text' => $this->owner->getType(),
                'title' => $this->owner->i18n_classDescription() ?: '',
            ],
        ] + $flags;
    }
}
