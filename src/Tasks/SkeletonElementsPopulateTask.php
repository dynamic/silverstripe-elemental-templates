<?php

namespace DNADesign\ElementalSkeletons\Tasks;

use DNADesign\ElementalSkeletons\Models\Skeleton;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;

class SkeletonElementsPopulateTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Populate Skeleton Elements';

    /**
     * @var string
     */
    protected $description = 'Populate the skeleton elements with some default content';

    /**
     * @var string
     */
    private static string $segment = 'SkeletonElementsPopulateTask';

    /**
     * @param $request
     * @return void
     */
    public function run($request): void
    {
        $populate = Skeleton::config()->get('populate');

        foreach (Skeleton::get() as $skeleton) {
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
