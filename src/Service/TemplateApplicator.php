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
     * @return array Result of the operation with success status and messages.
     */
    public function applyTemplateToRecord(DataObject $record, Template $template): array
    {
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Validate that the template exists.
        if (!$template->exists()) {
            $message = "Template with ID {$template->ID} does not exist.";
            $logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        // Validate that the template exists and has an ElementalArea.
        $templateArea = $template->Elements();
        if (!$templateArea || !$templateArea->exists()) {
            $message = "Template with ID {$template->ID} does not have an elemental area.";
            $logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        // Ensure the record supports elemental areas.
        if (!$record->hasMethod('ElementalArea')) {
            $message = "Record ID {$record->ID} does not support elemental areas.";
            $logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        $elementalArea = $record->ElementalArea();
        if (!$elementalArea || !$elementalArea->exists()) {
            $message = "Record ID {$record->ID} does not have an elemental area.";
            $logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        // Use the TemplateElementDuplicator service to duplicate elements.
        /** @var TemplateElementDuplicator $duplicator */
        $duplicator = Injector::inst()->get(TemplateElementDuplicator::class);
        $duplicator->duplicateElements($template, $elementalArea);

        $record->write();
        $message = "Template (ID: {$template->ID}) applied to record ID {$record->ID} successfully.";
        $logger->info($message);
        return ['success' => true, 'message' => $message];
    }
}
