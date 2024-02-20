<?php

namespace Dynamic\ElememtalTemplates\Tests\Models;

use DNADesign\Elemental\Forms\ElementalAreaField;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\ElememtalTemplates\Tests\TestOnly\ElementOne;
use Dynamic\ElememtalTemplates\Tests\TestOnly\ElementTwo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;
use ReflectionException;

class TemplateTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'TemplateTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        ElementOne::class,
        ElementTwo::class,
    ];

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testGetCMSFields()
    {
        $template = new Template();
        $fields = $template->getCMSFields();

        $this->assertInstanceOf(FormField::class, $fields->dataFieldByName('Title'));
        $this->assertInstanceOf(DropdownField::class, $fields->dataFieldByName('PageType'));

        $existingTemplate = $this->objFromFixture(Template::class, 'templateone');
        $existingFields = $existingTemplate->getCMSFields();

        $this->assertInstanceOf(ElementalAreaField::class, $existingFields->dataFieldByName('Elements'));
    }

    /**
     * @return void
     */
    public function testAllElementsAllowed()
    {
        $template = $this->objFromFixture(Template::class, 'templateone');
        $allowedTypesCount = count($template->getCMSFields()->dataFieldByName('Elements')->getTypes());

        $this->assertEquals(3, $allowedTypesCount);

        $templateTwo = $this->objFromFixture(Template::class, 'templatetwo');
        $allowedTypesCountTwo = count($templateTwo->getCMSFields()->dataFieldByName('Elements')->getTypes());

        $this->assertEquals(2, $allowedTypesCountTwo);
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
