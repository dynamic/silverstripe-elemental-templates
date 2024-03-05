<?php

namespace Dynamic\ElememtalTemplates\Extension;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

/**
 * Class \Dynamic\ElememtalTemplates\Extension\SiteTreeExtension
 *
 * @property \SilverStripe\CMS\Model\SiteTree|\Dynamic\ElememtalTemplates\Extension\SiteTreeExtension $owner
 */
class SiteTreeExtension extends DataExtension
{
    /**
     * @param FieldList $actions
     * @return void
     * @throws \ReflectionException
     */
    public function updateCMSActions(FieldList $actions): void
    {
        $canCreate = Template::singleton()->canCreate();
        $hasElementalArea = Template::getDecoratedBy(ElementalAreasExtension::class, DataObject::class);

        if ($canCreate && in_array($this->getOwner()->ClassName, $hasElementalArea)) {
            $actions->addFieldToTab(
                'ActionMenus.MoreOptions',
                FormAction::create('CreateTemplate', 'Create Blocks Template')
            );
        }
    }
}
