<?php

namespace Dynamic\ElememtalTemplates\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\CMS\Controllers\CMSPageEditController;

/**
 * Class \DNADesign\ElementalSkeletons\Extension\BaseElementDataExtension
 *
 * @property \DNADesign\Elemental\Models\BaseElement|\Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension $owner
 */
class BaseElementDataExtension extends DataExtension
{
    protected $skipPopulateData = false;

    protected $resetAvailableGlobally = false;

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
    public function updateCMSEditLink(string &$link = null): void
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
     * Skips the populateElementData logic if the flag is set.
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // Reset available globally if the flag is set
        if ($this->resetAvailableGlobally) {
            $this->getOwner()->AvailableGlobally = true;
        }

        if ($this->skipPopulateData) {
            return;
        }

        $manager = $this->getOwnerPage();

        if ($manager instanceof Template) {
            // Explicitly set AvailableGlobally to false for Template instances
            if ($this->getOwner()->hasField('AvailableGlobally')) {
                $this->getOwner()->AvailableGlobally = false;
            }

            // Populate data from the fixtures if it exists
            if ($populate = Template::config()->get('populate')) {
                if (array_key_exists($this->getOwner()->ClassName, $populate)) {
                    foreach ($populate[$this->getOwner()->ClassName] as $field => $value) {
                        $this->getOwner()->$field = $value;
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();
    }

    /**
     * @return mixed
     */
    protected function getOwnerPage(): mixed
    {
        return $this->getOwner()->getPage();
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

        parent::canCreate($member);
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

        parent::canEdit($member);
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

        parent::canDelete($member);
    }

    /**
     * @return Member|null
     */
    protected function getCurrentUser(): ?Member
    {
        return Security::getCurrentUser();
    }
}
