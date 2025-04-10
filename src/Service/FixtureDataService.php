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

        // Get the fixture path from the configuration
        $configuredPath = Config::inst()->get(BaseElementDataExtension::class, 'fixtures');

        if (!$configuredPath) {
            $logger->warning('No fixture path configured.');
            return null;
        }

        // Ensure the configured path is a string
        $configuredPath = (string)$configuredPath;

        // Resolve the path relative to the project root
        $fixturesPath = Director::baseFolder() . '/' . ltrim($configuredPath, '/');

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
        } catch (\Exception $e) {
            $logger->error('Error parsing fixture file: ' . $e->getMessage());
            return null;
        }
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

        foreach ($populateData as $identifier => $fields) {
            $logger->debug("Processing fixture identifier: $identifier");

            foreach ($fields as $field => $value) {
                if ($value === '') {
                    $logger->debug("Setting field: $field to null because the value is empty.");
                    $element->setField($field, null);
                    continue;
                }

                // Check if the field exists as a static $db property
                $dbFields = $element->config()->get('db');
                if (isset($dbFields[$field])) {
                    $logger->debug("Setting field: $field with value: " . json_encode($value));
                    $element->setField($field, $value);
                    continue;
                }

                // Check if the field is a relationship
                $hasOne = $element->config()->get('has_one');
                $hasMany = $element->config()->get('has_many');
                $manyMany = $element->config()->get('many_many');

                $relationName = null;
                $relationClassName = null;

                if (isset($hasOne[$field])) {
                    $relationName = $field;
                    $relationClassName = $hasOne[$field];
                } elseif (isset($hasMany[$field])) {
                    $relationName = $field;
                    $relationClassName = $hasMany[$field];
                } elseif (isset($manyMany[$field])) {
                    $relationName = $field;
                    $relationClassName = $manyMany[$field];
                }

                if ($relationName && $relationClassName) {
                    $logger->debug("Processing relation: $relationName with class: $relationClassName");

                    if (is_array($value)) {
                        // Handle nested data for has_one relationships
                        $relatedObject = $this->createRelatedObject($relationClassName, $value);

                        if ($relatedObject) {
                            $element->setField("{$relationName}ID", $relatedObject->ID);
                            $logger->debug("Created related $relationClassName object with ID: " . $relatedObject->ID);
                        }
                    } else {
                        $logger->warning("Field: $field is not a valid nested array for relation: $relationName.");
                    }
                } else {
                    $logger->warning("Field: $field is not a valid db field or relation.");
                }
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
            return $this->createImageFromFile($data['Filename'] ?? '');
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
                            $nestedObject = $this->createImageFromFile($value['Filename'] ?? '');
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
     * @param string $filePath
     * @return Image|null
     */
    private function createImageFromFile(string $filePath): ?Image
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        // Resolve the absolute path
        $absolutePath = Director::baseFolder() . '/' . ltrim($filePath, '/');
        $logger->debug("Attempting to create Image from file: $absolutePath");

        // Check if the file exists
        if (!file_exists($absolutePath)) {
            $logger->warning("Image file does not exist: $absolutePath");
            return null;
        }

        try {
            // Create a new Image record
            $image = Image::create();
            $image->Filename = $filePath;
            $image->setFromLocalFile($absolutePath, basename($filePath));
            $image->write();

            $logger->debug("Created Image record with ID: {$image->ID} for file: $filePath");

            return $image;
        } catch (Exception $e) {
            $logger->error("Failed to create Image record for file: $filePath. Error: " . $e->getMessage());
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
}
