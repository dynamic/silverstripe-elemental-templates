<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use Psr\Log\LoggerInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
use Dynamic\ElementalTemplates\Tests\TestOnly\TestTemplate;
use Dynamic\ElementalTemplates\Extension\CMSPageAddControllerExtension;

class CMSPageAddControllerExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'CMSPageAddControllerExtensionTest.yml';

    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        SamplePage::class,
        TestTemplate::class,
    ];

    protected static $required_extensions = [
        SamplePage::class => [
            ElementalPageExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the logger
        $mockLogger = $this->createMock(LoggerInterface::class);
        Injector::inst()->registerService($mockLogger, LoggerInterface::class);
    }

    public function testFindOrCreateElementalArea(): void
    {
        // Create a mock page with ElementalAreasExtension
        $page = $this->objFromFixture(SamplePage::class, 'testPage');
        $page->write();

        // Apply the extension
        $extension = new CMSPageAddControllerExtension();
        $extension->setOwner($page);

        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod($extension, 'findOrCreateElementalArea');
        $reflection->setAccessible(true);

        // Call findOrCreateElementalArea
        $elementalArea = $reflection->invoke($extension, $page);

        // Assert that an ElementalArea was created
        $this->assertInstanceOf(ElementalArea::class, $elementalArea);
        $this->assertTrue($elementalArea->exists());
        $this->assertEquals($page->ElementalAreaID, $elementalArea->ID);
    }

    public function testUpdateDoAdd(): void
    {
        // Create a mock page and template
        $page = $this->objFromFixture(SamplePage::class, 'testPage');
        $template = $this->objFromFixture(TestTemplate::class, 'testTemplate');

        // Mock the form
        $mockForm = $this->createMock(Form::class);
        $mockFields = $this->createMock(\SilverStripe\Forms\FieldList::class);
        $mockField = $this->createMock(\SilverStripe\Forms\FormField::class);

        $mockField->method('getValue')->willReturn($template->ID);
        $mockFields->method('dataFieldByName')->with('TemplateID')->willReturn($mockField);
        $mockForm->method('Fields')->willReturn($mockFields);

        // Apply the extension
        $extension = new CMSPageAddControllerExtension();
        $extension->setOwner($page);

        // Call updateDoAdd
        $extension->updateDoAdd($page, $mockForm);

        // Assert that the ElementalArea has elements from the template
        $elementalArea = $page->ElementalArea();
        $this->assertNotNull($elementalArea, "ElementalArea is null");
        $this->assertGreaterThan(0, $elementalArea->Elements()->count(), "ElementalArea has no elements");
    }
}
