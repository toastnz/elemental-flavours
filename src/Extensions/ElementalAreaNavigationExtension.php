<?php

namespace Toast\Flavours\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPaginator;

/**
 * Puts previous and next arrows on a block's edit form.
 *
 * Silverstripe already builds them for any grid whose detail form is told to
 * show pagination - it is the same pair an author gets on the rows inside a
 * block - but elemental switches pagination off and adds no paginator, so its
 * blocks are the one list in the CMS you can only leave by going back.
 *
 * That matters more here than it looks: with blocks opening on their own screen
 * rather than unfolding in the list, working through a page's blocks in order
 * otherwise means a return trip to the list between each one.
 *
 * The page size is deliberately larger than any real page's worth of blocks.
 * The arrows do cross a page boundary if they have to, but there is no list to
 * paginate on screen - the block editor is React and fetches its own - so a
 * second page would exist only to be stepped over.
 */
class ElementalAreaNavigationExtension extends Extension
{
    private static int $blocks_per_page = 250;

    protected function updateConfig(): void
    {
        $config = $this->owner;

        if ($config->getComponentByType(GridFieldPaginator::class)) {
            return;
        }

        // Config::inst() rather than static::config(): an Extension in
        // Silverstripe 6 is not Configurable, so it has no config() of its own.
        $perPage = (int) Config::inst()->get(static::class, 'blocks_per_page');

        $config->addComponent(GridFieldPaginator::create($perPage));
        $config->getComponentByType(GridFieldDetailForm::class)?->setShowPagination(true);
    }
}
