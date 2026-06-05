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

        // Loop over the template's inner elements in their current order
        foreach ($template->Elements()->Elements()->sort('Sort') as $element) {
        // Check if template has elements before accessing
        if (!$template->Elements()->exists()) {
            return;
        }

        // Loop over the template's inner elements.
        foreach ($template->Elements()->Elements() as $element) {
                // set AvailableGlobally to default
                $copy->setResetAvailableGlobally(true);

                // Set the Sort order to append after existing elements
                $sortOrder++;
                $copy->Sort = $sortOrder;

                // Set the parent to the target area
                $copy->ParentID = $area->ID;
