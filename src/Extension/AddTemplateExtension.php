<?php

namespace Dynamic\ElememtalTemplates\Extension;

use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Control\HTTPRequest;

/**
 * Class \Dynamic\ElememtalTemplates\Extension\AddTemplateExtension
 *
 * @property \SilverStripe\Admin\LeftAndMain|\Dynamic\ElememtalTemplates\Extension\AddTemplateExtension $owner
 */
class AddTemplateExtension extends LeftAndMainExtension
{
    /**
     * @var string[]
     */
    private static array $allowed_actions = [
        'CreateTemplate',
    ];

    /**
     * @param $data
     * @param $form
     * @return void
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function CreateTemplate($data, $form): void
    {
        $pageID = $data['ID'];
        $className = $data['ClassName'];

        if ($page = $className::get()->byID($pageID)) {
            $template = Template::create();
            $template->Title = 'Template from ' . $page->Title;
            $template->PageType = $page->ClassName;
            $template->setSkipPopulateData(true);
            $template->write();
            $elements = $template->Elements()->Elements();

            foreach ($page->ElementalArea()->Elements() as $element) {
                $newElement = $element->duplicate();
                $newElement->setSkipPopulateData(true); // Prevent populateElementData() from running
                $newElement->write();
                $elements->add($newElement);
            }

            $template->write();

            $this->getOwner()->redirect($template->CMSEditLink());
        }
    }
}
