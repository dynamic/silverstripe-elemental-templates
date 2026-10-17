<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\FixtureDataService;
use Dynamic\ElementalTemplates\Extension\BaseElementDataExtension;

class FixtureDataServiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep published asset bytes out of the project's real assets folder.
        TestAssetStore::activate('FixtureDataServiceTest');

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

        // Ensure the test fixture file exists
        if (!file_exists($testFixturePath)) {
            throw new \RuntimeException("Test fixture file not found: $testFixturePath");
        }

        // Override the fixtures path to use the correct YAML file
        Config::modify()->set(BaseElementDataExtension::class, 'fixtures', $testFixturePath);
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
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
        $template->write();

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

        // Create an ElementContent outside of a Template
        $elementOutsideTemplate = ElementContent::create();
        $elementOutsideTemplate->write();

        // Elements outside templates should not be populated with fixture data
        $this->assertSame(null, $elementOutsideTemplate->getField('Title'));
        $this->assertSame('', $elementOutsideTemplate->getField('HTML'));
    }

    public function testCreateRelatedObject(): void
    {
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-carousel which is not yet compatible with SilverStripe 6');
    }

    public function testPopulateElementDataWithRelationships(): void
    {
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-card which is not yet compatible with SilverStripe 6');
    }

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

    /**
     * Regression test for #99: underscores in the supplied Folder and Filename must be
     * preserved rather than turned into directory separators.
     */
    public function testCreateImageFromFileKeepsUnderscores(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $image = $service->createImageFromFile([
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'hero_image.png',
            'Folder' => 'Placeholder',
        ]);

        $this->assertNotNull($image, 'Image with an underscore in its filename should be created.');
        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame(
            'Placeholder/hero_image.png',
            $image->Filename,
            'Underscores in the filename must not be converted into folders.'
        );

        // An underscore in the folder name must stay inside a single folder too.
        $folderedImage = $service->createImageFromFile([
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'banner_image.png',
            'Folder' => 'Hero_Images',
        ]);

        $this->assertNotNull($folderedImage, 'Image inside an underscored folder should be created.');
        $this->assertSame(
            'Hero_Images/banner_image.png',
            $folderedImage->Filename,
            'Underscores in the folder name must not be converted into folders.'
        );
    }

    /**
     * Regression test for #113: an image DuplicateCheck must actually match the image the
     * fixture already created, and reusing it must not move it out of its folder.
     */
    public function testImageDuplicateCheckReusesImageAndKeepsItsFolder(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');
        $title = 'Duplicate check placeholder';

        $data = [
            'Title' => $title,
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'dup.png',
            'Folder' => 'Placeholder',
            'DuplicateCheck' => ['Title', 'Filename'],
        ];

        $first = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($first, 'The first image should be created.');
        $this->assertSame(
            'Placeholder/dup.png',
            $first->getFilename(),
            'The image should be stored in the folder the fixture asked for.'
        );
        $this->assertGreaterThan(0, (int)$first->ParentID, 'The image should sit inside the Placeholder folder.');
        $parentID = (int)$first->ParentID;

        $second = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($second, 'The second call should return an image.');
        $this->assertSame(
            $first->ID,
            $second->ID,
            'The DuplicateCheck should reuse the image the fixture already created.'
        );
        $this->assertSame(
            1,
            Image::get()->filter('Title', $title)->count(),
            'Only one image should exist for this fixture.'
        );

        $second = Image::get()->byID($second->ID);
        $this->assertSame($parentID, (int)$second->ParentID, 'Reusing the image must not move it to another folder.');
        $this->assertSame(
            'Placeholder/dup.png',
            $second->getFilename(),
            'Reusing the image must not change its filename.'
        );
    }

    /**
     * The duplicate lookup key has to be filtered the way File::setFilename() filters, or a
     * Filename the FileNameFilter rewrites never matches the stored FileFilename and every
     * element adds another copy of the same image.
     */
    public function testImageDuplicateCheckMatchesFilteredFilename(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');
        $title = 'Filtered duplicate';

        $data = [
            'Title' => $title,
            'PopulateFileFrom' => $populateFileFrom,
            // Spaces and brackets: sanitising gives "hero_image__1_.png", the FileNameFilter
            // SilverStripe stores with collapses that to "hero_image_1_.png".
            'Filename' => 'hero image (1).png',
            'Folder' => 'Placeholder',
            'DuplicateCheck' => ['Title', 'Filename'],
        ];

        $first = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($first, 'The first image should be created.');
        $this->assertSame(
            'Placeholder/hero_image_1_.png',
            $first->getFilename(),
            'The image should be stored under the filtered name SilverStripe derives.'
        );

        $second = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($second, 'The second call should return an image.');
        $this->assertSame(
            $first->ID,
            $second->ID,
            'The duplicate lookup key must be filtered the same way the stored path is.'
        );
    }

    /**
     * Regression test for #113: importRemoteImage() used to stage its download by writing it
     * straight onto ASSETS_PATH/<Folder>/<Filename> - the path an already published asset
     * occupies - and only then register the file. Re-importing under the same Filename
     * therefore replaced the bytes sitting at that path (and the AssetStore then consumed
     * them). The download has to be staged in a temp file instead, so the AssetStore can
     * resolve the name conflict without anything being written over an existing asset.
     */
    public function testRemoteImageImportDoesNotOverwriteExistingAssetBytes(): void
    {
        $service = new FixtureDataService();

        // Two different images, staged locally and served over file:// so no network is needed.
        $placeholder = file_get_contents(__DIR__ . '/placeholder.png');
        $this->assertNotFalse($placeholder, 'The test placeholder image should be readable.');

        $firstSource = $this->writeTempSource('fixture_first_', $placeholder);
        // Appending a byte keeps the file a valid PNG while changing its content and hash.
        $secondSource = $this->writeTempSource('fixture_second_', $placeholder . "\0");

        // The path the pre-fix code staged its download onto. It deliberately stays outside the
        // TestAssetStore basedir: ASSETS_PATH is where the old file_put_contents() wrote, and
        // that is the write this test is about.
        $publicPath = ASSETS_PATH . '/ImportTest/remote-dup.png';

        try {
            $tempFilesBefore = $this->listStagedTempFiles();
            $first = $service->importRemoteImage('file://' . $firstSource, 'remote-dup.png', 'ImportTest');
            $this->assertNotNull($first, 'The first remote import should succeed.');
            $this->assertSame(
                md5($placeholder),
                md5((string)$first->getString()),
                'The first import should publish its own bytes.'
            );

            // The bytes an already published asset occupies at that path, which a second
            // import under the same Filename must leave alone.
            $this->assertFalse(file_exists($publicPath), 'The first import should not leave a file on the public path.');
            @mkdir(dirname($publicPath ?? ''), 0777, true);
            file_put_contents($publicPath, $placeholder);

            $second = $service->importRemoteImage('file://' . $secondSource, 'remote-dup.png', 'ImportTest');
            $this->assertNotNull($second, 'The second remote import should succeed.');
            $this->assertNotSame(
                $first->ID,
                $second->ID,
                'The second import should create its own image record.'
            );

            $first->flushCache();
            $this->assertSame(
                md5($placeholder),
                md5((string)$first->getString()),
                'Importing a second file under the same Filename must not change the first image\'s content.'
            );
            $this->assertFileExists($publicPath, 'A second import must not consume or remove the file at the target path.');
            $this->assertSame(
                $placeholder,
                file_get_contents($publicPath),
                'A second import must not overwrite the bytes already sitting at the target path.'
            );
            $this->assertSame(
                $tempFilesBefore,
                $this->listStagedTempFiles(),
                'The staged download must be cleaned up once the AssetStore has the bytes.'
            );
        } finally {
            @unlink($firstSource);
            @unlink($secondSource);
            @unlink($publicPath);
        }
    }

    /**
     * Bad input: an unreadable remote source returns null and creates no record. The temp file
     * check guards the staging cleanup; the download itself fails before anything is staged.
     */
    public function testRemoteImageImportOfUnreadableUrlReturnsNull(): void
    {
        $service = new FixtureDataService();

        $before = $this->listStagedTempFiles();
        $image = $service->importRemoteImage('file:///definitely/not/a/real/file.png', 'missing.png', 'ImportTest');

        $this->assertNull($image, 'An unreadable URL should not create an image.');
        $this->assertSame($before, $this->listStagedTempFiles(), 'No staged temp file should be left behind.');
    }

    /**
     * Without DuplicateCheck every call creates a fresh image (unchanged behaviour).
     */
    public function testImageWithoutDuplicateCheckAlwaysCreatesANewImage(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');
        $data = [
            'Title' => 'No duplicate check',
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'no-dup.png',
            'Folder' => 'Placeholder',
        ];

        $first = $service->createRelatedObject(Image::class, $data);
        $second = $service->createRelatedObject(Image::class, $data);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->ID, $second->ID, 'Without DuplicateCheck a new image is created every time.');
    }

    /**
     * The fixture Title has to land on the image on the remote branch too, otherwise a
     * DuplicateCheck on Title can never match (#113).
     */
    public function testCreateImageFromFileWritesFixtureTitleForRemoteSource(): void
    {
        $service = new FixtureDataService();

        $source = $this->writeTempSource('fixture_title_', (string)file_get_contents(__DIR__ . '/placeholder.png'));

        try {
            $image = $service->createImageFromFile([
                'Title' => 'Remote fixture title',
                'PopulateFileFrom' => 'file://' . $source,
                'Filename' => 'titled-remote.png',
                'Folder' => 'ImportTest',
            ]);

            $this->assertNotNull($image, 'An image should be created from a file:// source.');
            $this->assertSame(
                'Remote fixture title',
                $image->Title,
                'The fixture Title should be written on the remote branch as well.'
            );
            $this->assertSame('ImportTest/titled-remote.png', $image->getFilename());
        } finally {
            @unlink($source);
        }
    }

    /**
     * Write a fixture source image to a uniquely named temp file, and return its path. Unlike
     * tempnam() this leaves no stray second file behind for the caller to clean up.
     */
    private function writeTempSource(string $prefix, string $contents): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Temp files this test suite staged in the system temp directory.
     *
     * @return string[]
     */
    private function listStagedTempFiles(): array
    {
        $files = glob(sys_get_temp_dir() . '/ss_fixture_import_*');
        return $files === false ? [] : $files;
    }

    /**
     * A DuplicateCheck whose fields are all absent from the fixture data must not fall back to
     * an unfiltered lookup, which would hand back (and previously overwrite) an unrelated record.
     */
    public function testDuplicateCheckWithoutUsableFieldLeavesOtherRecordsAlone(): void
    {
        $service = new FixtureDataService();

        $seeded = ElementContent::create();
        $seeded->Title = 'Seeded block';
        $seeded->Content = 'Seeded content';
        $seeded->write();

        $created = $service->createRelatedObject(ElementContent::class, [
            'Content' => 'Fresh content',
            // Title is the only check field and it is not in the data above.
            'DuplicateCheck' => ['Title'],
        ]);

        $this->assertNotNull($created, 'A record should still be created.');
        $this->assertNotSame(
            $seeded->ID,
            $created->ID,
            'Without a usable check field the lookup must not match an arbitrary record.'
        );

        $seeded->flushCache();
        $this->assertSame(
            'Seeded content',
            $seeded->Content,
            'The unrelated record must not be updated.'
        );
        $this->assertSame('Seeded block', $seeded->Title, 'The unrelated record must not be updated.');
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
