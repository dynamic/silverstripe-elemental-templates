<?php

namespace Dynamic\ElementalTemplates\Service;

use Dynamic\ElementalTemplates\Models\Template;
use DNADesign\Elemental\Models\ElementalArea;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;

class TemplateElementDuplicator
{
    /**
     * Duplicate all elements from the given template into the provided ElementalArea.
     * Elements are appended to the end of any existing elements in the area.
     *
     * @param Template $template
     * @param ElementalArea $area
     * @return void
     */
    public function duplicateElements(Template $template, ElementalArea $area): void
    {
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Get the current maximum Sort value from existing elements in the target area
        $existingElements = $area->Elements();
        $maxSort = 0;
        if ($existingElements->count() > 0) {
            $maxSort = (int) $existingElements->max('Sort');
        }

        // Track the sort order for new elements starting after existing ones
        $sortOrder = $maxSort;

        // Loop over the template's inner elements in their current order.
        // The caller (TemplateApplicator) already validates the area exists, but we guard
        // here too so PHPStan can verify the access is safe.
        $templateArea = $template->Elements();
        if (!$templateArea->exists()) {
            return;
        }
        foreach ($templateArea->Elements()->sort('Sort') as $element) {
            try {
                $copy = $element->duplicate();

                // set skip populate flag to true to prevent populateElementData() from being called
                if ($copy->hasMethod('setSkipPopulateData')) {
                    $copy->setSkipPopulateData(true);
                }

                // set AvailableGlobally to default
                $copy->setResetAvailableGlobally(true);

                // Set the Sort order to append after existing elements
                $sortOrder++;
                $copy->Sort = $sortOrder;

                // Set the parent to the target area
                $copy->ParentID = $area->ID;

                $copy->write();

                // Write to draft stage if versioned.
                if ($copy->hasExtension(Versioned::class)) {
                    $copy->writeToStage(Versioned::DRAFT);
                }

                // Add the duplicated element to the target area
                $area->Elements()->add($copy);
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
