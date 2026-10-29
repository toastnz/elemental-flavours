<?php

namespace Toast\Flavours\Tasks;

use DNADesign\Elemental\Models\BaseElement;
use Toast\Flavours\Layouts\LayoutRegistry;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\View\SSViewer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Lists the layouts every element offers, and where each one came from.
 *
 * Layouts are found by looking for template files, which means the usual
 * question when one does not appear is whether the file is where the module is
 * looking. This answers that directly rather than by guesswork: it prints the
 * themes being searched and, for each element, the layouts that survived
 * discovery and config.
 *
 * Run with --flush. The layout list is cached, so without it this reports what
 * the CMS is currently showing rather than what is on disk - which is the right
 * answer to a different question.
 */
class LayoutsTask extends BuildTask
{
    protected static string $commandName = 'flavours-layouts';

    protected string $title = 'Flavours: list element layouts';

    protected static string $description = 'Lists the layouts available to each elemental block, for checking that a template has been picked up.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $registry = LayoutRegistry::singleton();

        $output->writeln('Themes searched, in order: ' . implode(', ', SSViewer::get_themes()));
        $output->writeln('');

        $classes = ClassInfo::subclassesFor(BaseElement::class, false);
        sort($classes);

        foreach ($classes as $class) {
            $layouts = $registry->getLayouts($class);

            if (!$layouts) {
                $output->writeln("$class: no layouts");

                continue;
            }

            $default = $registry->getDefaultLayout($class);
            $output->writeln("<options=bold>$class</>");

            foreach ($layouts as $name => $layout) {
                $icon = match (true) {
                    $layout->getIcon() !== null => 'svg',
                    $layout->getIconClass() !== null => $layout->getIconClass(),
                    default => 'no icon',
                };

                $marker = $name === $default ? '*' : ' ';

                $output->writeln(sprintf('  %s %-20s %-24s %s', $marker, $name, $layout->getLabel(), $icon));
            }

            $output->writeln('');
        }

        $output->writeln('* marks the layout used when none is chosen, or when a saved one has been removed.');

        return Command::SUCCESS;
    }
}
