<?php

namespace Dynamic\ElememtalTemplates\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\Elements\Accordion\Elements\ElementAccordion; // Add this line
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
        $element = new ElementAccordion();
        $element->write();

        $populateData = [
            'Panels' => [
                [
                    'ClassName' => AccordionPanel::class,
                    'Title' => 'Panel 1',
                    'Content' => 'Content for panel 1'
                ],
                [
                    'ClassName' => AccordionPanel::class,
                    'Title' => 'Panel 2',
                    'Content' => 'Content for panel 2'
                ]
            ]
        ];

        $extension = new TestableBaseElementDataExtension();
        $extension->setOwner($element);
        $extension->createRelatedRecords($element, $populateData);

        $this->assertGreaterThan(0, $element->Panels()->count(), "Failed asserting that relation 'Panels' of 'Dynamic\Elements\Accordion\Elements\ElementAccordion' is populated correctly.");
    }
}

class TestableBaseElementDataExtension extends BaseElementDataExtension
{
    public function populateElementData(): void
    {
        parent::populateElementData();
    }

    public function createRelatedRecords(DataObject $owner, array $populateData, int $depth = 0): void
    {
        parent::createRelatedRecords($owner, $populateData, $depth);
    }
}
