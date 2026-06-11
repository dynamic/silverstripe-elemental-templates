<?php

namespace Dynamic\ElementalTemplates\Tasks;

use DNADesign\Elemental\Models\BaseElement;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class UpdateBaseElementGlobalAvailabilityTask extends BuildTask
{
    private static string $segment = 'UpdateBaseElementGlobalAvailabilityTask';

    protected string $title = 'Update BaseElement Global Availability';

    protected static string $description = 'Sets AvailableGlobally to false for BaseElements associated with Templates.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $elements = BaseElement::get();

        foreach ($elements as $element) {
            $manager = $element->getPage();

            if ($manager instanceof Template && $element->hasField('AvailableGlobally')) {
                $element->setField('AvailableGlobally', false);
                $element->write();
                $output->writeln("Updated AvailableGlobally for BaseElement ID: {$element->ID}");
            } else {
                $output->writeln(
                    "No action taken for BaseElement ID: {$element->ID} as it is not associated with a Template."
                );
            }
        }

        $output->writeln('Task completed.');

        return Command::SUCCESS;
    }
}
