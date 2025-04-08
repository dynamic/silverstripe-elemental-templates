<?php

namespace Dynamic\ElementalTemplates\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use Dynamic\ElememtalTemplates\Models\Template;

class PublishTemplatesTask extends BuildTask
{
    protected $title = 'Publish All Templates';

    protected $description = 'Loops through all Template records and publishes them.';

    public function run($request)
    {
        $templates = Template::get();

        foreach ($templates as $template) {
            if ($template->isArchived()) {
                $template->writeToStage(Versioned::DRAFT);
                $template->publishRecursive();
                echo "Published Template: {$template->Title} (ID: {$template->ID})\n";
            } else {
                echo "Template already published: {$template->Title} (ID: {$template->ID})\n";
            }
        }

        echo "Task completed.\n";
    }
}
