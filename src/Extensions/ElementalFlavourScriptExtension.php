<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Puts the <elemental-flavour> custom element on every front-end page.
 *
 * Every page rather than only pages with blocks, because the element is not
 * only a block holder: a theme can use it by hand for any band of the page
 * that should collapse its spacing against the blocks next to it, and that
 * needs the script whether or not the page has blocks. Deferred, so it never
 * holds up rendering - until it runs the classes are simply not there yet.
 *
 * Turn it off to load the script some other way:
 *
 *     Toast\Flavours\Extensions\ElementalFlavourScriptExtension:
 *       require_script: false
 *
 * @extends Extension<ContentController>
 */
class ElementalFlavourScriptExtension extends Extension
{
    private static bool $require_script = true;

    protected function onAfterInit(): void
    {
        // Config::inst() rather than static::config(): an Extension in
        // Silverstripe 6 is not Configurable.
        if (!Config::inst()->get(static::class, 'require_script')) {
            return;
        }

        Requirements::javascript(
            'toastnz/elemental-flavours: client/dist/js/elemental-flavour.js',
            ['defer' => true]
        );
    }
}
