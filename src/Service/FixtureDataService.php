<?php

namespace Dynamic\ElementalTemplates\Service;

use Exception;
use RuntimeException;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Assets\Storage\AssetStore;
use Dynamic\ElememtalTemplates\Extension\BaseElementDataExtension;

class FixtureDataService
{
    /**
     * Load and parse fixture data for a given class.
     *
     * @param string $className
     * @return array|null
     */
    public function getFixtureData(string $className): ?array
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $logger->debug('Starting getFixtureData() for class: ' . $className);

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

        $logger->debug('Loading fixtures from path: ' . $fixturesPath);

        try {
            // Parse the YAML file directly
            $fixtureData = Yaml::parseFile($fixturesPath);
            $logger->debug('Fixture data parsed successfully from ' . $fixturesPath);

            // Find the fixture data for the given class
            $populateData = $fixtureData[$className] ?? null;

            if (!$populateData) {
                $logger->warning('No populate data found for class: ' . $className);
                return null;
            }

            $logger->debug('Fixture data found for class: ' . $className);
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

        $logger->debug('Resolved fixture path: ' . $resolvedPath);

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

        $logger->debug('Starting populateElementData() for class: ' . get_class($element));

        // Use getFixtureData to retrieve the fixture data for the element
        $populateData = $this->getFixtureData(get_class($element));

        if (!$populateData) {
            $logger->warning('No fixture data found for class: ' . get_class($element));
            return;
        }

        // Enhance populateElementData to handle nested relationships more robustly
        foreach ($populateData as $identifier => $fields) {
            $logger->debug("Processing fixture identifier: $identifier");

            if (is_array($fields)) {
                foreach ($fields as $field => $value) {
                    if (is_array($value)) {
                        // Handle nested relationships
                        $logger->debug("Processing nested relationship for field: $field");

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
                                        $logger->debug("Set has_one relation: $relationName with ID: " . $relatedObject->ID);
                                    }
                                } elseif (in_array($relationType, ['has_many', 'many_many'])) {
                                    $element->{$relationName}()->removeAll();
                                    $logger->debug("Cleared existing $relationType relation: $relationName before adding new objects.");
                                    foreach ($value as $relatedData) {
                                        $relatedObject = $this->createRelatedObject($relationClassName, $relatedData);
                                        if ($relatedObject) {
                                            $element->{$relationName}()->add($relatedObject);
                                            $logger->debug("Added related $relationClassName object with ID: " . $relatedObject->ID . " to $relationType relation: $relationName");
                                        }
                                    }
                                }
                                continue;
                            }
                        }
                    } else {
                        // Handle scalar fields
                        $logger->debug("Setting scalar field: $field with value: " . json_encode($value));
                        $element->setField($field, $value);
                    }
                }
            } else {
                // Handle scalar fields directly
                $logger->debug("Setting scalar field: $identifier with value: " . json_encode($fields));
                $element->setField($identifier, $fields);
            }
        }
        // Log the final state of the element
        $logger->debug('Final state of element: ' . json_encode($element->toMap()));
    }

    /**
     * Create a related object based on the class and fixture data.
     *
     * @param string $relatedClassName
     * @param array|string $data
     * @return DataObject
     */
    private function createRelatedObject(string $relatedClassName, $data): ?DataObject
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $logger->debug("Creating related object with class: $relatedClassName and data: " . json_encode($data));

        // Validate that $data is an array
        if (!is_array($data)) {
            $logger->warning("Invalid data type for related object creation. Expected array, got: " . gettype($data));
            return null;
        }

        // Check if a specific ClassName is provided in the data
        if (isset($data['ClassName'])) {
            $logger->debug("Overriding base class $relatedClassName with subclass " . $data['ClassName']);
            $relatedClassName = $data['ClassName'];
        } else {
            $logger->debug("Using base class $relatedClassName for related object creation.");
        }

        // Validate that the resolved class is a valid subclass of DataObject
        if (!is_subclass_of($relatedClassName, DataObject::class)) {
            $logger->error("Invalid class: $relatedClassName is not a subclass of DataObject.");
            throw new RuntimeException("Invalid class: $relatedClassName is not a subclass of DataObject.");
        }

        $logger->debug("Validated class: $relatedClassName as a subclass of DataObject.");

        // Handle Image creation
        if (is_a($relatedClassName, Image::class, true)) {
            $logger->debug("Detected $relatedClassName class for relation. Calling createImageFromFile().");
            return $this->createImageFromFile($data);
        }

        // Handle Link creation
        if (is_a($relatedClassName, Link::class, true)) {
            $logger->debug("Detected $relatedClassName class for relation. Calling createLinkFromData().");
            return $this->createLinkFromData($data);
        }

        // Generic DataObject creation
        $logger->debug("Creating $relatedClassName object with data: " . json_encode($data));

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
                            $logger->debug("Created and linked nested $relationClassName object with ID: " . $nestedObject->ID . " to field: $field");
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

            $logger->debug("Successfully created $relatedClassName object with ID: " . $relatedObject->ID);

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

        if (!$populateFileFrom || !$filename) {
            $logger->warning("Both PopulateFileFrom and Filename must be provided for image creation.");
            return null;
        }

        $logger->debug("Creating image from PopulateFileFrom: $populateFileFrom with Filename: $filename");

        // Check if PopulateFileFrom is a URL
        if (filter_var($populateFileFrom, FILTER_VALIDATE_URL)) {
            $logger->debug("Detected URL for PopulateFileFrom. Using importRemoteImage.");
            return $this->importRemoteImage($populateFileFrom, $filename, $folder = null);
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

        try {
            $image = Image::create();
            $image->setFromLocalFile($absolutePath, $filename);
            $image->write();

            $logger->debug("Successfully created Image record with ID: {$image->ID}");
            return $image;
        } catch (Exception $e) {
            $logger->error("Error creating Image record: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper method to create a Link record based on the provided data.
     *
     * @param array $linkData
     * @return Link|null
     */
    private function createLinkFromData($data): ?Link
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $logger->debug("Creating Link record with data: " . json_encode($data));

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

        $logger->debug("Creating Link record of class: $className");

        // Create a new Link record
        /** @var Link $link */
        $link = $className::create();

        foreach ($data as $field => $value) {
            if ($field !== 'ClassName') {
                $link->$field = $value;
            }
        }

        $link->write();

        $logger->debug("Created Link record with ID: {$link->ID} and ClassName: $className");

        return $link;
    }

    // Add debugging to importRemoteImage to log its behavior
    public function importRemoteImage($url, $filename = null, $folder = '')
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->debug("Starting importRemoteImage for URL: $url");

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
            $mimeToExtension = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
            ];

            $extension = $mimeToExtension[$mimeType] ?? 'jpg'; // Default to jpg if MIME type is unknown
            $logger->debug("Inferred file extension: $extension for MIME type: $mimeType");

            // Append the inferred extension to the filename
            $filename .= ".$extension";
        }

        // Ensure filename is safe
        $filename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);

        // Generate target path in assets
        if ($folder) {
            $folder = trim($folder, '/');
            $targetFolder = $folder ? ASSETS_PATH . "/$folder" : ASSETS_PATH;
            if (!file_exists($targetFolder)) {
                mkdir($targetFolder, 0755, true);
            }
        }

        $localPath = $folder ? "$folder/$filename" : $filename;
        $fullLocalPath = ASSETS_PATH . "/$localPath";

        // Log the URL and filename
        $logger->debug("URL: $url, Filename: $filename");

        // Log the folder where the file will be saved
        $logger->debug("Target folder: $targetFolder");

        // Log the full local path of the file
        $logger->debug("Full local path: $fullLocalPath");

        // Log the result of file_put_contents
        if (file_put_contents($fullLocalPath, $contents)) {
            $logger->debug("File successfully saved to: $fullLocalPath");
        } else {
            $logger->warning("Failed to save file to: $fullLocalPath");
        }

        // Use AssetStore to create the file record
        $store = Injector::inst()->get(AssetStore::class);
        $image = Image::create();
        $image->setFromLocalFile($fullLocalPath, $localPath);

        try {
            $image->write();
            $logger->debug("Successfully created Image record with ID: {$image->ID}");
        } catch (ValidationException $e) {
            $logger->warning("Failed to write image record: " . $e->getMessage());
            return null;
        }

        return $image;
    }
}
