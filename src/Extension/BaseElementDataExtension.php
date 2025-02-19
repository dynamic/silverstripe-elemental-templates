<?php

namespace Dynamic\ElememtalTemplates\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
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

        $logger = Injector::inst()->get(LoggerInterface::class);
    $logger->info("TEST: SilverStripe logging system is working.");

        try {
            $this->populateElementData();
        } catch (\Exception $e) {
            $this->logAction("Error in populateElementData(): " . $e->getMessage(), 'error');
        }
    }

    /**
     * Populates new Elemental blocks with default values from YAML.
     */
    protected function populateElementData(): void
    {
        $this->logAction("populateElementData() running for " . $this->owner->ClassName, 'debug');

        $manager = $this->getOwnerPageSafe();
        if (!$manager instanceof Template) {
            $this->logAction("populateElementData() skipped: Not a Template instance.", 'warning');
            return;
        }

        $populate = $this->getTemplateConfig();
        if (!$populate) {
            $this->logAction("populateElementData() skipped: No populate config found.", 'warning');
            return;
        }

        $ownerClass = $this->owner->ClassName;
        if (!isset($populate[$ownerClass])) {
            $this->logAction("populateElementData() skipped: No data found for {$ownerClass}.", 'warning');
            return;
        }

        $this->logAction("Populating default values for {$ownerClass}", 'info');

        foreach ($populate[$ownerClass] as $field => $value) {
            if ($this->owner->hasField($field)) {
                $this->owner->$field = $value;
            }
        }

        // Attach Element to ElementalArea if missing
        $this->ensureElementAttached();

        // Handle relations
        $this->createRelatedRecords($ownerClass, $populate[$ownerClass]);
    }

    /**
     * Ensures the Elemental block is attached to an ElementalArea.
     */
    protected function ensureElementAttached(): void
    {
        if (!$this->owner->ParentID) {
            $this->logAction("No ParentID found for " . $this->owner->ClassName . ". Attempting to attach to ElementalArea.", 'warning');

            $elementalArea = $this->owner->Page()->ElementalArea();
            if ($elementalArea && $elementalArea->exists()) {
                $this->owner->ParentID = $elementalArea->ID;
                $this->logAction("Attached " . $this->owner->ClassName . " to ElementalArea ID " . $elementalArea->ID, 'info');
            } else {
                $this->logAction("Failed to find ElementalArea for attachment.", 'error');
            }
        }
    }

    /**
     * Creates related records based on has_one, has_many, and many_many relations.
     * Limits recursion to 5 levels to prevent infinite nesting.
     */
    protected function createRelatedRecords(string $ownerClass, array $populateData, int $depth = 0): void
    {
        if ($depth > 5) {
            $this->logAction("Skipping relation population for {$ownerClass}: Max depth (5) reached.", 'warning');
            return;
        }

        try {
            // Handle has_one relations
            foreach ($this->owner->hasOne() as $relationName => $relatedClass) {
                if (!isset($populateData[$relationName])) {
                    continue;
                }

                $relatedData = $populateData[$relationName];
                $relatedClassName = $relatedData['ClassName'] ?? $relatedClass;

                if ($this->owner->getComponent($relationName)->exists()) {
                    continue;
                }

                $relatedObject = $relatedClassName::create();
                foreach ($relatedData as $field => $value) {
                    if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
                        $relatedObject->$field = $value;
                    }
                }
                $relatedObject->write();
                $this->owner->setField("{$relationName}ID", $relatedObject->ID);
                $this->logAction("Created has_one relation {$relatedClassName} for {$relationName}", 'info');

                // Recurse for nested relationships
                $this->createRelatedRecords($relatedClassName, $relatedData, $depth + 1);
            }

            // Handle has_many relations
            foreach ($this->owner->hasMany() as $relationName => $relatedClass) {
                if (!isset($populateData[$relationName]) || !$this->owner->hasMethod($relationName)) {
                    continue;
                }

                if ($this->owner->$relationName()->count() > 0) {
                    continue;
                }

                foreach ($populateData[$relationName] as $itemData) {
                    $relatedClassName = $itemData['ClassName'] ?? $relatedClass;

                    $relatedObject = $relatedClassName::create();
                    foreach ($itemData as $field => $value) {
                        if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
                            $relatedObject->$field = $value;
                        }
                    }
                    $relatedObject->write();
                    $this->owner->$relationName()->add($relatedObject);
                    $this->logAction("Created has_many relation {$relatedClassName} for {$relationName}", 'info');

                    // Recurse for nested relationships
                    $this->createRelatedRecords($relatedClassName, $itemData, $depth + 1);
                }
            }

            // Handle many_many relations (Fix for Slides not being created)
            foreach ($this->owner->manyMany() as $relationName => $relatedClass) {
                if (!isset($populateData[$relationName]) || !$this->owner->hasMethod($relationName)) {
                    continue;
                }

                if ($this->owner->$relationName()->count() > 0) {
                    continue;
                }

                foreach ($populateData[$relationName] as $itemData) {
                    $relatedClassName = $itemData['ClassName'] ?? $relatedClass;

                    $relatedObject = $relatedClassName::create();
                    foreach ($itemData as $field => $value) {
                        if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
                            $relatedObject->$field = $value;
                        }
                    }
                    $relatedObject->write();
                    $this->owner->$relationName()->add($relatedObject);
                    $this->logAction("Created many_many relation {$relatedClassName} for {$relationName}", 'info');

                    // Handle nested has_one (e.g., Image for ImageSlide)
                    foreach ($relatedObject->hasOne() as $subRelationName => $subRelatedClass) {
                        if (!isset($itemData[$subRelationName])) {
                            continue;
                        }

                        $subRelatedData = $itemData[$subRelationName];
                        $subRelatedClassName = $subRelatedData['ClassName'] ?? $subRelatedClass;

                        if ($relatedObject->getComponent($subRelationName)->exists()) {
                            continue;
                        }

                        $subRelatedObject = $subRelatedClassName::create();
                        foreach ($subRelatedData as $subField => $subValue) {
                            if ($subField !== 'ClassName' && $subRelatedObject->hasField($subField)) {
                                $subRelatedObject->$subField = $subValue;
                            }
                        }
                        $subRelatedObject->write();
                        $relatedObject->setField("{$subRelationName}ID", $subRelatedObject->ID);
                        $relatedObject->write();
                        $this->logAction("Created has_one relation {$subRelatedClassName} for {$subRelationName} inside {$relatedClassName}", 'info');
                    }

                    // Recurse for deeper relationships
                    $this->createRelatedRecords($relatedClassName, $itemData, $depth + 1);
                }
            }
        } catch (\Exception $e) {
            $this->logAction("Error in createRelatedRecords(): " . $e->getMessage(), 'error');
        }
    }

    /**
     * Logs messages to SilverStripe's logging system.
     */
    protected function logAction(string $message, string $level = 'info'): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        switch ($level) {
            case 'error':
                $logger->error($message);
                break;
            case 'warning':
                $logger->warning($message);
                break;
            case 'debug':
                $logger->debug($message);
                break;
            default:
                $logger->info($message);
                break;
        }
    }

    /**
     * Retrieves the YAML-defined populate configuration for Elemental blocks.
     */
    protected function getTemplateConfig(): ?array
    {
        return Config::inst()->get(Template::class, 'populate');
    }

    /**
     * Retrieves the owner page safely.
     */
    protected function getOwnerPageSafe(): mixed
    {
        return $this->getOwner()->getPage();
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

                    // Check if ClassName is specified, otherwise use the default relation class
                    $relatedClassName = $relatedData['ClassName'] ?? $relatedClass;

                    // Ensure the relation does not already exist
                    if ($this->owner->getComponent($relationName)->exists()) {
                        continue;
                    }

                    // Create related object and assign it
                    $relatedObject = $relatedClassName::create();
                    foreach ($relatedData as $field => $value) {
                        if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
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
                        // Check if ClassName is specified, otherwise use the default relation class
                        $relatedClassName = $itemData['ClassName'] ?? $relatedClass;

                        $relatedObject = $relatedClassName::create();
                        foreach ($itemData as $field => $value) {
                            if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
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
                        // Check if ClassName is specified, otherwise use the default relation class
                        $relatedClassName = $itemData['ClassName'] ?? $relatedClass;

                        $relatedObject = $relatedClassName::create();
                        foreach ($itemData as $field => $value) {
                            if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
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
