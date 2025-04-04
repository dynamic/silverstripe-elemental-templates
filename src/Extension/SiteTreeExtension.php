<?php

namespace Dynamic\ElememtalTemplates\Extension;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use LeKoala\CmsActions\CustomAction;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

/**
 * Class \Dynamic\ElememtalTemplates\Extension\SiteTreeExtension
 *
 * @property \SilverStripe\CMS\Model\SiteTree|\Dynamic\ElememtalTemplates\Extension\SiteTreeExtension $owner
 */
class SiteTreeExtension extends DataExtension
{
    private static $allowed_actions = [
        'applyTemplate'
    ];

    /**
     * Update CMS fields to add a tab for applying an existing template.
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields): void
    {
        // Only add the apply template field if the page already exists
        if ($this->owner->ID) {
            $templates = Template::get()->map('ID', 'Title')->toArray();
            $fields->findOrMakeTab('Root.ApplyTemplate')
                ->push(
                    DropdownField::create(
                        'ApplyTemplateID',
                        'Select Template to Apply',
                        $templates
                    )->setEmptyString('-- Select a Template --')
                );
        }
    }

    /**
     * Update CMS actions using lekoala/silverstripe-cms-actions.
     *
     * This registers:
     *  - "Create Blocks Template" action for creating a new template, and
     *  - "Apply Blocks Template" action for applying an existing one.
     *
     * @param FieldList $actions
     * @return void
     * @throws \ReflectionException
     */
    public function updateCMSActions(FieldList $actions): void
    {
        $canCreate = Template::singleton()->canCreate();
        $hasElementalArea = Template::getDecoratedBy(ElementalAreasExtension::class, DataObject::class);

        // Only add actions if the current page supports elemental areas
        if ($canCreate && array_key_exists($this->getOwner()->ClassName, $hasElementalArea)) {
            $moreOptions = $actions->fieldByName('ActionMenus.MoreOptions');

            if (!$moreOptions) {
                return;
            }

            // "Create Blocks Template" action using CustomAction.
            // Provide both the action name and title as required.
            $moreOptions->insertAfter(
                'Information',
                CustomAction::create('CreateTemplate', 'Create Blocks Template')
            );

            // "Apply Blocks Template" action if this is an existing page.
            if ($this->getOwner()->ID) {
                $moreOptions->insertAfter(
                    'CreateTemplate',
                    CustomAction::create('ApplyTemplate', 'Apply Blocks Template')
                );
            }
        }
    }

    /**
     * Handler to apply the selected template to this page.
     *
     * Logs key checkpoints for troubleshooting.
     *
     * @param array $data Form data, expecting an 'ApplyTemplateID' field.
     * @param Form $form
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function applyTemplate($data, Form $form)
    {
        $this->logAction("applyTemplate triggered", "debug");

        $templateID = $data['ApplyTemplateID'] ?? null;
        if (!$templateID) {
            $this->logAction("No template ID provided in the form data.", "warning");
            $form->sessionMessage('Please select a template before applying.', 'bad');
            return Controller::curr()->redirectBack();
        }
        $this->logAction("Template ID received: " . $templateID, "debug");

        $template = Template::get()->byID($templateID);
        if (!$template) {
            $this->logAction("No template found with ID: " . $templateID, "error");
            $form->sessionMessage('The selected template could not be found.', 'bad');
            return Controller::curr()->redirectBack();
        }
        $this->logAction("Template found: " . $template->Title, "debug");

        // Get the template's ElementalArea.
        $templateArea = $template->Elements();
        if (!$templateArea || !$templateArea->exists()) {
            $this->logAction("Template with ID {$templateID} does not have an elemental area.", "error");
            $form->sessionMessage('The selected template does not have an elemental area.', 'bad');
            return Controller::curr()->redirectBack();
        }

        // Leverage Template::getDecoratedBy() so that if the current page type
        // is decorated by ElementalAreasExtension (using Page as the base) then it can operate.
        $decoratedTypes = Template::getDecoratedBy(ElementalAreasExtension::class, \Page::class);
        if (!isset($decoratedTypes[$this->getOwner()->ClassName])) {
            $this->logAction("The page type ({$this->getOwner()->ClassName}) is not configured with an elemental area.", "error");
            $form->sessionMessage('This page is not configured with an elemental area.', 'bad');
            return Controller::curr()->redirectBack();
        }

        // Check that the current page supports ElementalArea() relation.
        if (!$this->getOwner()->hasMethod('ElementalArea')) {
            $this->logAction("Method 'ElementalArea' does not exist on page type {$this->getOwner()->ClassName}.", "error");
            $form->sessionMessage('This page does not support elemental areas.', 'bad');
            return Controller::curr()->redirectBack();
        }

        // Get the page's ElementalArea.
        $pageArea = $this->getOwner()->ElementalArea();
        if (!$pageArea || !$pageArea->exists()) {
            $this->logAction("The page does not have an elemental area to apply the template.", "error");
            $form->sessionMessage('This page does not have an elemental area to apply the template.', 'bad');
            return Controller::curr()->redirectBack();
        }

        // Duplicate each element from the template into the page's elemental area.
        foreach ($templateArea->Elements() as $element) {
            $newElement = $element->duplicate();
            $pageArea->Elements()->add($newElement);
            $this->logAction("Duplicated element (ID: {$element->ID}) to new element (ID: {$newElement->ID}).", "debug");
        }

        $this->getOwner()->write();
        $this->logAction("Template applied successfully to page ID {$this->getOwner()->ID}.", "info");

        $form->sessionMessage('The template has been applied successfully.', 'good');
        
        // Force the CMS to reload the edit view (including the ElementalArea)
        return Controller::curr()->redirectBack();
    }

    /**
     * Logs messages using SilverStripe's logging system.
     *
     * @param string $message The log message
     * @param string $level (default: 'info') The log level (info, debug, warning, error)
     */
    protected function logAction(string $message, string $level = 'info'): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        switch ($level) {
            case 'error':
                $logger->error($message);
                break;
            case 'warning':
                $logger->warning($message);
                break;
            case 'debug':
                $logger->debug($message);
                break;
            default:
                $logger->info($message);
                break;
        }
    }
}
