<?php

namespace DNADesign\ElementalSkeletons\Tests\Models;

use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\ElementalSkeletons\Models\Skeleton;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;
use ReflectionException;

class SkeletonTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'SkeletonTest.yml';

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetCMSFields()
    {
        $skeleton = new Skeleton();
        $fields = $skeleton->getCMSFields();

        $this->assertInstanceOf(FormField::class, $fields->dataFieldByName('Title'));
        $this->assertInstanceOf(DropdownField::class, $fields->dataFieldByName('PageType'));

        $existingSkeleton = $this->objFromFixture(Skeleton::class, 'skeletonone');
        $existingFields = $existingSkeleton->getCMSFields();

        $this->assertInstanceOf(ElementalAreaField::class, $existingFields->dataFieldByName('Elements'));
    }

    /**
     * @return void
     */
    public function testCanCreate(): void
    {
        $this->markTestSkipped('TODO - Implement testCanCreate');
    }

    /**
     * @return void
     */
    public function testCanEdit(): void
    {
        $this->markTestSkipped('TODO - Implement testCanEdit');
    }

    /**
     * @return void
     */
    public function testCanDelete(): void
    {
        $this->markTestSkipped('TODO - Implement testCanDelete');
    }
}
