<?php

namespace Dynamic\ElementalTemplates\Tasks;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Deprecation;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @deprecated 3.2.0 Will be removed without equivalent functionality to replace it in a future major release.
 *             Use BaseElementDataExtension.fixtures instead.
 */
class SkeletonElementsPopulateTask extends BuildTask
{
    private static string $segment = 'SkeletonElementsPopulateTask';

    protected string $title = 'Populate Template Elements';

    protected static string $description = 'Populate the template elements with some default content';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // SCOPE_GLOBAL is deliberate: this task is dispatched through supported-module code (framework's
        // BuildTask/PolyCommand run the execute() hook), so a method-scoped notice would be attributed to a
        // supported-module caller and hidden by default. Global scope keeps it visible to the installing project.
        Deprecation::notice(
            '3.2.0',
            'SkeletonElementsPopulateTask is deprecated. Will be removed without equivalent functionality to '
            . 'replace it in a future major release. Use BaseElementDataExtension.fixtures instead.',
            Deprecation::SCOPE_GLOBAL
        );

        $populate = Template::config()->get('populate') ?? [];

        foreach (Template::get() as $skeleton) {
            $area = $skeleton->Elements();
            foreach ($area->Elements() as $element) {
                $output->writeln("Populating content for {$element->ClassName} with ID {$element->ID}");
                if (array_key_exists($element->ClassName, $populate)) {
                    foreach ($populate[$element->ClassName] as $field => $value) {
                        $element->$field = $value;
                    }

                    $element->write();
                    $element->writeToStage(Versioned::DRAFT);
                }
            }
        }

        return Command::SUCCESS;
    }
}
