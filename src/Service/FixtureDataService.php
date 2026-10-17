<?php

namespace Dynamic\ElementalTemplates\Service;

use Exception;
use RuntimeException;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\Core\Injector\Injector;
use Dynamic\ElementalTemplates\Extension\BaseElementDataExtension;

class FixtureDataService
{
    /**
     * The extensions importRemoteImage() can give a downloaded file whose name carries none,
     * keyed by the MIME type it sniffs from the bytes ('jpg' is also its fallback). The image
     * duplicate lookup key has to allow for the same set, or an extension-less fixture Filename
     * never matches the path the image was stored under.
     */
    private const IMPORTED_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Load and parse fixture data for a given class.
     *
     * @param string $className
     * @return array|null
     */
    public function getFixtureData(string $className): ?array
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // $logger->debug('Starting getFixtureData() for class: ' . $className);

        // Resolve the fixture path dynamically
        $fixturesPath = $this->resolveFixturePath();

        if (!$fixturesPath) {
            $logger->warning('No valid fixture path resolved.');
            return null;
        }

        if (!file_exists($fixturesPath)) {
            $logger->warning('Fixture file does not exist: ' . $fixturesPath);
            return null;
        }

        // $logger->debug('Loading fixtures from path: ' . $fixturesPath);

        try {
            // Parse the YAML file directly
            $fixtureData = Yaml::parseFile($fixturesPath);
            // $logger->debug('Fixture data parsed successfully from ' . $fixturesPath);

            // Find the fixture data for the given class
            $populateData = $fixtureData[$className] ?? null;

            if (!$populateData) {
                $logger->warning('No populate data found for class: ' . $className);
                return null;
            }

            // $logger->debug('Fixture data found for class: ' . $className);
            return $populateData;
        } catch (Exception $e) {
            $logger->error('Error parsing fixture file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the fixture path dynamically based on the environment.
     *
     * @return string|null
     */
    private function resolveFixturePath(): ?string
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Get the fixture path from the configuration
        $configuredPath = Config::inst()->get(BaseElementDataExtension::class, 'fixtures');

        if (!$configuredPath) {
            $logger->warning('No fixture path configured.');
            return null;
        }

        // Ensure the configured path is a string
        $configuredPath = (string)$configuredPath;

        // Check if the path is absolute
        if (strpos($configuredPath, '/') === 0) {
            // Absolute path
            $resolvedPath = $configuredPath;
        } else {
            // Relative path (from the project root)
            $resolvedPath = Director::baseFolder() . '/' . ltrim($configuredPath, '/');
        }

        // $logger->debug('Resolved fixture path: ' . $resolvedPath);

        return $resolvedPath;
    }

    /**
     * Populate fields for an element using fixture data.
     *
     * @param object $element
     * @return void
     */
    public function populateElementData($element): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // $logger->debug('Starting populateElementData() for class: ' . get_class($element));

        // Use getFixtureData to retrieve the fixture data for the element
        $populateData = $this->getFixtureData(get_class($element));

        if (!$populateData) {
            $logger->warning('No fixture data found for class: ' . get_class($element));
            return;
        }

        // Enhance populateElementData to handle nested relationships more robustly
        foreach ($populateData as $identifier => $fields) {
            // $logger->debug("Processing fixture identifier: $identifier");

            if (is_array($fields)) {
                foreach ($fields as $field => $value) {
                    if (is_array($value)) {
                        // Handle nested relationships
                        // $logger->debug("Processing nested relationship for field: $field");

                        $relationships = [
                            'has_one' => $element->config()->get('has_one'),
                            'has_many' => $element->config()->get('has_many'),
                            'many_many' => $element->config()->get('many_many'),
                        ];

                        foreach ($relationships as $relationType => $relationConfig) {
                            if (isset($relationConfig[$field])) {
                                $relationName = $field;
                                $relationClassName = $relationConfig[$field];

                                if ($relationType === 'has_one') {
                                    $relatedObject = $this->createRelatedObject($relationClassName, $value);
                                    if ($relatedObject) {
                                        $element->setField("{$relationName}ID", $relatedObject->ID);
                                        // $logger->debug("Set has_one relation: $relationName with ID: " . $relatedObject->ID);
                                    }
                                } elseif (in_array($relationType, ['has_many', 'many_many'])) {
                                    $element->{$relationName}()->removeAll();
                                    // $logger->debug("Cleared existing $relationType relation: $relationName before adding new objects.");
                                    foreach ($value as $relatedData) {
                                        $relatedObject = $this->createRelatedObject($relationClassName, $relatedData);
                                        if ($relatedObject) {
                                            $element->{$relationName}()->add($relatedObject);
                                            // $logger->debug("Added related $relationClassName object with ID: " . $relatedObject->ID . " to $relationType relation: $relationName");
                                        }
                                    }
                                }
                                continue;
                            }
                        }
                    } else {
                        // Handle scalar fields
                        // $logger->debug("Setting scalar field: $field with value: " . json_encode($value));
                        $element->setField($field, $value);
                    }
                }
            } else {
                // Handle scalar fields directly
                // $logger->debug("Setting scalar field: $identifier with value: " . json_encode($fields));
                $element->setField($identifier, $fields);
            }
        }

        // Log the final state of the element
        // $logger->debug('Final state of element: ' . json_encode($element->toMap()));
    }

    /**
     * Create a related object based on the class and fixture data.
     *
     * @param string $relatedClassName
     * @param array|string $data
     * @return DataObject
     */
    public function createRelatedObject(string $relatedClassName, $data): ?DataObject
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // $logger->debug("Creating related object with class: $relatedClassName and data: " . json_encode($data));

        // Validate that $data is an array
        if (!is_array($data)) {
            $logger->warning("Invalid data type for related object creation. Expected array, got: " . gettype($data));
            return null;
        }

        // Check if a specific ClassName is provided in the data
        if (isset($data['ClassName'])) {
            // $logger->debug("Overriding base class $relatedClassName with subclass " . $data['ClassName']);
            $relatedClassName = $data['ClassName'];
        } else {
            // $logger->debug("Using base class $relatedClassName for related object creation.");
        }

        // Validate that the resolved class is a valid subclass of DataObject
        if (!is_subclass_of($relatedClassName, DataObject::class)) {
            $logger->error("Invalid class: $relatedClassName is not a subclass of DataObject.");
            throw new RuntimeException("Invalid class: $relatedClassName is not a subclass of DataObject.");
        }

        // $logger->debug("Validated class: $relatedClassName as a subclass of DataObject.");

        $duplicateCheckFields = $data['DuplicateCheck'] ?? [];
        unset($data['DuplicateCheck']);

        // File subclasses store the asset path in FileFilename, not in a Filename column, and
        // reusing an existing file must never re-apply the fixture's folder-less Filename.
        if (!empty($duplicateCheckFields)) {
            $relatedIsFile = is_a($relatedClassName, File::class, true);
            $query = DataObject::get($relatedClassName);
            $filtersApplied = 0;
            foreach ($duplicateCheckFields as $field) {
                if (!isset($data[$field])) {
                    continue;
                }

                if ($relatedIsFile && $field === 'Filename' && !$relatedClassName::singleton()->hasDatabaseField('Filename')) {
                    $assetPaths = $this->assetPathCandidates((string)($data['Folder'] ?? ''), (string)$data['Filename']);
                    if (!empty($assetPaths)) {
                        $query = $query->filter('FileFilename', $assetPaths);
                        $filtersApplied++;
                    }
                    continue;
                }

                if ($relatedClassName::singleton()->hasDatabaseField($field)) {
                    $query = $query->filter($field, $data[$field]);
                    $filtersApplied++;
                }
            }

            if ($filtersApplied === 0) {
                // None of the requested DuplicateCheck fields is present in the fixture data, so the
                // query carries no filter and would match an arbitrary record of this class.
                $logger->warning("DuplicateCheck for $relatedClassName named no field present in the fixture data; skipping the lookup.");
            }

            $existingRecord = $filtersApplied > 0 ? $query->first() : null;

            if ($existingRecord) {
                // $logger->debug("Found existing record for $relatedClassName with matching fields: " . json_encode($duplicateCheckFields));

                if ($relatedIsFile) {
                    // Return the existing file untouched: update($data) would push the fixture's
                    // folder-less Filename through File::setFilename(), which resets ParentID to
                    // 0 and moves a shared image out of its folder.
                    // $logger->debug("Reusing existing file for $relatedClassName with ID: " . $existingRecord->ID);

                    return $existingRecord;
                }

                // Update the existing record with the new data
                $existingRecord->update($data);
                $existingRecord->write();
                // $logger->debug("Updated existing record for $relatedClassName with ID: " . $existingRecord->ID);

                return $existingRecord;
            }
        }

        // $logger->debug("No existing record found. Creating new $relatedClassName object.");

        // Handle Image creation
        if (is_a($relatedClassName, Image::class, true)) {
            // $logger->debug("Detected $relatedClassName class for relation. Calling createImageFromFile().");
            return $this->createImageFromFile($data);
        }

        // Handle Link creation
        if (is_a($relatedClassName, Link::class, true)) {
            // $logger->debug("Detected $relatedClassName class for relation. Calling createLinkFromData().");
            return $this->createLinkFromData($data);
        }

        // Generic DataObject creation
        // $logger->debug("Creating $relatedClassName object with data: " . json_encode($data));

        try {
            $relatedObject = $relatedClassName::create();
            $relatedObject->update($data);

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    // Handle nested relations
                    $relationClassName = $relatedObject->config()->get('has_one')[$field] ?? null;

                    if ($relationClassName && is_subclass_of($relationClassName, DataObject::class)) {
                        if (is_a($relationClassName, Image::class, true)) {
                            $nestedObject = $this->createImageFromFile($value);
                        } elseif (is_a($relationClassName, Link::class, true)) {
                            $nestedObject = $this->createLinkFromData($value);
                        } else {
                            $nestedObject = $this->createRelatedObject($relationClassName, $value);
                        }

                        if ($nestedObject) {
                            $relatedObject->setField("{$field}ID", $nestedObject->ID);
                            // $logger->debug("Created and linked nested $relationClassName object with ID: " . $nestedObject->ID . " to field: $field");
                        }
                    } else {
                        $logger->warning("Field: $field does not map to a valid has_one relation in class: $relatedClassName");
                    }
                } else {
                    // Set regular fields
                    $relatedObject->setField($field, $value);
                }
            }

            $relatedObject->write();

            // $logger->debug("Successfully created $relatedClassName object with ID: " . $relatedObject->ID);

            return $relatedObject;
        } catch (Exception $e) {
            $logger->error("Failed to create $relatedClassName object. Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper method to create an Image record based on a file path.
     *
     * @param array $data
     * @return Image|null
     */
    public function createImageFromFile(array $data): ?Image
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $populateFileFrom = $data['PopulateFileFrom'] ?? null;
        $filename = $data['Filename'] ?? null;
        $folder = $data['Folder'] ?? '';

        if (!$populateFileFrom || !$filename) {
            $logger->warning("Both PopulateFileFrom and Filename must be provided for image creation.");
            return null;
        }

        // Sanitize folder and filename
        $folder = $this->sanitiseFolder((string)$folder);
        $filename = $this->sanitiseFilename((string)$filename);

        // $logger->debug("Creating image from PopulateFileFrom: $populateFileFrom with Filename: $filename in Folder: $folder");

        // Check if PopulateFileFrom is a URL
        if (filter_var($populateFileFrom, FILTER_VALIDATE_URL)) {
            // $logger->debug("Detected URL for PopulateFileFrom. Using importRemoteImage.");
            return $this->importRemoteImage($populateFileFrom, $filename, $folder, $this->getFixtureTitle($data));
        }

        // Resolve the absolute path for PopulateFileFrom (local file)
        $absolutePath = Director::baseFolder() . '/' . ltrim($populateFileFrom, '/');

        if (!file_exists($absolutePath)) {
            $logger->warning("Image file does not exist at path: $absolutePath");
            return null;
        }

        if (!is_readable($absolutePath)) {
            $logger->warning("Image file is not readable at path: $absolutePath");
            return null;
        }

        // No directory is created under ASSETS_PATH here. setFromLocalFile() hands the path to the
        // AssetStore, which creates whatever folder it publishes into - and publishes somewhere
        // else entirely while a test store is active. Pre-creating it here only ever left empty
        // folders in the project's real assets path, and the remote branch no longer does it.
        $localPath = $this->buildAssetPath($folder, $filename);

        try {
            $image = Image::create();
            $image->setFromLocalFile($absolutePath, $localPath);
            $this->writeTitle($image, $data);
            $image->write();

            // $logger->debug("Successfully created Image record with ID: {$image->ID}");
            return $image;
        } catch (Exception $e) {
            $logger->error("Error creating Image record: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sanitise a fixture folder name the way image creation does, so a lookup key built from
     * it matches the path the image is actually stored at.
     */
    private function sanitiseFolder(string $folder): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', trim($folder, '/'));
    }

    /**
     * Sanitise a fixture filename the way image creation does.
     */
    private function sanitiseFilename(string $filename): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);
    }

    /**
     * Build the asset path a fixture image is stored at, from its fixture Folder and Filename.
     * Filtered the way File::setFilename() filters, so a lookup key built here matches the
     * FileFilename the record is actually stored under.
     */
    private function buildAssetPath(string $folder, string $filename): string
    {
        $folder = $this->sanitiseFolder($folder);
        $filename = $this->sanitiseFilename($filename);

        if ($folder === '' || $filename === '') {
            $path = $folder . $filename;
        } else {
            $path = "$folder/$filename";
        }

        $filtered = $this->filterAssetPath($path);

        return $filtered !== '' ? $filtered : $path;
    }

    /**
     * Every FileFilename a fixture's Folder/Filename pair can end up stored under.
     *
     * importRemoteImage() appends an extension when the fixture Filename carries none: it sniffs
     * the downloaded bytes and falls back to 'jpg'. A lookup key built from the raw, extension-less
     * name would never match that stored path, so every run created another copy of the image.
     * Allowing for each extension it might pick keeps the key in step with the creation path; a
     * Filename that already has an extension still yields exactly one candidate.
     *
     * @return string[]
     */
    private function assetPathCandidates(string $folder, string $filename): array
    {
        $path = $this->buildAssetPath($folder, $filename);

        if ($path === '' || pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return $path === '' ? [] : [$path];
        }

        // The default ('jpg', first below) plus every extension the MIME map can produce.
        $extensions = array_values(array_unique(array_values(self::IMPORTED_EXTENSIONS) + ['jpg']));
        $candidates = [];
        foreach ($extensions as $extension) {
            $candidate = $this->buildAssetPath($folder, $filename . '.' . $extension);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Run each segment of an asset path through SilverStripe's FileNameFilter, mirroring
     * File::filterFilename() (which is protected). The filter collapses repeated separators
     * and strips leading dots, dashes and underscores, so an unfiltered key would not match.
     */
    private function filterAssetPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $filter = FileNameFilter::create();
        // Mirrors File::getFilePathParts(): a "0" segment is a legitimate folder name.
        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));

        return implode('/', array_map(fn ($segment) => $filter->filter($segment), $segments));
    }

    /**
     * The fixture's Title, if it supplied one.
     *
     * @param array $data
     */
    private function getFixtureTitle(array $data): ?string
    {
        $title = $data['Title'] ?? null;

        if ($title === null || $title === '') {
            return null;
        }

        return (string)$title;
    }

    /**
     * Write the fixture Title onto a newly created image, so a DuplicateCheck on Title can
     * match it. File::onBeforeWrite() only derives a Title from the file name when none is set.
     *
     * @param Image $image
     * @param array $data
     */
    private function writeTitle(Image $image, array $data): void
    {
        $title = $this->getFixtureTitle($data);

        if ($title !== null) {
            $image->setField('Title', $title);
        }
    }

    /**
     * Helper method to create a Link record based on the provided data.
     *
     * @param array $data
     * @return Link|null
     */
    private function createLinkFromData($data): ?Link
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // $logger->debug("Creating Link record with data: " . json_encode($data));

        // Ensure $data is an array
        if (!is_array($data)) {
            $logger->warning("Invalid data type for Link creation. Expected array, got: " . gettype($data));
            return null;
        }

        // Determine the class to use for the Link
        $className = $data['ClassName'] ?? Link::class;

        // Validate that the class is a subclass of Link
        if (!is_a($className, Link::class, true)) {
            $logger->warning("Invalid Link class: $className");
            return null;
        }

        // $logger->debug("Creating Link record of class: $className");

        // Create a new Link record
        /** @var Link $link */
        $link = $className::create();

        foreach ($data as $field => $value) {
            if ($field !== 'ClassName') {
                $link->$field = $value;
            }
        }

        $link->write();

        // $logger->debug("Created Link record with ID: {$link->ID} and ClassName: $className");

        return $link;
    }

    // Add debugging to importRemoteImage to log its behavior
    public function importRemoteImage($url, $filename = null, $folder = '', ?string $title = null)
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        // $logger->debug("Starting importRemoteImage for URL: $url");

        // Get file contents from remote URL
        $contents = @file_get_contents($url);
        if (!$contents) {
            $logger->warning("Failed to download file from URL: $url");
            return null;
        }

        // Determine file name and extension
        $parsedUrl = parse_url($url);
        $basename = basename($parsedUrl['path']);
        $filename = $filename ?: $basename;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Handle missing file extensions in importRemoteImage
        if (!$extension) {
            $logger->warning("File extension is missing for URL: $url. Attempting to infer from MIME type.");

            // Use finfo to determine the MIME type of the file
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);

            // Map MIME type to file extension
            $extension = self::IMPORTED_EXTENSIONS[$mimeType] ?? 'jpg'; // Default to jpg if MIME type is unknown
            // $logger->debug("Inferred file extension: $extension for MIME type: $mimeType");

            // Append the inferred extension to the filename
            $filename .= ".$extension";
        }

        // Ensure filename is safe
        $filename = $this->sanitiseFilename((string)$filename);

        $folder = $this->sanitiseFolder((string)$folder);
        $localPath = $this->buildAssetPath($folder, $filename);

        // Log the URL and filename
        // $logger->debug("URL: $url, Filename: $filename");

        // Stage the download in a temp file outside the assets folder. Writing it to
        // ASSETS_PATH/<Folder>/<Filename> would land on the path an already published asset
        // occupies; handed to the AssetStore instead, the new file is stored under its own hash
        // and File::onBeforeWrite() gives the record a non-conflicting Name.
        // A unique name in the system temp dir, with the source extension kept so the store
        // can still detect the mime type.
        $tempPath = sys_get_temp_dir() . '/ss_fixture_import_' . bin2hex(random_bytes(8)) . '.' . $extension;

        if (file_put_contents($tempPath, $contents) === false) {
            $logger->warning("Failed to stage downloaded file from URL: $url");
            @unlink($tempPath);
            return null;
        }

        // Use AssetStore to create the file record
        $image = Image::create();

        try {
            $image->setFromLocalFile($tempPath, $localPath);
        } finally {
            // The AssetStore copied the bytes into its own store; the staged file is ours to clean up.
            @unlink($tempPath);
        }

        if ($title !== null && $title !== '') {
            $image->setField('Title', $title);
        }

        try {
            $image->write();
            // $logger->debug("Successfully created Image record with ID: {$image->ID}");
        } catch (Exception $e) {
            $logger->warning("Failed to write image record: " . $e->getMessage());
            return null;
        }

        return $image;
    }
}
