<?php

namespace Dynamic\ElementalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;

/**
 * Class \Dynamic\ElementalTemplates\Extension\CMSPageAddControllerExtension
 *
 * @property \SilverStripe\CMS\Controllers\CMSPageAddController|\Dynamic\ElementalTemplates\Extension\CMSPageAddControllerExtension $owner
 */
class CMSPageAddControllerExtension extends Extension
{
    /**
     * Inject the grouped template picker into the "Add new page" form.
     *
     * SS6 builds this form in CMSMainAddForm (this hook fires from its
     * createFields()); the page-type field is named "RecordType". Older
     * versions used CMSPageAddController::updatePageOptions with a "PageType"
     * field — both field names are handled so the dropdown lands in the right
     * place regardless of CMS version.
     *
     * @param FieldList $fields
     * @return void
     */
    public function updateFields(FieldList $fields): void
    {
        if ($fields->dataFieldByName('TemplateID')) {
            return;
        }

        $title = '<span class="step-label"><span class="flyout">Step 3. </span><span class="title">(Optional) Select template to create page with</span></span>';
        $templateField = GroupedDropdownField::create(
            'TemplateID',
            DBField::create_field('HTMLFragment', $title),
            Template::getGroupedTemplateMap()
        );
        $templateField->setEmptyString('Select template');

        $anchor = $fields->dataFieldByName('RecordType') ? 'RecordType' : 'PageType';
        if ($fields->dataFieldByName($anchor)) {
            $fields->insertAfter($anchor, $templateField);
        } else {
            $fields->push($templateField);
        }
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

        // Read the selected template from the POST submission. Form::getRequestData()
        // holds the data the form was *built* with (the GET to /admin/pages/add),
        // not the submitted values, so the TemplateID must come off the request.
        $request = $form->getController() ? $form->getController()->getRequest() : null;
        $templateID = $request ? $request->postVar('TemplateID') : null;
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
