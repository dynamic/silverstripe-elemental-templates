<?php

namespace Dynamic\ElememtalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\ElememtalTemplates\Service\TemplateApplicator;

/**
 * Class \Dynamic\ElememtalTemplates\Extension\CMSPageAddControllerExtension
 *
 * @property \SilverStripe\CMS\Controllers\CMSPageAddController|\Dynamic\ElememtalTemplates\Extension\CMSPageAddControllerExtension $owner
 */
class CMSPageAddControllerExtension extends Extension
{
    /**
     * Update page options to include a template selection.
     *
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
     * Hook into the record creation process to apply a template.
     *
     * @param DataObject $record
     * @param Form       $form
     * @return void
     * @throws ValidationException
     */
    public function updateDoAdd(DataObject $record, Form $form): void
    {
        if (!$record->hasExtension(ElementalAreasExtension::class)) {
            return;
        }

        $record->write();

        $templateID = (int)$form->Fields()->dataFieldByName('TemplateID')->Value();
        if (!$templateID || !$template = Template::get()->byID($templateID)) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                "Invalid or missing template ID: {$templateID}."
            );
            return;
        }

        /** @var TemplateApplicator $applicator */
        $applicator = Injector::inst()->get(TemplateApplicator::class);
        $result = $applicator->applyTemplateToRecord($record, $template);

        if (!$result['success']) {
            Injector::inst()->get(LoggerInterface::class)->error($result['message']);
        }
    }

    /**
     * (Optional) Find or create the ElementalArea for the given record.
     * This method may be shared with other parts of the module if needed.
     *
     * @param DataObject $record
     * @return \DNADesign\Elemental\Models\ElementalArea|null
     */
    protected function findOrCreateElementalArea(DataObject $record): ?\DNADesign\Elemental\Models\ElementalArea
    {
        $elementalAreaRelations = $record->getElementalRelations();

        foreach ($elementalAreaRelations as $relationName) {
            Injector::inst()->get(LoggerInterface::class)->info("Checking ElementalArea relation: {$relationName}");
            $cmsFields = $record->getCMSFields();
            if ($cmsFields->dataFieldByName("{$relationName}")) {
                Injector::inst()->get(LoggerInterface::class)->info("Found valid ElementalArea relation: {$relationName}");
                $elementalArea = $record->getComponent($relationName);
                if (!$elementalArea || !$elementalArea->exists()) {
                    Injector::inst()->get(LoggerInterface::class)->info("Creating new ElementalArea for relation: {$relationName}");
                    $elementalArea = \DNADesign\Elemental\Models\ElementalArea::create();
                    $elementalArea->write();
                    $record->setField("{$relationName}ID", $elementalArea->ID);
                    $record->write();
                }
                return $elementalArea;
            } else {
                Injector::inst()->get(LoggerInterface::class)->info("Skipping relation: {$relationName} as it is not visible in getCMSFields()");
            }
        }
        Injector::inst()->get(LoggerInterface::class)->warning("No valid ElementalArea relation found for record ID {$record->ID}.");
        return null;
    }
}
