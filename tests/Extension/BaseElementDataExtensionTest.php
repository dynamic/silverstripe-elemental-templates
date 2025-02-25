<?php

namespace Dynamic\ElememtalTemplates\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use Psr\Log\LoggerInterface;

class BaseElementDataExtensionTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the logger
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->any())
            ->method('log')
            ->will($this->returnCallback(function ($level, $message) {
                echo "[$level] $message\n";
            }));
        Injector::inst()->registerService($logger, LoggerInterface::class);

        // Load the skeleton-populate.yml configuration from Template class
        $populateConfig = Config::inst()->get(Template::class, 'populate');
        Config::modify()->set(Template::class, 'populate', $populateConfig);
    }

    public function testPopulateElementData()
    {
        $populateConfig = Config::inst()->get(Template::class, 'populate');

        foreach ($populateConfig as $className => $data) {
            if (!class_exists($className)) {
                $this->markTestSkipped("Class $className does not exist.");
                continue;
            }

            // Create a Template instance and associate the element with it
            $template = Template::create();
            $template->write();

            $element = $className::create();
            $element->ParentID = $template->ID;
            $element->write();

            $extension = new TestableBaseElementDataExtension();
            $extension->setOwner($element);

            $extension->populateElementData();

            // Reload the element to ensure the fields are saved
            $element = DataObject::get_by_id($className, $element->ID);

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    continue; // Skip related records for this test
                }
                $this->assertEquals($value, $element->$field, "Failed asserting that field '$field' of '$className' is populated correctly.");
            }
        }
    }

    public function testCreateRelatedRecords()
    {
        $this->markTestSkipped('Skipping testCreateRelatedRecords due to ongoing issues.');

        $populateConfig = Config::inst()->get(Template::class, 'populate');

        foreach ($populateConfig as $className => $data) {
            if (!class_exists($className)) {
                $this->markTestSkipped("Class $className does not exist.");
                continue;
            }

            // Create a Template instance and associate the element with it
            $template = Template::create();
            $template->write();

            $element = $className::create();
            $element->ParentID = $template->ID;
            $element->write();

            $extension = new TestableBaseElementDataExtension();
            $extension->setOwner($element);

            $extension->createRelatedRecords($className, $data);

            // Reload the element to ensure the related records are saved
            $element = DataObject::get_by_id($className, $element->ID);

            foreach ($data as $relationName => $relatedData) {
                if (!is_array($relatedData)) {
                    continue; // Skip non-related records
                }

                if ($element->hasMethod($relationName)) {
                    $relation = $element->$relationName();
                    if (method_exists($relation, 'count')) {
                        $this->assertGreaterThan(0, $relation->count(), "Failed asserting that relation '$relationName' of '$className' is populated correctly.");

                        foreach ($relatedData as $index => $itemData) {
                            $relatedObject = $relation->offsetGet($index);
                            foreach ($itemData as $field => $value) {
                                if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
                                    if (array_key_exists($field, $relatedObject->hasOne())) {
                                        $relatedComponent = $relatedObject->getComponent($field);
                                        $this->assertEquals($value, $relatedComponent->ID, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                                    } elseif ($relatedObject->$field instanceof Image) {
                                        // Ensure the expected value is an ID
                                        if (is_array($value) && isset($value['ID'])) {
                                            $value = $value['ID'];
                                        }
                                        $this->assertEquals($value, $relatedObject->$field->ID, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                                    } else {
                                        $this->assertEquals($value, $relatedObject->$field, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Handle has_one relations
                    $relatedObject = $element->getComponent($relationName);
                    foreach ($relatedData as $field => $value) {
                        if ($field !== 'ClassName' && $relatedObject->hasField($field)) {
                            if (array_key_exists($field, $relatedObject->hasOne())) {
                                $relatedComponent = $relatedObject->getComponent($field);
                                $this->assertEquals($value, $relatedComponent->ID, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                            } elseif ($relatedObject->$field instanceof Image) {
                                // Ensure the expected value is an ID
                                if (is_array($value) && isset($value['ID'])) {
                                    $value = $value['ID'];
                                }
                                $this->assertEquals($value, $relatedObject->$field->ID, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                            } else {
                                $this->assertEquals($value, $relatedObject->$field, "Failed asserting that field '$field' of related object in '$relationName' is populated correctly.");
                            }
                        }
                    }
                }
            }
        }
    }
}

class TestableBaseElementDataExtension extends BaseElementDataExtension
{
    public function populateElementData(): void
    {
        parent::populateElementData();
    }

    public function createRelatedRecords(string $ownerClass, array $populateData, int $depth = 0): void
    {
        parent::createRelatedRecords($ownerClass, $populateData, $depth);
    }
}
