<?php

namespace Dynamic\ElementalTemplates\Extension;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use Dynamic\ElementalTemplates\Form\TemplatePickerField;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use LeKoala\CmsActions\CustomAction;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Core\Extension;
 * @property \SilverStripe\CMS\Model\SiteTree|\Dynamic\ElementalTemplates\Extension\SiteTreeExtension $owner
 * @property \SilverStripe\CMS\Model\SiteTree $owner
 * @method \SilverStripe\CMS\Model\SiteTree getOwner()

            // Initialize elements variable
            $elements = null;

            // Ensure template has elemental area before accessing elements
            if ($template->Elements() && $template->Elements()->exists()) {
                $elements = $template->Elements()->Elements();
            }