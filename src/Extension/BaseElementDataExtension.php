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
    /**
     * @deprecated 3.2.0 No longer read. The skip state lives on the element itself (dynamic data
     *             "SkipTemplatePopulate"), because SilverStripe shares one extension instance per
     *             process and a flag stored here leaked to every later write. Removed in 4.0.0.
     */
    protected bool $skipPopulateData = false;

    /**
     * Ensures populateElementData runs only once per element instance.
     *
     * @deprecated 3.2.0 No longer read. Populating once per element is now tracked with the
     *             "SkipTemplatePopulate" dynamic data on the element, so the flag no longer leaks
     *             across writes. Removed in 4.0.0.
     */
    protected bool $hasRunPopulateElementData = false;

    /**
     * @var string|null Path to the fixtures YAML file.
     * @config
     */
    private static $fixtures = null;

    /**
     * Sets the flag to skip populateElementData() for this element.
     *
     * The flag is stored on the element, not on the extension, so it applies to this element only.
     */
    public function setSkipPopulateData(bool $skip): void
    {
        $this->getOwner()->setDynamicData('SkipTemplatePopulate', $skip);
    }

    /**
     * @deprecated 3.0.7 The flag was never read. SilverStripe shares one extension instance per
     *             process, so a flag stored here would apply to every later write. TemplateElementDuplicator
     *             now sets AvailableGlobally on the copy directly. This method does nothing.
     */
    public function setResetAvailableGlobally(bool $reset): void
    {
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
     * Keeps AvailableGlobally correct on every write, then populates placeholder data for new
     * Template elements unless the skip flag is set or it has already run for this element.
     *
     * The skip state lives on the element as "SkipTemplatePopulate" dynamic data: the extension
     * instance is shared for the whole process, so a flag stored on it would leak to every
     * element written afterwards.
     */
    protected function onBeforeWrite(): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        $fixtureService = Injector::inst()->get(FixtureDataService::class);

        $owner = $this->getOwner();
        $manager = $this->getOwnerPage();

        $this->updateAvailableGlobally($manager);

        // Skip if populateElementData() has already run for this element, or the caller asked us
        // to skip it for this element (a duplicate keeps its own content)
        if ($owner->getDynamicData('SkipTemplatePopulate')) {
            return;
        }

        // $logger->debug('onBeforeWrite triggered for ' . $this->owner->ClassName);

        if (!$manager instanceof Template || $owner->isInDB()) {
            return;
        }

        // Call the FixtureDataService to populate fields
        $fixtureService->populateElementData($owner);

        // Mark populateElementData as having run for this element
        $owner->setDynamicData('SkipTemplatePopulate', true);
    }

    /**
     * Extension hook for DataObject::duplicate(); the owner is the copy, not the original.
     *
     * duplicate() writes the copy while it still sits in the Template's area, before any caller
     * can set the skip flag, so mark it here to keep the copy's own content. The hook is invoked
     * with the original, $doWrite and $relations, none of which is needed, so it declares no
     * parameters.
     */
    protected function onBeforeDuplicate(): void
    {
        $this->getOwner()->setDynamicData('SkipTemplatePopulate', true);
    }

    /**
     * Elements owned by a Template are never available globally. Every other element keeps whatever
     * the editor chose. Runs on every write, not only the first.
     *
     * @param mixed $manager the element's owner page, as returned by getOwnerPage()
     */
    protected function updateAvailableGlobally(mixed $manager): void
    {
        $owner = $this->getOwner();

        if ($manager instanceof Template && $owner->hasField('AvailableGlobally')) {
            $owner->setField('AvailableGlobally', false);
        }
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
