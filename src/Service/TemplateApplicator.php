<?php

namespace Dynamic\ElementalTemplates\Service;

use Dynamic\ElementalTemplates\Models\Template;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Service\TemplateElementDuplicator;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

class TemplateApplicator
{
    /**
     * Resolve the name of the elemental area relation the record actually exposes in the CMS.
     *
     * Mirrors the lookup the (now legacy) CMSPageAddControllerExtension::findOrCreateElementalArea()
     * performed: walk the record's elemental area relations and return the first one that has a
     * matching field in getCMSFields() - that is the area a member of content authors edits.
     *
     * When no relation is visible, the first relation the record actually declares is used: a record
     * that is not in the database yet never has an area field (ElementalAreasExtension::updateCMSFields()
     * only builds one once isInDb() is true), so 'nothing is visible' says nothing about which relation
     * the record has. The conventional 'ElementalArea' name is the last resort, for a record that
     * exposes no elemental relation at all.
     *
     * @param DataObject $record The record whose active elemental area should be used.
     * @return string The relation name, e.g. 'ElementalArea' or 'ElementalHomePage'.
     */
    public function resolveAreaRelationName(DataObject $record): string
    {
        if (!$record->hasMethod('getElementalRelations')) {
            return 'ElementalArea';
        }

        $elementalAreaRelations = $record->getElementalRelations();
        if (empty($elementalAreaRelations)) {
            return 'ElementalArea';
        }

        $cmsFields = $record->getCMSFields();
        foreach ($elementalAreaRelations as $relationName) {
            if ($cmsFields->dataFieldByName($relationName)) {
                return $relationName;
            }
        }

        // Nothing is visible in the CMS. Prefer a relation this record really has over the
        // conventional name, which it may not have at all.
        return $elementalAreaRelations[0];
    }

    /**
     * Applies the given template to the provided record's active elemental area.
     *
     * The signature is deliberately two arguments: this class is resolved through the Injector, so a
     * project subclass may override this method, and adding a parameter would be a fatal for it.
     * Use applyTemplateToRelation() to name the target relation.
     *
     * @param DataObject $record
     * @param Template   $template
     * @return array Result of the operation with success status and messages.
     */
    public function applyTemplateToRecord(DataObject $record, Template $template): array
    {
        return $this->applyTemplateToRelation($record, $template);
    }

    /**
     * Applies the given template to one elemental area relation of the provided record.
     *
     * @param DataObject  $record       The record to apply the template to.
     * @param Template    $template     The template to apply.
     * @param string|null $relationName The elemental area relation to write into; it must be one
     *                                  of $record->getElementalRelations(). When null the
     *                                  applicator targets the record's active area as determined
     *                                  by resolveAreaRelationName().
     * @return array Result of the operation with success status and messages.
     */
    public function applyTemplateToRelation(
        DataObject $record,
        Template $template,
        ?string $relationName = null
    ): array {
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

        // Resolve the target area relation when the caller did not name one explicitly.
        if ($relationName === null) {
            // A record that is not in the database yet has no area field for any of its relations
            // (ElementalAreasExtension::updateCMSFields() only builds one once isInDb() is true), so
            // there is nothing to choose between and the order of getElementalRelations() decides -
            // and that order puts inherited/extension relations first, which is the area this
            // feature exists to avoid. Write the record first, with the same guarded write the
            // materialization step below uses, then resolve against fields that really exist.
            if (!$record->isInDb()) {
                try {
                    $record->write();
                } catch (\Exception $e) {
                    $message = "Could not initialize elemental area for record ID {$record->ID}: {$e->getMessage()}";
                    $logger->error($message);
                    return ['success' => false, 'message' => $message];
                }
            }
            $relationName = $this->resolveAreaRelationName($record);
        } else {
            // An explicit name is called as a method below, so it must be one of the record's
            // elemental area relations, not just any method the record happens to have.
            $elementalRelations = $record->hasMethod('getElementalRelations')
                ? (array) $record->getElementalRelations()
                : [];
            if (!in_array($relationName, $elementalRelations, true)) {
                $message = "'{$relationName}' is not an elemental area relation of record ID {$record->ID}.";
                $logger->error($message);
                return ['success' => false, 'message' => $message];
            }
        }

        // Ensure the record supports elemental areas.
        if (!$record->hasMethod($relationName)) {
            $message = "Record ID {$record->ID} does not support elemental areas.";
            $logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        // Materialize the record's elemental area if it has not been created yet.
        // Newly added pages (or pages never saved with blocks) have ElementalAreaID = 0;
        // ElementalAreasExtension::onBeforeWrite() creates the area on write (only while
        // reading the DRAFT stage, which is the CMS edit context), so writing the record
        // first lets us apply the template in place instead of bailing out. The write is
        // guarded so a validation/hook failure keeps the method's no-throw contract
        // instead of escaping uncaught and leaving a half-applied record.
        $elementalArea = $record->$relationName();
        if (!$elementalArea || !$elementalArea->exists()) {
            try {
                $record->write();
            } catch (\Exception $e) {
                $message = "Could not initialize elemental area for record ID {$record->ID}: {$e->getMessage()}";
                $logger->error($message);
                return ['success' => false, 'message' => $message];
            }
            $elementalArea = $record->$relationName();
        }

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
