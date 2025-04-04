<?php

namespace Dynamic\ElememtalTemplates\Service;

use Dynamic\ElememtalTemplates\Models\Template;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElememtalTemplates\Service\TemplateElementDuplicator;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

class TemplateApplicator
{
    /**
     * Applies the given template to the provided record.
     *
     * @param DataObject $record
     * @param Template   $template
     * @return bool True on success, false otherwise.
     */
    public function applyTemplateToRecord(DataObject $record, Template $template): bool
    {
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Validate that the template has an ElementalArea.
        $templateArea = $template->Elements();
        if (!$templateArea || !$templateArea->exists()) {
            $logger->error("Template with ID {$template->ID} does not have an elemental area.");
            return false;
        }

        // Ensure the record supports elemental areas.
        if (!$record->hasMethod('ElementalArea')) {
            $logger->error("Record ID {$record->ID} does not support elemental areas.");
            return false;
        }

        $elementalArea = $record->ElementalArea();
        if (!$elementalArea || !$elementalArea->exists()) {
            $logger->error("Record ID {$record->ID} does not have an elemental area.");
            return false;
        }

        // Use the TemplateElementDuplicator service to duplicate elements.
        /** @var TemplateElementDuplicator $duplicator */
        $duplicator = Injector::inst()->get(TemplateElementDuplicator::class);
        $duplicator->duplicateElements($template, $elementalArea);

        $record->write();
        $logger->info("Template (ID: {$template->ID}) applied to record ID {$record->ID} successfully.");
        return true;
    }
}
