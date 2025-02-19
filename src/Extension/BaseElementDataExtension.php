<?php

namespace Dynamic\ElememtalTemplates\Extension;

use SilverStripe\Security\Member;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\CMS\Controllers\CMSPageEditController;

/**
 * Class \DNADesign\ElementalSkeletons\Extension\BaseElementDataExtension
 *
 * @property \DNADesign\Elemental\Models\BaseElement|\Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension $owner
 */
class BaseElementDataExtension extends DataExtension
{
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
     * @return void
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->populateDefaultValues();
    }

    /**
     * @return void
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();
    }

    /**
     * Populates default values for new Elemental blocks based on YAML configuration.
     *
     * @return void
     */
    protected function populateDefaultValues(): void
    {
        $manager = $this->getOwnerPage();
        if (!$manager) {
            return;
        }

        if ($manager instanceof Template) {
            $populate = Config::inst()->get(Template::class, 'populate');
            if (!$populate) {
                return;
            }

            $ownerClass = $this->owner->ClassName;

            if (isset($populate[$ownerClass])) {
                // Populate standard fields
                foreach ($populate[$ownerClass] as $field => $value) {
                    if ($this->owner->hasField($field)) {
                        $this->owner->$field = $value;
                    }
                }

                // Handle has_one relations
                foreach ($this->owner->hasOne() as $relationName => $relatedClass) {
                    if (!isset($populate[$ownerClass][$relationName])) {
                        continue;
                    }

                    $relatedData = $populate[$ownerClass][$relationName];

                    // Check if the relation exists already
                    if ($this->owner->getComponent($relationName)->exists()) {
                        continue;
                    }

                    // Create related object and assign it
                    $relatedObject = $relatedClass::create();
                    foreach ($relatedData as $field => $value) {
                        if ($relatedObject->hasField($field)) {
                            $relatedObject->$field = $value;
                        }
                    }
                    $relatedObject->write();
                    $this->owner->setField("{$relationName}ID", $relatedObject->ID);
                }

                // Handle has_many relations
                foreach ($this->owner->hasMany() as $relationName => $relatedClass) {
                    if (!isset($populate[$ownerClass][$relationName]) || !$this->owner->hasMethod($relationName)) {
                        continue;
                    }

                    $existingRecords = $this->owner->$relationName();
                    if ($existingRecords->count() > 0) {
                        continue;
                    }

                    foreach ($populate[$ownerClass][$relationName] as $itemData) {
                        $relatedObject = $relatedClass::create();
                        foreach ($itemData as $field => $value) {
                            if ($relatedObject->hasField($field)) {
                                $relatedObject->$field = $value;
                            }
                        }
                        $relatedObject->write();
                        $this->owner->$relationName()->add($relatedObject);
                    }
                }

                // Handle many_many relations
                foreach ($this->owner->manyMany() as $relationName => $relatedClass) {
                    if (!isset($populate[$ownerClass][$relationName]) || !$this->owner->hasMethod($relationName)) {
                        continue;
                    }

                    $existingRecords = $this->owner->$relationName();
                    if ($existingRecords->count() > 0) {
                        continue;
                    }

                    foreach ($populate[$ownerClass][$relationName] as $itemData) {
                        $relatedObject = $relatedClass::create();
                        foreach ($itemData as $field => $value) {
                            if ($relatedObject->hasField($field)) {
                                $relatedObject->$field = $value;
                            }
                        }
                        $relatedObject->write();
                        $this->owner->$relationName()->add($relatedObject);
                    }
                }
            }
        }
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
