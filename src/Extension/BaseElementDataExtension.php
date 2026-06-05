<?php

namespace Dynamic\ElementalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Core\Extension;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use Dynamic\ElementalTemplates\Service\FixtureDataService;
use Dynamic\ElementalTemplates\Service\FixtureDataService;
 * @property \DNADesign\Elemental\Models\BaseElement|\Dynamic\ElementalTemplates\Extension\BaseElementDataExtension $owner
 * @property \DNADesign\Elemental\Models\BaseElement $owner
 * @method \DNADesign\Elemental\Models\BaseElement getOwner()
     * Ensures populateElementData runs only once per request.
     */
    private static $hasRunPopulateElementData = false;

    /**
     * Guard to prevent infinite recursion in onBeforeWrite
     */
    private bool $isWriting = false;

    /**
        $logger = Injector::inst()->get(LoggerInterface::class);
        $fixtureService = Injector::inst()->get(FixtureDataService::class);

        // Reset available globally if the flag is set
        $this->getOwner()->AvailableGlobally = true;

        // Skip if the skipPopulateData flag is set to true or if already run for this instance
        if ($this->skipPopulateData || $this->hasRunPopulateElementData) {
        // Prevent infinite recursion
        if ($this->isWriting) {

        // Explicitly set AvailableGlobally to false for Template instances
        if ($this->getOwner()->hasField('AvailableGlobally')) {
            $this->getOwner()->AvailableGlobally = false;
        }

        // Call the FixtureDataService to populate fields
        $fixtureService->populateElementData($this->owner);

        // Mark populateElementData as having run for this instance
        $this->hasRunPopulateElementData = true;
        // Extension hook method in SilverStripe 6 - no parent call needed
     * Extension hook for canCreate permission check
     *
     * @param Member|null $member
     * @param array $context Additional context for permission checking
     * @param bool &$result The result of the permission check (passed by reference)
     * @return void
     * @param $member
     * @return bool

        if ($ownerPage = $this->getOwnerPage()) {
            if (!$ownerPage->canCreate($member)) {
                $result = false;
            }
        }
    }

    /**
     * Extension hook for canEdit permission check
     *
     * @param Member|null $member
     * @param array $context Additional context for permission checking
     * @param bool &$result The result of the permission check (passed by reference)
     * @return void
        
        // Return the actual permission of the owner element
        return $this->getOwner()->canCreate($member);
    }

    /**
     * @param $member
     * @return bool

        if ($ownerPage = $this->getOwnerPage()) {
            if (!$ownerPage->canEdit($member)) {
                $result = false;
            }
        }
    }

    /**
     * Extension hook for canDelete permission check
     *
     * @param Member|null $member
     * @param array $context Additional context for permission checking
     * @param bool &$result The result of the permission check (passed by reference)
     * @return void
        
        // Return the actual permission of the owner element
        return $this->getOwner()->canEdit($member);
    }

    /**
     * @param $member
     * @return bool

        if ($ownerPage = $this->getOwnerPage()) {
            if (!$ownerPage->canDelete($member)) {
                $result = false;
            }
        }
        
        // Return the actual permission of the owner element
        return $this->getOwner()->canDelete($member);