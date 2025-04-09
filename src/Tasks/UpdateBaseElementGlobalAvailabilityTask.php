<?php

namespace Dynamic\ElementalTemplates\Tasks;

use SilverStripe\Dev\BuildTask;
use DNADesign\Elemental\Models\BaseElement;
use Dynamic\ElememtalTemplates\Models\Template;


class UpdateBaseElementGlobalAvailabilityTask extends BuildTask
{
    protected $title = 'Update BaseElement Global Availability';

    protected $description = 'Sets AvailableGlobally to false for BaseElements associated with Templates.';

    private static $segment = 'UpdateBaseElementGlobalAvailabilityTask';

    public function run($request)
    {
        $elements = BaseElement::get();

        foreach ($elements as $element) {
            $manager = $element->getOwner()->getPage();

            if ($manager instanceof Template) {
                $element->AvailableGlobally = false;
                $element->write();
                echo "Updated AvailableGlobally for BaseElement ID: {$element->ID}\n";
            } else {
                echo "No action taken for BaseElement ID: {$element->ID} as it is not associated with a Template.\n";
            }
        }

        echo "Task completed.\n";
    }
}