<?php

namespace Dynamic\ElememtalTemplates\Service;

use Dynamic\ElememtalTemplates\Models\Template;
use DNADesign\Elemental\Models\ElementalArea;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;

class TemplateElementDuplicator
{
    /**
     * Duplicate all elements from the given template into the provided ElementalArea.
     *
     * @param Template $template
     * @param ElementalArea $area
     * @return void
     */
    public function duplicateElements(Template $template, ElementalArea $area): void
    {
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Loop over the template's inner elements.
        foreach ($template->Elements()->Elements() as $element) {
            try {
                $copy = $element->duplicate();

                $logger->debug(sprintf(
                    "Duplicating element (ID: %d) to new element (ID: %d).",
                    $element->ID,
                    $copy->ID
                ));
                // set skip populate flag to true to prevent populateElementData() from being called
                if ($copy->hasMethod('setSkipPopulateData')) {
                    $copy->setSkipPopulateData(true);
                }

                // set AvailableGlobally to default
                //if ($copy->hasMethod('setResetAvailableGlobally')) {
                    $copy->setResetAvailableGlobally(true);
                //}

                $copy->write();

                // Write to draft stage if versioned.
                if ($copy->hasExtension(Versioned::class)) {
                    $copy->writeToStage(Versioned::DRAFT);
                }

                // Add the duplicated element to the target area.
                $area->Elements()->add($copy);

                $logger->debug(sprintf(
                    "Duplicated element (ID: %d) to new element (ID: %d).",
                    $element->ID,
                    $copy->ID
                ));
            } catch (\Exception $ex) {
                $logger->error(sprintf(
                    "Error duplicating element (ID: %d): %s",
                    $element->ID,
                    $ex->getMessage()
                ));
            }
        }
    }
}
