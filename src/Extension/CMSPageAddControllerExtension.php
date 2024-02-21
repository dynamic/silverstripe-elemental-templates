<?php

namespace Dynamic\ElememtalTemplates\Extension;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

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

        // We have to write the record before we can add has_many relations
        $record->write();

        // Find and verify the Template is valid
        $templateID = (int)$form->Fields()->dataFieldByName('TemplateID')->Value();
        $area = $record->ElementalArea();
        if ($templateID && $templateID > 0) {
            /** @var Template $template */
            $template = Template::get()->byID($templateID);

            foreach ($template->Elements()->Elements() as $element) {
                $copy = $element->duplicate();
                $copy->write();
                $copy->writeToStage(Versioned::DRAFT);
                $area->Elements()->add($copy);
            }
        }
    }
}
