<?php

namespace Dynamic\ElememtalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElememtalTemplates\Models\Template;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;

/**
 * Class \Dynamic\ElememtalTemplates\Extension\CMSPageAddControllerExtension
 *
 * @property \SilverStripe\CMS\Controllers\CMSPageAddController|\Dynamic\ElememtalTemplates\Extension\CMSPageAddControllerExtension $owner
 */
class CMSPageAddControllerExtension extends Extension
{
    /**
     * @param FieldList $fields
     * @return void
     */
    public function updatePageOptions(FieldList $fields): void
    {
        $templates = ['' => 'Select template'] + Template::get()->map('ID', 'Title')->toArray();

        $title = '<span class="step-label"><span class="flyout">Step 3. </span><span class="title">(Optional) Select template to create page with</span></span>';
        $templateField = new DropdownField('TemplateID', DBField::create_field('HTMLFragment', $title), $templates);
        $fields->insertAfter('PageType', $templateField);
    }

    /**
     * @param DataObject $record
     * @param Form $form
     * @return void
     * @throws ValidationException
     */
    public function updateDoAdd(DataObject $record, Form $form): void
    {
        // Ensure the newly created record has the elemental extension
        if (!$record->hasExtension(ElementalAreasExtension::class)) {
            return;
        }

        $record->write();

        // Validate the template
        $templateID = (int)$form->Fields()->dataFieldByName('TemplateID')->Value();
        if (!$templateID || !$template = Template::get()->byID($templateID)) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                "Invalid or missing template ID: {$templateID}."
            );
            return;
        }

        // Find or create the ElementalArea
        $elementalArea = $this->findOrCreateElementalArea($record);
        if (!$elementalArea) {
            return;
        }

        // Add elements to the ElementalArea
        try {
            foreach ($template->Elements()->Elements() as $element) {
                $copy = $element->duplicate();
                $copy->setSkipPopulateData(true);
                $copy->write();
                $copy->writeToStage(Versioned::DRAFT);
                $elementalArea->Elements()->add($copy);
            }

            Injector::inst()->get(LoggerInterface::class)->info(
                "Successfully added elements from template ID {$templateID} to ElementalArea for record ID {$record->ID}."
            );
        } catch (\Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                "Error while duplicating elements for template ID {$templateID}: " . $e->getMessage()
            );
        }
    }

    /**
     * Find or create the ElementalArea for the given record.
     *
     * @param DataObject $record
     * @return ElementalArea|null
     */
    protected function findOrCreateElementalArea(DataObject $record): ?ElementalArea
    {
        // Get all ElementalArea relations using getElementalRelations()
        $elementalAreaRelations = $record->getElementalRelations();

        foreach ($elementalAreaRelations as $relationName) {
            Injector::inst()->get(LoggerInterface::class)->info(
                "Checking ElementalArea relation: {$relationName}"
            );

            // Check if the field exists in getCMSFields()
            $cmsFields = $record->getCMSFields();
            if ($cmsFields->dataFieldByName("{$relationName}")) {
                Injector::inst()->get(LoggerInterface::class)->info(
                    "Found valid ElementalArea relation: {$relationName}"
                );

                // Retrieve or create the ElementalArea
                $elementalArea = $record->getComponent($relationName);
                if (!$elementalArea || !$elementalArea->exists()) {
                    Injector::inst()->get(LoggerInterface::class)->info(
                        "Creating new ElementalArea for relation: {$relationName}"
                    );

                    $elementalArea = ElementalArea::create();
                    $elementalArea->write();
                    $record->setField("{$relationName}ID", $elementalArea->ID);
                    $record->write();
                }

                return $elementalArea;
            } else {
                Injector::inst()->get(LoggerInterface::class)->info(
                    "Skipping relation: {$relationName} as it is not visible in getCMSFields()"
                );
            }
        }

        Injector::inst()->get(LoggerInterface::class)->warning(
            "No valid ElementalArea relation found for record ID {$record->ID}."
        );

        return null;
    }
}
