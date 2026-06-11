<?php

namespace Dynamic\ElementalTemplates\Tasks;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class SkeletonElementsPopulateTask extends BuildTask
{
    private static string $segment = 'SkeletonElementsPopulateTask';

    protected string $title = 'Populate Template Elements';

    protected static string $description = 'Populate the template elements with some default content';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
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
