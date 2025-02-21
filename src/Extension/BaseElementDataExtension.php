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

                $expectedCount = count($populateData[$relationName]);
                $existingCount = $this->owner->$relationName()->count();

                if ($existingCount >= $expectedCount) {
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

                    // Handle has_one Image for related objects
                    if (isset($itemData['Image']['Filename'])) {
                        $this->logAction("Processing {$relatedClassName}: Checking image assignment...", 'debug');

                        $image = $this->createImageFromFile($itemData['Image']['Filename']);
                        if ($image) {
                            $relatedObject->ImageID = $image->ID;
                            $relatedObject->write();
                            $this->logAction("Assigned Image ID {$image->ID} to {$relatedClassName} (ID: {$relatedObject->ID})", 'info');
                        } else {
                            $this->logAction("Image creation failed, no Image assigned to {$relatedClassName}", 'error');
                        }
                    }

                    // Handle SiteTreeLink for LinkListObject
                    if ($relatedObject->ClassName === 'Dynamic\Elements\Links\Model\LinkListObject') {
                        $this->logAction("LinkListObject detected. Checking for Link data...", 'debug');
                        $this->logAction("Link data: " . json_encode($itemData['Link']), 'debug');

                        if (isset($itemData['Link'])) {
                            $this->logAction("Processing LinkListObject: Checking SiteTreeLink assignment...", 'debug');

                            $linkData = $itemData['Link'];
                            if (!isset($linkData['ClassName'])) {
                                $this->logAction("Link data does not contain ClassName. Skipping Link creation.", 'warning');
                                continue;
                            }

                            $linkClassName = $linkData['ClassName'];
                            if (!class_exists($linkClassName)) {
                                $this->logAction("Link class {$linkClassName} does not exist. Skipping Link creation.", 'error');
                                continue;
                            }

                            $linkObject = $linkClassName::create();
                            foreach ($linkData as $linkField => $linkValue) {
                                if ($linkField !== 'ClassName' && $linkObject->hasField($linkField)) {
                                    $linkObject->$linkField = $linkValue;
                                }
                            }
                            $linkObject->write();
                            $this->logAction("Created SiteTreeLink with ID: {$linkObject->ID}", 'info');

                            $relatedObject->LinkID = $linkObject->ID; // Ensure we are setting the ID
                            $relatedObject->write();
                            $this->logAction("Assigned LinkID: {$linkObject->ID} to LinkListObject with ID: {$relatedObject->ID}", 'info');
                        } else {
                            $this->logAction("No Link data found for LinkListObject.", 'warning');
                        }
                    }

                    // Recurse for nested relationships
                    $this->createRelatedRecords($relatedClassName, $itemData, $depth + 1);
                }
            }

            // Handle many_many relations (Fix for Slides not being created)
            foreach ($this->owner->manyMany() as $relationName => $relatedClass) {
                if (!isset($populateData[$relationName]) || !$this->owner->hasMethod($relationName)) {
                    continue;
                }

                $expectedCount = count($populateData[$relationName]);
                $existingCount = $this->owner->$relationName()->count();

                if ($existingCount >= $expectedCount) {
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

                    // Handle has_one Image for related objects
                    if (isset($itemData['Image']['Filename'])) {
                        $this->logAction("Processing {$relatedClassName}: Checking image assignment...", 'debug');

                        $image = $this->createImageFromFile($itemData['Image']['Filename']);
                        if ($image) {
                            $relatedObject->ImageID = $image->ID;
                            $relatedObject->write();
                            $this->logAction("Assigned Image ID {$image->ID} to {$relatedClassName} (ID: {$relatedObject->ID})", 'info');
                        } else {
                            $this->logAction("Image creation failed, no Image assigned to {$relatedClassName}", 'error');
                        }
                    }

                    // Handle EmbedVideo for VideoSlide
                    if ($relatedObject->ClassName === 'Dynamic\Carousel\Model\VideoSlide' && isset($itemData['EmbedVideo']['SourceURL'])) {
                        $embedData = $itemData['EmbedVideo'];
                        $embedObject = \nathancox\EmbedField\Model\EmbedObject::create();

                        foreach ($embedData as $embedField => $embedValue) {
                            if ($embedObject->hasField($embedField)) {
                                $embedObject->$embedField = $embedValue;
                            }
                        }
                        $embedObject->write();

                        $relatedObject->EmbedVideoID = $embedObject->ID;
                        $relatedObject->write();

                        $this->logAction("Created EmbedObject for VideoSlide with URL: {$embedData['SourceURL']}", 'info');
                    }

                    // Handle nested many_many relationships
                    if (isset($itemData['Testimonials'])) {
                        foreach ($itemData['Testimonials'] as $testimonialData) {
                            $testimonialClassName = $testimonialData['ClassName'] ?? 'Dynamic\Elements\Model\Testimonial';

                            $testimonialObject = $testimonialClassName::create();
                            foreach ($testimonialData as $field => $value) {
                                if ($field !== 'ClassName' && $testimonialObject->hasField($field)) {
                                    $testimonialObject->$field = $value;
                                }
                            }
                            $testimonialObject->write();
                            $testimonialObject->TestimonialCategories()->add($relatedObject);
                            $this->logAction("Added TestimonialCategory to Testimonial (ID: {$testimonialObject->ID})", 'info');
                        }
                    }

                    // Recurse for nested relationships
                    $this->createRelatedRecords($relatedClassName, $itemData, $depth + 1);
                }
            }
        } catch (\Exception $e) {
            $this->logAction("Error in createRelatedRecords(): " . $e->getMessage(), 'error');
        }
    }

    /**
     * Creates an Image object from a file path and returns the Image record.
     */
    protected function createImageFromFile(string $filename): ?Image
    {
        // First, check if path is already absolute
        if (file_exists($filename)) {
            $absolutePath = $filename;
        } else {
            // Try BASE_PATH (assuming file is inside the project root)
            $absolutePath = BASE_PATH . '/' . ltrim($filename, '/');
            if (!file_exists($absolutePath)) {
                // Try PUBLIC_PATH for assets stored under public folder
                $absolutePath = PUBLIC_PATH . '/' . ltrim($filename, '/');
            }
        }

        $this->logAction("🔍 Checking for Image file at: {$absolutePath}", 'debug');

        if (!file_exists($absolutePath)) {
            $this->logAction("⚠️ Image file not found: {$absolutePath}", 'error');
            return null;
        }

        $image = Image::create();
        $image->setFromLocalFile($absolutePath, basename($filename));
        $image->write();

        $this->logAction("✅ Successfully created Image from file {$absolutePath} (ID: {$image->ID})", 'info');

        return $image;
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
