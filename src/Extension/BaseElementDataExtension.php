<?php

namespace Dynamic\ElementalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use Dynamic\ElementalTemplates\Service\FixtureDataService;

/**
 * Class BaseElementDataExtension
 *
 * @property \DNADesign\Elemental\Models\BaseElement|\Dynamic\ElementalTemplates\Extension\BaseElementDataExtension $owner
 */
class BaseElementDataExtension extends Extension
{
    protected bool $skipPopulateData = false;

    protected bool $resetAvailableGlobally = false;

    /**
     * @var string|null Path to the fixtures YAML file.
     * @config
     */
    private static $fixtures = null;

    /**
     * Ensures populateElementData runs only once per request.
     */
    private static $hasRunPopulateElementData = false;

    /**
     * Sets the flag to skip populateElementData().
     */
    public function setSkipPopulateData(bool $skip): void
    {
        $this->skipPopulateData = $skip;
    }

    /**
     * Sets the flag to skip populateElementData().
     */
    public function setResetAvailableGlobally(bool $reset): void
    {
        $this->resetAvailableGlobally = $reset;
    }

    /**
     * @var string
     * @config
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $manager = $this->getOwnerPage();

        if ($manager instanceof Template) {
            $fields->removeByName('AvailableGlobally');
        }
    }

    /**
     * @param string|null $link
     * @return void
     */
    public function updateCMSEditLink(?string &$link = null): void
    {
        $owner = $this->getOwner();

        $relationName = $owner->getAreaRelationName();
        $page = $this->getOwnerPage();

        if (!$page) {
            return;
        }

        if ($page instanceof Template) {
            // nested bock - we need to get edit link of parent block
            $link = Controller::join_links(
                $page->CMSEditLink(),
                'ItemEditForm/field/' . $page->getOwnedAreaRelationName() . '/item/',
                $owner->ID
            );

            // remove edit link from parent CMS link
            $link = preg_replace('/\/item\/([\d]+)\/edit/', '/item/$1', $link);
        } else {
            // block is directly under a non-block object - we have reached the top of nesting chain
            $link = Controller::join_links(
                singleton(CMSPageEditController::class)->Link('EditForm'),
                $page->ID,
                'field/' . $relationName . '/item/',
                $owner->ID
            );
        }

        $link = Controller::join_links(
            $link,
            'edit'
        );
    }

    /**
     * Skips the populateElementData logic if the flag is set or if it has already run.
     */
    protected function onBeforeWrite(): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        $fixtureService = Injector::inst()->get(FixtureDataService::class);

        // Reset available globally if the flag is set
        $this->getOwner()->AvailableGlobally = true;

        // Skip if the skipPopulateData flag is set to true
        if ($this->skipPopulateData || self::$hasRunPopulateElementData) {
            return;
        }

        // $logger->debug('onBeforeWrite triggered for ' . $this->owner->ClassName);

        $manager = $this->getOwnerPage();

        if (!$manager instanceof Template || $this->owner->isInDB()) {
            return;
        }

        // Explicitly set AvailableGlobally to false for Template instances
        if ($this->getOwner()->hasField('AvailableGlobally')) {
            $this->getOwner()->AvailableGlobally = false;
        }

        // Call the FixtureDataService to populate fields
        $fixtureService->populateElementData($this->owner);

        // Mark populateElementData as having run
        //self::$hasRunPopulateElementData = true;
    }

    /**
     * @return void
     */
    protected function onAfterWrite(): void
    {
        // Extension hook method in SilverStripe 6 - no parent call needed
    }

    /**
     * @return mixed
     */
    protected function getOwnerPage(): mixed
    {
        return $this->getOwner()->getPage();
    }

    /**
     * Extension hook for canCreate permission check
     * 
     * @param Member|null $member
     * @param array $context Additional context for permission checking
     * @param bool &$result The result of the permission check (passed by reference)
     * @return void
     */
    public function updateCanCreate(?Member $member, array $context, bool &$result): void
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

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
     */
    public function updateCanEdit(?Member $member, array $context, bool &$result): void
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

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
     */
    public function updateCanDelete(?Member $member, array $context, bool &$result): void
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

        if ($ownerPage = $this->getOwnerPage()) {
            if (!$ownerPage->canDelete($member)) {
                $result = false;
            }
        }
    }

    /**
     * @return Member|null
     */
    protected function getCurrentUser(): ?Member
    {
        return Security::getCurrentUser();
    }
}
