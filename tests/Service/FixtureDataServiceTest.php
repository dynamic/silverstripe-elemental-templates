<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use Dynamic\Carousel\Model\ImageSlide;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\Elements\Card\Elements\ElementCard;
use SilverStripe\LinkField\Models\SiteTreeLink;
use Dynamic\Elements\Carousel\Elements\ElementCarousel;
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
        $element = ElementContent::create();
        $element->ParentID = $elementalArea->ID;
        $element->write();

        // Verify that the fields were populated correctly by FixtureDataService
        $this->assertEquals('Test Content Block Title', $element->getField('Title'));
        $this->assertEquals('<p>Test Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris elementum congue erat, accumsan tincidunt velit porta lobortis. Sed at efficitur ex. Nulla quis porta neque. In hac habitasse platea dictumst. Nullam et malesuada sem. Pellentesque eros eros, rutrum sit amet erat in, finibus ultrices tortor. Curabitur a tincidunt leo, congue interdum ex. Integer a tortor eget ligula eleifend suscipit a rutrum purus. Donec quis rutrum felis.</p>', $element->getField('HTML'));
        $this->assertFalse((bool)$element->getField('AvailableGlobally'));

        // Create an ElementContent outside of an ElementalArea
        $elementOutsideTemplate = ElementContent::create();
        $elementOutsideTemplate->write();

        // Adjusted assertion to match the actual behavior of FixtureDataService
        $this->assertSame(null, $elementOutsideTemplate->getField('Title'));
        $this->assertSame('', $elementOutsideTemplate->getField('HTML'));
        $this->assertTrue((bool)$elementOutsideTemplate->getField('AvailableGlobally'));
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

        // Create an ElementCarousel and attach it to the ElementalArea
        $elementCarousel = ElementCarousel::create();
        $elementCarousel->ParentID = $elementalArea->ID;
        $elementCarousel->write();

        // Verify that the ElementCarousel has the expected Slides
        $slides = $elementCarousel->Slides();
        $this->assertCount(2, $slides, 'ElementCarousel should have 2 Slides.');

        // Log the IDs of the slides being added to the ElementCarousel
        foreach ($slides as $slide) {
            echo "Slide ID: " . $slide->ID . "\n";
        }

        foreach ($slides as $slide) {
            $this->assertInstanceOf(ImageSlide::class, $slide, 'Each Slide should be an instance of ImageSlide.');
            $this->assertNotEmpty($slide->Title, 'Each Slide should have a Title.');
            $this->assertInstanceOf(Image::class, $slide->Image(), 'Each Slide should have an associated Image.');
            $this->assertInstanceOf(SiteTreeLink::class, $slide->ElementLink(), 'Each Slide should have an associated Link.');
        }
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

        // Ensure the ElementLink is retrieved correctly
        $elementLink = $elementCard->ElementLink();
        $this->assertNotNull($elementLink, 'ElementLink should not be null.');
        $this->assertInstanceOf(SiteTreeLink::class, $elementLink, 'ElementLink should be an instance of SiteTreeLink.');
        $this->assertEquals('Learn More', $elementLink->LinkText, 'ElementLink should have the correct LinkText.');
        $this->assertEquals(1, $elementLink->PageID, 'ElementLink should have the correct PageID.');

        $this->assertInstanceOf(Image::class, $elementCard->Image());
    }

    // Add a test for URL vs local image in FixtureDataServiceTest
    public function testImageCreationFromLocalAndURL(): void
    {
        $service = new FixtureDataService();

        $localPath = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        // Test with a local image path
        $localImageData = [
            'PopulateFileFrom' => $localPath,
            'Filename' => 'assets/Placeholder/placeholder.png',
        ];

        $localImage = $service->createImageFromFile($localImageData);
        $this->assertNotNull($localImage, 'Local image should be created successfully.');
        $this->assertInstanceOf(Image::class, $localImage, 'Created object should be an instance of Image.');
        $this->assertEquals('assets/Placeholder/placeholder.png', $localImage->Filename, 'Local image filename should match the provided value.');

        // Test with a URL image path
        $urlImageData = [
            'PopulateFileFrom' => 'https://placehold.co/600x400.png',
            'Filename' => 'test-placeholder.png',
            'Folder' => 'Placeholder',
        ];

        $urlImage = $service->createImageFromFile($urlImageData);
        $this->assertNotNull($urlImage, 'URL image should be created successfully.');
        $this->assertInstanceOf(Image::class, $urlImage, 'Created object should be an instance of Image.');
        $this->assertEquals('Placeholder/test-placeholder.png', $urlImage->Filename, 'URL image filename should match the provided value.');
    }

    public function testCreateImageFromFile(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $imageData = [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'test-placeholder.png',
            'Folder' => 'Placeholder',
        ];

        $image = $service->createImageFromFile($imageData);

        $this->assertNotNull($image, 'Image should be created successfully.');
        $this->assertInstanceOf(Image::class, $image, 'Created object should be an instance of Image.');
        $this->assertEquals('Placeholder/test-placeholder.png', $image->Filename, 'Image filename should match the provided value.');
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
