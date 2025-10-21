<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElememtalTemplates\Models\Template;
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

        // Ensure the logger captures debug-level logs during tests
        $mockLogger->method('debug')->willReturnCallback(function ($message) {
            error_log("DEBUG: $message");
        });
        $mockLogger->method('warning')->willReturnCallback(function ($message) {
            error_log("WARNING: $message");
        });
        $mockLogger->method('error')->willReturnCallback(function ($message) {
            error_log("ERROR: $message");
        });

        // Dynamically resolve the path to the test fixture file
        $testFixturePath = __DIR__ . '/test-element-placeholder.yml';

        // Debugging: Output the resolved path
        //echo "Resolved fixture path: $testFixturePath\n";

        // Ensure the test fixture file exists
        if (!file_exists($testFixturePath)) {
            throw new \RuntimeException("Test fixture file not found: $testFixturePath");
        }

        // Override the fixtures path to use the correct YAML file
        Config::modify()->set(BaseElementDataExtension::class, 'fixtures', $testFixturePath);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // No need to delete the static fixture file
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
        $template->write(); // Save to the database to ensure isInDB() returns true

        // Create an ElementalArea and associate it with the Template
        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create a real ElementContent within the Template
        // DON'T write it yet - the extension populates on first write
        $element = ElementContent::create();
        $element->ParentID = $elementalArea->ID;
        $element->write(); // This triggers onBeforeWrite which populates fixture data

        // Verify that the fields were populated correctly by FixtureDataService
        $this->assertEquals('Test Content Block Title', $element->getField('Title'));
        $this->assertEquals('<p>Test Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris elementum congue erat, accumsan tincidunt velit porta lobortis. Sed at efficitur ex. Nulla quis porta neque. In hac habitasse platea dictumst. Nullam et malesuada sem. Pellentesque eros eros, rutrum sit amet erat in, finibus ultrices tortor. Curabitur a tincidunt leo, congue interdum ex. Integer a tortor eget ligula eleifend suscipit a rutrum purus. Donec quis rutrum felis.</p>', $element->getField('HTML'));
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
        // The extension populates fixture data on first write (when not in DB yet)
        $element = ElementContent::create();
        $element->ParentID = $elementalArea->ID;
        $element->write(); // Triggers population

        // Verify that the fields were populated correctly by FixtureDataService
        $this->assertEquals('Test Content Block Title', $element->getField('Title'));
        $this->assertEquals('<p>Test Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris elementum congue erat, accumsan tincidunt velit porta lobortis. Sed at efficitur ex. Nulla quis porta neque. In hac habitasse platea dictumst. Nullam et malesuada sem. Pellentesque eros eros, rutrum sit amet erat in, finibus ultrices tortor. Curabitur a tincidunt leo, congue interdum ex. Integer a tortor eget ligula eleifend suscipit a rutrum purus. Donec quis rutrum felis.</p>', $element->getField('HTML'));
        $this->assertFalse((bool)$element->getField('AvailableGlobally'));

        // Create an ElementContent outside of a Template
        $elementOutsideTemplate = ElementContent::create();
        $elementOutsideTemplate->write();

        // Elements outside templates should not be populated with fixture data
        $this->assertSame(null, $elementOutsideTemplate->getField('Title'));
        $this->assertSame('', $elementOutsideTemplate->getField('HTML'));
        // ElementContent doesn't have AvailableGlobally field, so this test doesn't apply
        // The extension sets it for elements that DO have the field
    }

    public function testCreateRelatedObject(): void
    {
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-carousel which is not yet compatible with SilverStripe 6');
    }

    public function testPopulateElementDataWithRelationships(): void
    {
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-card which is not yet compatible with SilverStripe 6');
    }

    // Add a test for URL vs local image in FixtureDataServiceTest
    public function testImageCreationFromLocalAndURL(): void
    {
        $service = new FixtureDataService();

        $localPath = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        // Test with a local image path
        $localImageData = [
            'PopulateFileFrom' => $localPath,
            'Filename' => 'test-placeholder1.png',
            'Folder' => 'Placeholder',
        ];

        $localImage = $service->createImageFromFile($localImageData);
        $this->assertNotNull($localImage, 'Local image should be created successfully.');
        $this->assertInstanceOf(Image::class, $localImage, 'Created object should be an instance of Image.');
        $this->assertEquals('Placeholder/test-placeholder1.png', $localImage->Filename, 'Local image filename should match the provided value.');

        // Test with a URL image path
        $urlImageData = [
            'PopulateFileFrom' => 'https://placehold.co/600x400.png',
            'Filename' => 'test-placeholder2.png',
            'Folder' => 'Placeholder',
        ];

        $urlImage = $service->createImageFromFile($urlImageData);
        $this->assertNotNull($urlImage, 'URL image should be created successfully.');
        $this->assertInstanceOf(Image::class, $urlImage, 'Created object should be an instance of Image.');
        $this->assertEquals('Placeholder/test-placeholder2.png', $urlImage->Filename, 'URL image filename should match the provided value.');
    }

    public function testCreateImageFromFile(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $imageData = [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'test-placeholder3.png',
            'Folder' => 'Placeholder',
        ];

        $image = $service->createImageFromFile($imageData);

        $this->assertNotNull($image, 'Image should be created successfully.');
        $this->assertInstanceOf(Image::class, $image, 'Created object should be an instance of Image.');
        $this->assertEquals('Placeholder/test-placeholder3.png', $image->Filename, 'Image filename should match the provided value.');
    }

    public function testDuplicateCheck(): void
    {
        $service = new FixtureDataService();

        // Test with a new record
        $data = [
            'Title' => 'Unique Title',
            'Content' => 'Unique Content',
            'DuplicateCheck' => ['Title', 'Content'],
        ];

        $newRecord = $service->createRelatedObject(ElementContent::class, $data);
        $this->assertNotNull($newRecord, 'New record should be created successfully.');
        $this->assertEquals('Unique Title', $newRecord->Title, 'Title should match the provided value.');
        $this->assertEquals('Unique Content', $newRecord->Content, 'Content should match the provided value.');

        // Test with a duplicate record
        $duplicateRecord = $service->createRelatedObject(ElementContent::class, $data);
        $this->assertNotNull($duplicateRecord, 'Duplicate record should be found successfully.');
        $this->assertEquals($newRecord->ID, $duplicateRecord->ID, 'Duplicate record should match the existing record.');
    }
}
