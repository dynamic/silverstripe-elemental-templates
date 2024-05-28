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

        if ($canCreate && array_key_exists($this->getOwner()->ClassName, $hasElementalArea)) {
            $moreOptions = $actions->fieldByName('ActionMenus.MoreOptions');

            if(!$moreOptions) {
                return;
            }

            $moreOptions->insertAfter(
                'Information',
                FormAction::create('CreateTemplate', 'Create Blocks Template')
                    ->removeExtraClass('btn-primary')
                    ->addExtraClass('btn-secondary')
            );
        }
    }
}
