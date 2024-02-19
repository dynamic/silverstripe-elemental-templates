<?php

namespace Dynamic\ElememtalTemplates\Tasks;

use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;

class TemplateElementsPopulateTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Populate Template Elements';

    /**
     * @var string
     */
    protected $description = 'Populate the template elements with some default content';

    /**
     * @var string
     */
    private static string $segment = 'TemplateElementsPopulateTask';

    /**
     * @param $request
     * @return void
     */
    public function run($request): void
    {
        $populate = Template::config()->get('populate');

        foreach (Template::get() as $skeleton) {
            $area = $skeleton->Elements();
            foreach ($area->Elements() as $element) {
                echo "Populating content for {$element->ClassName} with ID {$element->ID}" . PHP_EOL;
                if (array_key_exists($element->ClassName, $populate)) {
                    foreach ($populate[$element->ClassName] as $field => $value) {
                        $element->$field = $value;
                    }

                    $element->write();
                    $element->writeToStage(Versioned::DRAFT);
                }
            }
        }
    }
}
