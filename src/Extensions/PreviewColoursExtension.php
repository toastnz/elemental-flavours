<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Puts the live colour preview's receiver on the page, but only when the page
 * is being previewed.
 *
 * The CMS asks for its preview with ?CMSPreview=1, which is the only time this
 * script has anything to do - so a real visitor never downloads it. The script
 * checks that it is in a frame itself, which covers a preview address opened
 * in a tab of its own.
 *
 * Only registered when toastnz/colourpalettes is installed - see
 * _config/colourpalettes.yml.
 *
 * @extends Extension<ContentController>
 */
class PreviewColoursExtension extends Extension
{
    protected function onAfterInit(): void
    {
        $request = $this->owner->getRequest();

        if (!$request || $request->getVar('CMSPreview') === null) {
            return;
        }

        Requirements::javascript('toastnz/elemental-flavours: client/dist/js/preview-colours.js');
    }
}
