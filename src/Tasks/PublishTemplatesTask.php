<?php

namespace Dynamic\ElementalTemplates\Tasks;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class PublishTemplatesTask extends BuildTask
{
    private static $segment = 'PublishTemplatesTask';

    protected string $title = 'Publish All Templates';

    protected static string $description = 'Loops through all Template records and publishes them.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $templates = Template::get();

        foreach ($templates as $template) {
            if ($template->isArchived()) {
                $template->writeToStage(Versioned::DRAFT);
                $template->publishRecursive();
                $output->writeln("Published Template: {$template->Title} (ID: {$template->ID})");
            } else {
                $output->writeln("Template already published: {$template->Title} (ID: {$template->ID})");
            }
        }

        $output->writeln('Task completed.');

        return Command::SUCCESS;
    }
}
