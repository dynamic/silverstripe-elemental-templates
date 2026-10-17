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
        $publicFolder = dirname($publicPath ?? '');
        $publicFolderPreExisted = is_dir($publicFolder ?? '');

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
            if (!$publicFolderPreExisted) {
                // The folder this test mkdir'ed is not its to keep: leaving it behind puts an empty
                // folder in the project's real assets path, which is what this branch stops doing.
                @rmdir($publicFolder ?? '');
            }
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

    /**
     * importRemoteImage() appends an extension it had to infer from the downloaded bytes when the
     * fixture Filename carries none, so a duplicate lookup keyed on the raw, extension-less name
     * never matched it and every run created another copy of the same image.
     */
    public function testImageDuplicateCheckMatchesInferredExtensionOfRemoteImport(): void
    {
        $service = new FixtureDataService();

        $source = $this->writeTempSource('fixture_noext_', (string)file_get_contents(__DIR__ . '/placeholder.png'));

        try {
            $data = [
                'Title' => 'Extensionless import',
                'PopulateFileFrom' => 'file://' . $source,
                // No extension: the import has to infer one, so the stored path is not the name
                // the fixture spelled out.
                'Filename' => 'no-extension',
                'Folder' => 'ImportTest',
                'DuplicateCheck' => ['Title', 'Filename'],
            ];

            $first = $service->createRelatedObject(Image::class, $data);
            $this->assertNotNull($first, 'The first import should create an image.');
            $this->assertSame(
                'ImportTest/no-extension.png',
                $first->getFilename(),
                'The inferred extension is part of the path the image is stored at.'
            );

            $second = $service->createRelatedObject(Image::class, $data);
            $this->assertNotNull($second, 'The second call should return an image.');
            $this->assertSame(
                $first->ID,
                $second->ID,
                'The lookup key has to allow for the extension the import appends.'
            );
            $this->assertSame(1, Image::get()->count(), 'Only one image should exist for this fixture.');
        } finally {
            @unlink($source);
        }
    }

    /**
     * The same extension question on the local branch, answered with the behaviour the code really
     * has: an extension-less Filename never becomes a record at all, because Image refuses a name
     * with no extension. That is why the duplicate lookup does not need the raw path as a candidate
     * (it is unchanged from before this branch - the module has always refused this input).
     */
    public function testCreateImageFromFileRejectsExtensionlessFilename(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $image = $service->createImageFromFile([
            'Title' => 'Extensionless local',
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'no-extension-local',
            'Folder' => 'NoExtTest',
        ]);

        $this->assertNull($image, 'An image with no extension cannot be written, so nothing is created.');
        $this->assertSame(0, Image::get()->count(), 'A refused image must not leave a record behind.');
    }

    /**
     * createImageFromFile()'s local branch used to pre-create ASSETS_PATH/<Folder>, which
     * setFromLocalFile() never needed: it only left an empty folder in the project's real assets
     * path, and wrote there even while a test store was active.
     */
    public function testCreateImageFromFileDoesNotCreatePublicAssetFolders(): void
    {
        $service = new FixtureDataService();

        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');
        $folder = 'NoMkdirTest';
        $publicFolder = ASSETS_PATH . '/' . $folder;

        try {
            $this->assertDirectoryDoesNotExist($publicFolder, 'The test starts from a clean assets path.');

            $image = $service->createImageFromFile([
                'PopulateFileFrom' => $populateFileFrom,
                'Filename' => 'local-only.png',
                'Folder' => $folder,
            ]);

            $this->assertNotNull($image, 'The image should still be created.');
            $this->assertSame(
                $folder . '/local-only.png',
                $image->getFilename(),
                'The folder the fixture asked for is still applied.'
            );
            $this->assertNotSame(
                '',
                (string)$image->getString(),
                'The AssetStore publishes the bytes, and creates whatever folder it needs to do that.'
            );
            $this->assertDirectoryDoesNotExist(
                $publicFolder,
                'Creating an image must not create a folder in the project real assets path.'
            );
        } finally {
            // With the fix in place there is nothing here to remove; this only cleans up after a
            // pre-fix run. rmdir never removes a non-empty directory.
            @rmdir($publicFolder);
        }
    }

    /**
     * Two fixtures that share a Folder/Filename pair but differ in another checked field: the name
     * generator renames the second file to "<name>-v2.<ext>", so the second record is stored under
     * a path the fixture's own key never spells out and the next run's lookup found neither of them
     * - every run added another copy (#113 item 3).
     */
    public function testImageDuplicateCheckReusesVersionedFileAfterNameCollision(): void
    {
        $service = new FixtureDataService();
        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $fixture = function (string $title) use ($populateFileFrom): array {
            return [
                'Title' => $title,
                'PopulateFileFrom' => $populateFileFrom,
                'Filename' => 'shared-name.png',
                'Folder' => 'VersionedTest',
                'DuplicateCheck' => ['Title', 'Filename'],
            ];
        };

        $firstA = $service->createRelatedObject(Image::class, $fixture('First fixture'));
        $this->assertNotNull($firstA, 'The first fixture should create an image.');
        $this->assertSame(
            'VersionedTest/shared-name.png',
            $firstA->getFilename(),
            'The first fixture is stored under the path it asked for.'
        );

        $firstB = $service->createRelatedObject(Image::class, $fixture('Second fixture'));
        $this->assertNotNull($firstB, 'A fixture with a different Title is a different image.');
        $this->assertNotSame(
            $firstA->ID,
            $firstB->ID,
            'The second fixture must not be handed the first fixture\'s image.'
        );
        $this->assertSame(
            'shared-name-v2.png',
            $firstB->getField('Name'),
            'File::onBeforeWrite() renames the colliding file, which is what the lookup has to allow for.'
        );

        // File::updateFilesystem() then copies the bytes to that name against a real AssetStore and
        // points FileFilename at it. TestAssetStore's back end reports no public-path conflict, so
        // it leaves the column on the old path; applying the rename here puts the record in the
        // state the lookup under test actually reads.
        $firstB->setField('FileFilename', 'VersionedTest/shared-name-v2.png');
        $firstB->write();
        $firstB->flushCache();
        $firstB = Image::get()->byID((int)$firstB->ID);
        $this->assertSame('VersionedTest/shared-name-v2.png', $firstB->getFilename());

        // Second run of the same two fixtures: neither may add another record.
        $secondA = $service->createRelatedObject(Image::class, $fixture('First fixture'));
        $secondB = $service->createRelatedObject(Image::class, $fixture('Second fixture'));

        $this->assertNotNull($secondA);
        $this->assertNotNull($secondB);
        $this->assertSame($firstA->ID, $secondA->ID, 'The fixture holding the plain path is found again.');
        $this->assertSame(
            $firstB->ID,
            $secondB->ID,
            'The fixture stored under the versioned name has to be found again, or every run adds a copy.'
        );
        $this->assertSame(2, Image::get()->count(), 'Re-running the fixtures must not add another copy.');

        $reused = Image::get()->byID((int)$secondB->ID);
        $this->assertNotNull($reused);
        $this->assertSame(
            'VersionedTest/shared-name-v2.png',
            $reused->getFilename(),
            'Reusing the versioned file must not rename or move it.'
        );
    }

    /**
     * The Filename branch has to be what decides a match: with only Filename checked, Title plays no
     * part, so a lookup that filtered nothing would still hand back "some" image - it has to be this
     * fixture's own.
     */
    public function testImageDuplicateCheckOnFilenameOnlyReusesImage(): void
    {
        $service = new FixtureDataService();
        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        // An unrelated image, created first so that an unfiltered lookup would find it.
        $seed = Image::create();
        $seed->setFromLocalFile(__DIR__ . '/placeholder.png', 'SeedFolder/seed.png');
        $seed->write();
        $this->assertSame('SeedFolder/seed.png', $seed->getFilename(), 'The seed sits on another path.');

        $data = [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'filename-only.png',
            'Folder' => 'Placeholder',
            'DuplicateCheck' => ['Filename'],
        ];

        $first = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($first, 'The first call should create an image.');
        $this->assertSame(
            'Placeholder/filename-only.png',
            $first->getFilename(),
            'Filename alone must still narrow the lookup to this fixture\'s own path.'
        );

        $second = $service->createRelatedObject(Image::class, $data);
        $this->assertNotNull($second);
        $this->assertSame($first->ID, $second->ID, 'Filename alone has to be enough to reuse the image.');
        $this->assertSame('Placeholder/filename-only.png', $second->getFilename());
        $this->assertSame(2, Image::get()->count(), 'Only one image beyond the seed should exist.');

        $seedAfter = Image::get()->byID((int)$seed->ID);
        $this->assertNotNull($seedAfter);
        $this->assertSame(
            'SeedFolder/seed.png',
            $seedAfter->getFilename(),
            'The unrelated image must not be matched, let alone moved.'
        );
    }

    /**
     * The negative side of the same lookup: a shared Title must not be enough to match. The same
     * Title in a different Folder, or under a different Filename, is a different file and has to
     * become a new record - while the same Title on the same path stays the same image.
     */
    public function testImageDuplicateCheckDoesNotMatchSameTitleInAnotherFolder(): void
    {
        $service = new FixtureDataService();
        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');
        $title = 'Shared title across folders';

        // An image that already carries this Title, so a Title-only lookup would match it from
        // any folder and under any file name.
        $seed = Image::create();
        $seed->setFromLocalFile(__DIR__ . '/placeholder.png', 'AlphaTest/shared.png');
        $seed->setField('Title', $title);
        $seed->write();

        $data = function (string $folder, string $filename) use ($populateFileFrom, $title): array {
            return [
                'Title' => $title,
                'PopulateFileFrom' => $populateFileFrom,
                'Filename' => $filename,
                'Folder' => $folder,
                'DuplicateCheck' => ['Title', 'Filename'],
            ];
        };

        $samePath = $service->createRelatedObject(Image::class, $data('AlphaTest', 'shared.png'));
        $this->assertNotNull($samePath);
        $this->assertSame(
            $seed->ID,
            $samePath->ID,
            'The same Title on the same path is the same image.'
        );

        $inBeta = $service->createRelatedObject(Image::class, $data('BetaTest', 'shared.png'));
        $otherName = $service->createRelatedObject(Image::class, $data('AlphaTest', 'other.png'));

        $this->assertNotNull($inBeta);
        $this->assertNotNull($otherName);
        $this->assertNotSame(
            $seed->ID,
            $inBeta->ID,
            'The same Title in another folder is a different file, so it must not be reused.'
        );
        $this->assertNotSame(
            $seed->ID,
            $otherName->ID,
            'The same Title with another Filename is a different file, so it must not be reused.'
        );
        $this->assertNotSame($inBeta->ID, $otherName->ID, 'Two different files must not share one record.');
        $this->assertSame('AlphaTest/shared.png', $seed->getFilename(), 'Reusing a file must not move it.');
        $this->assertSame('BetaTest/shared.png', $inBeta->getFilename());
        $this->assertSame('AlphaTest/other.png', $otherName->getFilename());
        $this->assertSame(3, Image::get()->count(), 'Three different files means three records.');
    }

    /**
     * Guard on the version-suffix tolerance: a file whose own name ends in "-v2" is not the name
     * generator's doing while nothing occupies the un-suffixed path, so a fixture for that path
     * must not be handed it.
     */
    public function testImageDuplicateCheckDoesNotMatchUnrelatedVersionedName(): void
    {
        $service = new FixtureDataService();
        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $versioned = $service->createRelatedObject(Image::class, [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'genuine-name-v2.png',
            'Folder' => 'VersionGuardTest',
            'DuplicateCheck' => ['Filename'],
        ]);
        $this->assertNotNull($versioned, 'The versioned-looking name should be created.');
        $this->assertSame(
            'VersionGuardTest/genuine-name-v2.png',
            $versioned->getFilename(),
            'Nothing occupies the plain path, so the name generator leaves this name alone.'
        );

        $plainData = [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'genuine-name.png',
            'Folder' => 'VersionGuardTest',
            'DuplicateCheck' => ['Filename'],
        ];

        $plain = $service->createRelatedObject(Image::class, $plainData);
        $this->assertNotNull($plain, 'A fixture for the un-suffixed name must still get its own file.');
        $this->assertNotSame(
            $versioned->ID,
            $plain->ID,
            'A file that was never renamed is not this fixture\'s image.'
        );
        $this->assertSame('VersionGuardTest/genuine-name.png', $plain->getFilename());

        $again = $service->createRelatedObject(Image::class, $plainData);
        $this->assertNotNull($again);
        $this->assertSame($plain->ID, $again->ID, 'The plain path is found again on the next run.');
        $this->assertSame(2, Image::get()->count(), 'Two files means two records.');

        // And the fixture whose own name carries "-v2" has to be found again too, rather than
        // adding a copy on every run.
        $versionedAgain = $service->createRelatedObject(Image::class, [
            'PopulateFileFrom' => $populateFileFrom,
            'Filename' => 'genuine-name-v2.png',
            'Folder' => 'VersionGuardTest',
            'DuplicateCheck' => ['Filename'],
        ]);
        $this->assertNotNull($versionedAgain);
        $this->assertSame(
            $versioned->ID,
            $versionedAgain->ID,
            'A fixture whose own Filename ends in "-vN" is stored under exactly that name.'
        );
        $this->assertSame(2, Image::get()->count(), 'Re-running both fixtures must not add a copy.');
    }

    /**
     * The name generator bumps a name that already carries a version to the next one, so a fixture
     * whose own Filename ends in "-vN" and collides with another fixture lands on "-v3" - a path
     * neither the fixture nor a stripped candidate spells out.
     */
    public function testImageDuplicateCheckReusesVersionedFileWhoseOwnNameCarriesVersion(): void
    {
        $service = new FixtureDataService();
        $populateFileFrom = str_replace(Director::baseFolder() . '/', '', __DIR__ . '/placeholder.png');

        $fixture = function (string $title) use ($populateFileFrom): array {
            return [
                'Title' => $title,
                'PopulateFileFrom' => $populateFileFrom,
                'Filename' => 'banner-v2.png',
                'Folder' => 'VersionedTest',
                'DuplicateCheck' => ['Title', 'Filename'],
            ];
        };

        $firstA = $service->createRelatedObject(Image::class, $fixture('Banner first'));
        $this->assertNotNull($firstA);
        $this->assertSame(
            'VersionedTest/banner-v2.png',
            $firstA->getFilename(),
            'Nothing is in the way, so the version in the fixture name is kept as-is.'
        );

        $firstB = $service->createRelatedObject(Image::class, $fixture('Banner second'));
        $this->assertNotNull($firstB);
        $this->assertNotSame($firstA->ID, $firstB->ID);
        $this->assertSame(
            'banner-v3.png',
            $firstB->getField('Name'),
            'The generator bumps the version of a name that already carries one.'
        );

        // As above: TestAssetStore reports no public-path conflict, so apply the rename that
        // File::updateFilesystem() performs against a real AssetStore.
        $firstB->setField('FileFilename', 'VersionedTest/banner-v3.png');
        $firstB->write();
        $firstB->flushCache();
        $firstB = Image::get()->byID((int)$firstB->ID);

        $secondA = $service->createRelatedObject(Image::class, $fixture('Banner first'));
        $secondB = $service->createRelatedObject(Image::class, $fixture('Banner second'));

        $this->assertNotNull($secondA);
        $this->assertNotNull($secondB);
        $this->assertSame($firstA->ID, $secondA->ID, 'The fixture holding its own "-v2" name is found again.');
        $this->assertSame($firstB->ID, $secondB->ID, 'The fixture bumped to "-v3" is found again.');
        $this->assertSame(2, Image::get()->count(), 'Re-running the fixtures must not add another copy.');
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
