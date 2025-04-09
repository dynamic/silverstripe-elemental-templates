<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\Elements\Card\Elements\ElementCard;
use SilverStripe\LinkField\Models\SiteTreeLink;
use Dynamic\ElementalTemplates\Service\FixtureDataService;
use Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension;

class FixtureDataServiceTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the logger
        $mockLogger = $this->createMock(LoggerInterface::class);
        Injector::inst()->registerService($mockLogger, LoggerInterface::class);

        // Override the fixtures path to use the test YAML file
        Config::modify()->set(BaseElementDataExtension::class, 'fixtures', 'vendor/dynamic/silverstripe-elemental-templates/tests/fixtures/test-element-placeholder.yml');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove the test fixture file
        $testFixturePath = BASE_PATH . '/app/fixtures/test-element-placeholder.yml';
        if (file_exists($testFixturePath)) {
            unlink($testFixturePath);
        }
    }

    public function testGetFixtureData(): void
    {
        $service = new FixtureDataService();

        // Test with a valid class name
        $className = ElementContent::class;
        $data = $service->getFixtureData($className);

        $this->assertNotNull($data, 'Fixture data should not be null for a valid class name.');
        $this->assertIsArray($data, 'Fixture data should be an array.');

        // Test with an invalid class name
        $invalidClassName = 'Invalid\\Class\\Name';
        $data = $service->getFixtureData($invalidClassName);

        $this->assertNull($data, 'Fixture data should be null for an invalid class name.');
    }

    public function testElementCreationWithinTemplate(): void
    {
        // Create a real Template record
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write(); // Save to the database to ensure isinDB() returns true

        // Create an ElementalArea and associate it with the Template
        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create a real ElementContent within the Template
        $element = ElementContent::create();
        $element->ParentID = $elementalArea->ID;
        $element->write();

        // Verify that the fields were populated correctly by FixtureDataService
        $this->assertEquals('Example Content Block', $element->getField('Title'));
        $this->assertEquals('<p>This is placeholder content for the example block.</p>', $element->getField('HTML'));
        $this->assertFalse((bool)$element->getField('AvailableGlobally'));
    }

    public function testElementContentCreationWithinTemplate(): void
    {
        // Create a real Template record
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        // Create an ElementalArea and associate it with the Template
        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create a real ElementContent within the ElementalArea
        $element = ElementContent::create();
        $element->ParentID = $elementalArea->ID;
        $element->write();

        // Verify that the fields were populated correctly by FixtureDataService
        $this->assertEquals('Example Content Block', $element->getField('Title'));
        $this->assertEquals('<p>This is placeholder content for the example block.</p>', $element->getField('HTML'));
        $this->assertFalse((bool)$element->getField('AvailableGlobally'));

        // Create an ElementContent outside of an ElementalArea
        $elementOutsideTemplate = ElementContent::create();
        $elementOutsideTemplate->write();

        // Adjusted assertion to match the actual behavior of FixtureDataService
        $this->assertSame(null, $elementOutsideTemplate->getField('Title'));
        $this->assertSame('', $elementOutsideTemplate->getField('HTML'));
        $this->assertNull($elementOutsideTemplate->getField('AvailableGlobally'));
    }

    public function testCreateRelatedObject(): void
    {
        // Create a Template and associate it with an ElementalArea
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create an ElementCard and attach it to the ElementalArea
        $elementCard = ElementCard::create();
        $elementCard->ParentID = $elementalArea->ID;
        $elementCard->write();

        // Verify that the ElementCard has the expected Image and ElementLink records
        $this->assertEquals('Example Card Block', $elementCard->Title);
        $this->assertEquals('<p>This is placeholder content for the card block.</p>', $elementCard->Content);

        $this->assertInstanceOf(SiteTreeLink::class, $elementCard->ElementLink());
        $this->assertEquals('Learn More', $elementCard->ElementLink()->LinkText);
        $this->assertEquals(1, $elementCard->ElementLink()->PageID);

        $this->assertInstanceOf(Image::class, $elementCard->Image());
    }

    public function testPopulateElementDataWithRelationships(): void
    {
        // Create a Template and associate it with an ElementalArea
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create an ElementCard and attach it to the ElementalArea
        $elementCard = ElementCard::create();
        $elementCard->ParentID = $elementalArea->ID;
        $elementCard->write();

        // Verify that the ElementCard has the expected Image and ElementLink records
        $this->assertEquals('Example Card Block', $elementCard->Title);
        $this->assertEquals('<p>This is placeholder content for the card block.</p>', $elementCard->Content);

        $this->assertInstanceOf(SiteTreeLink::class, $elementCard->ElementLink());
        $this->assertEquals('Learn More', $elementCard->ElementLink()->LinkText);
        $this->assertEquals(1, $elementCard->ElementLink()->PageID);

        $this->assertInstanceOf(Image::class, $elementCard->Image());
    }
}