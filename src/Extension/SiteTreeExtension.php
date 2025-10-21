<?php

namespace Dynamic\ElementalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\Injector\Injector;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;

/**
 * Class \Dynamic\ElementalTemplates\Extension\SiteTreeExtension
 *
 * @property \SilverStripe\CMS\Model\SiteTree|\Dynamic\ElementalTemplates\Extension\SiteTreeExtension $owner
 */
class SiteTreeExtension extends Extension
{
    private static $allowed_actions = [
        'applyTemplate'
    ];

    /**
     * Update Settings fields to add a dropdown for applying an existing template.
     *
     * @param FieldList $fields
     */
    public function updateSettingsFields(FieldList $fields): void
    {
        if ($this->owner->ID) {
            $templates = Template::get()->map('ID', 'Title')->toArray();
            $fields->addFieldToTab(
                'Root.Settings',
                DropdownField::create(
                    'ApplyTemplateID',
                    'Select Template to Apply',
                    $templates
                )
                ->setEmptyString('-- Select a Template --')
                ->setDescription('To apply a template, go to More Options and select Apply Blocks Template.')
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

            // Ensure the CreateTemplate action calls the method in AddTemplateExtension
            $moreOptions->insertAfter(
                'Information',
                CustomAction::create('CreateTemplate', 'Create Blocks Template')
                    ->setUseButtonTag(true)
                    ->setAttribute('data-url', $this->owner->Link('CreateTemplate'))
            );

            // "Apply Blocks Template" action if this is an existing page.
            if ($this->getOwner()->ID) {
                $moreOptions->insertAfter(
                    'CreateTemplate',
                    $ap = CustomAction::create('ApplyTemplate', 'Apply Blocks Template')
                );
                $ap->setShouldRefresh(true);
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

        // Ensure the template is retrieved before passing it to the service
        $template = Template::get()->byID($templateID);
        if (!$template) {
            $this->logAction("No template found with ID: " . $templateID, "error");
            $form->sessionMessage('The selected template could not be found.', 'bad');
            return Controller::curr()->redirectBack();
        }

        /** @var TemplateApplicator $applicator */
        $applicator = Injector::inst()->get(TemplateApplicator::class);
        $result = $applicator->applyTemplateToRecord($this->owner, $template);

        if (!$result['success']) {
            $this->logAction($result['message'], "error");
            $form->sessionMessage($result['message'], 'bad');
            return Controller::curr()->redirectBack();
        }

        $form->sessionMessage($result['message'], 'good');
        return Controller::curr()->redirectBack();
    }

    /**
     * Create a template from the current page.
     *
     * @param array $data
     * @param Form $form
     * @return void
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function CreateTemplate($data, $form): void
    {
        $pageID = $data['ID'];
        $className = $data['ClassName'];

        // Ensure the class exists and is a valid subclass of SiteTree
        if (!class_exists($className) || !is_subclass_of($className, \SilverStripe\CMS\Model\SiteTree::class)) {
            $this->logAction("Invalid page class: {$className}", "error");
            $form->sessionMessage('Invalid page class.', 'bad');
            Controller::curr()->redirectBack();
            return;
        }

        // Retrieve the page by ID
        if ($page = $className::get()->byID($pageID)) {
            $template = Template::create();
            $template->Title = 'Template from ' . $page->Title;
            $template->PageType = $page->ClassName;
            $template->write();
            $elements = $template->Elements()->Elements();

            // Duplicate elements from the page's ElementalArea
            if ($page->hasMethod('ElementalArea') && $page->ElementalArea()->exists()) {
                foreach ($page->ElementalArea()->Elements() as $element) {
                    $newElement = $element->duplicate();
                    $newElement->write();
                    $elements->add($newElement);
                }
            }

            $template->write();

            // Redirect to the newly created template's CMS edit link
            Controller::curr()->redirect($template->CMSEditLink());
        } else {
            $this->logAction("Page not found with ID: {$pageID}", "error");
            $form->sessionMessage('Page not found.', 'bad');
            Controller::curr()->redirectBack();
        }
    }

    /**
     * Logs messages using SilverStripe's logging system.
     *
     * @param string $message The log message
     * @param string $level (default: 'info') The log level (info, debug, warning, error)
     */
    protected function logAction(string $message, string $level = 'info'): void
    {
        /** @var LoggerInterface $logger */
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
