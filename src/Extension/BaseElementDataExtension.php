<?php

namespace Dynamic\ElememtalTemplates\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use Dynamic\ElementalTemplates\Service\FixtureDataService;

/**
 * Class \DNADesign\ElementalSkeletons\Extension\BaseElementDataExtension
 *
 * @property \DNADesign\Elemental\Models\BaseElement $owner
 * @method \DNADesign\Elemental\Models\BaseElement getOwner()
 */
class BaseElementDataExtension extends Extension
{
    protected $skipPopulateData = false;

    protected $resetAvailableGlobally = false;

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
    public function onBeforeWrite(): void
    {

        $logger = Injector::inst()->get(LoggerInterface::class);
        $fixtureService = Injector::inst()->get(FixtureDataService::class);

        // Reset available globally if the flag is set (but not for Template instances)
        if (
            $this->getOwner()->exists() &&
            $this->getOwner()->hasField('AvailableGlobally') &&
            !($this->getOwner() instanceof Template)
        ) {
            $this->getOwner()->AvailableGlobally = true;
        }

        // Skip if the skipPopulateData flag is set to true
        if ($this->skipPopulateData || self::$hasRunPopulateElementData) {
            return;
        }

        // $logger->debug('onBeforeWrite triggered for ' . $this->owner->ClassName);

        $manager = $this->getOwnerPage();

        if (!$manager instanceof Template || ($this->getOwner()->exists() && $this->getOwner()->isInDB())) {
            return;
        }

        // Explicitly set AvailableGlobally to false for Template instances
        if ($this->getOwner()->exists() && $this->getOwner()->hasField('AvailableGlobally')) {
            $this->getOwner()->AvailableGlobally = false;
        }

        // Call the FixtureDataService to populate fields
        $fixtureService->populateElementData($this->getOwner());

        // Mark populateElementData as having run
        //self::$hasRunPopulateElementData = true;
    }

    /**
     * @return void
     */
    public function onAfterWrite(): void
    {
    }

    /**
     * @return mixed
     */
    protected function getOwnerPage(): mixed
    {
        if ($this->getOwner()->exists()) {
            return $this->getOwner()->getPage();
        }
        return null;
    }

    /**
     * @param $member
     * @return true|void
     */
    public function canCreate($member)
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

        if ($ownerPage = $this->getOwnerPage()) {
            if ($ownerPage->canCreate($member)) {
                return true;
            }
        }

        return null;
    }

    /**
     * @param $member
     * @return true|void
     */
    public function canEdit($member)
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

        if ($ownerPage = $this->getOwnerPage()) {
            if ($ownerPage->canEdit($member)) {
                return true;
            }
        }

        return null;
    }

    /**
     * @param $member
     * @return true|void
     */
    public function canDelete($member)
    {
        if (!$member instanceof Member) {
            $member = $this->getCurrentUser();
        }

        if ($ownerPage = $this->getOwnerPage()) {
            if ($ownerPage->canDelete($member)) {
                return true;
            }
        }

        return null;
    }

    /**
     * @return Member|null
     */
    protected function getCurrentUser(): ?Member
    {
        return Security::getCurrentUser();
    }
}
