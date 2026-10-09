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
 *             The BaseElementDataExtension.fixtures config only populates elements newly created inside a
 *             Template and never rewrites existing ones, so it is not an equivalent replacement for this task.
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
        // The notice uses the standardised no-replacement wording verbatim (the message default of
        // Deprecation::noticeWithNoReplacment()) but is raised through notice() directly: that wrapper runs
        // inside withSuppressedNotice(), and outputNotices() drops a notice recorded that way unless the host
        // project called Deprecation::enable(true) -- so the wrapper would hide the notice from exactly the
        // projects that never touched the deprecation settings. Naming BaseElementDataExtension.fixtures as a replacement
        // here would contradict it: that config only populates newly created elements, so there is no
        // equivalent replacement for this task. See the class docblock for the explanation.
        Deprecation::notice(
            '3.2.0',
            'SkeletonElementsPopulateTask is deprecated. Will be removed without equivalent functionality to '
            . 'replace it in a future major release.',
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
