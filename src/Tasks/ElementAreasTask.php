<?php

namespace Toast\Flavours\Tasks;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Gives a block area to records that were meant to have one and don't.
 *
 * Elemental creates a record's block area when the record is written, and only
 * when the write is happening on the draft stage. Two things follow from that.
 * A page that already existed when the elemental extension was applied to it
 * has no area until somebody opens and saves it, and nothing that runs from the
 * command line - dev/build, a task, an import - sets a stage by default, so it
 * cannot create one either.
 *
 * The symptom is not obvious. The CMS asks for the blocks of area 0, gets a
 * 404, and tells the author they were "trying to access a section or object
 * that doesn't exist", which sounds like a permissions problem and isn't.
 *
 * The fix is a write on the draft stage: elemental does the rest itself, and
 * the page content block is added by its own reconcile in the same pass.
 */
class ElementAreasTask extends BuildTask
{
    protected string $title = 'Flavours: repair missing block areas';

    protected static string $commandName = 'flavours-element-areas';

    protected static string $description = 'Finds records whose block area was never created and creates it. Reports only, unless run with --commit.';

    public function getOptions(): array
    {
        return [
            new InputOption('commit', null, InputOption::VALUE_NONE, 'Write the areas. Without this the task only reports.'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $commit = (bool) $input->getOption('commit');

        // Nothing below works without this: elemental refuses to touch a block
        // area unless the current stage is draft, and a command line request
        // has no stage of its own.
        Versioned::set_stage(Versioned::DRAFT);

        $missing = 0;
        $repaired = 0;

        foreach (ClassInfo::classesWithExtension(ElementalAreasExtension::class) as $class) {
            // A subclass comes back from its parent's query too, so only the
            // topmost class carrying the extension is asked.
            if ($this->hasElementalParent($class)) {
                continue;
            }

            foreach (DataObject::get($class) as $record) {
                foreach ((array) $record->getElementalRelations() as $relation) {
                    $field = $relation . 'ID';

                    if ($record->$field) {
                        continue;
                    }

                    $missing++;
                    $output->writeln(sprintf(
                        '  %s #%d "%s" has no %s',
                        $record->ClassName,
                        $record->ID,
                        $record->getTitle(),
                        $relation
                    ));

                    if (!$commit) {
                        continue;
                    }

                    $record->write();
                    $repaired++;

                    $output->writeln(sprintf('    created %s #%d', $relation, $record->$field));
                }
            }
        }

        if (!$missing) {
            $output->writeln('Every record has its block area.');

            return 0;
        }

        $output->writeln('');
        $output->writeln($commit
            ? sprintf('Repaired %d of %d.', $repaired, $missing)
            : sprintf('%d to repair. Run again with --commit to write them.', $missing));

        return 0;
    }

    /**
     * True when an ancestor of this class also has the extension, so the record
     * is already covered by the ancestor's query.
     */
    private function hasElementalParent(string $class): bool
    {
        foreach (ClassInfo::ancestry($class) as $ancestor) {
            if ($ancestor !== $class && DataObject::has_extension($ancestor, ElementalAreasExtension::class)) {
                return true;
            }
        }

        return false;
    }
}
