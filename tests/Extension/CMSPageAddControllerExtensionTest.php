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

    public function testUpdateFieldsInsertsGroupedTemplateDropdownAfterRecordType(): void
    {
        $template = $this->objFromFixture(TestTemplate::class, 'testTemplate');
        $template->Category = 'Heroes';
        $template->write();

        // SS6 add form: the page-type field is named "RecordType".
        $fields = new \SilverStripe\Forms\FieldList(
            new \SilverStripe\Forms\HiddenField('RecordType')
        );

        $extension = new CMSPageAddControllerExtension();
        $extension->updateFields($fields);

        $field = $fields->dataFieldByName('TemplateID');
        $this->assertInstanceOf(\SilverStripe\Forms\GroupedDropdownField::class, $field);

        // Must sit immediately after RecordType (as "Step 3").
        $names = array_map(fn ($f) => $f->getName(), $fields->dataFields());
        $this->assertSame(
            array_search('TemplateID', array_values($names), true),
            array_search('RecordType', array_values($names), true) + 1
        );

        $source = $field->getSource();
        $this->assertArrayHasKey('Heroes', $source);
        $this->assertContains($template->Title, $source['Heroes']);
    }

    public function testUpdateFieldsIsIdempotent(): void
    {
        $fields = new \SilverStripe\Forms\FieldList(
            new \SilverStripe\Forms\HiddenField('RecordType')
        );

        $extension = new CMSPageAddControllerExtension();
        $extension->updateFields($fields);
        $extension->updateFields($fields);

        // A second pass must not add a duplicate TemplateID field.
        $matching = array_filter(
            $fields->dataFields(),
            fn ($f) => $f->getName() === 'TemplateID'
        );
        $this->assertCount(1, $matching);
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
        $page = $this->objFromFixture(SamplePage::class, 'testPage');
        $template = $this->objFromFixture(TestTemplate::class, 'testTemplate');

        // The selected TemplateID comes off the POST request, reached via
        // $form->getController()->getRequest()->postVar('TemplateID').
        $request = new \SilverStripe\Control\HTTPRequest('POST', '/', [], ['TemplateID' => $template->ID]);
        $controller = new \SilverStripe\Control\Controller();
        $controller->setRequest($request);

        $mockForm = $this->createMock(Form::class);
        $mockForm->method('getController')->willReturn($controller);

        $extension = new CMSPageAddControllerExtension();
        $extension->setOwner($page);
        $extension->updateDoAdd($page, $mockForm);

        $elementalArea = $page->ElementalArea();
        $this->assertNotNull($elementalArea, 'ElementalArea is null');
        $this->assertGreaterThan(0, $elementalArea->Elements()->count(), 'ElementalArea has no elements');
    }

    public function testUpdateDoAddWithoutTemplateIdIsNoOp(): void
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        // No TemplateID in the request: the page must be left without blocks.
        $request = new \SilverStripe\Control\HTTPRequest('POST', '/', [], []);
        $controller = new \SilverStripe\Control\Controller();
        $controller->setRequest($request);

        $mockForm = $this->createMock(Form::class);
        $mockForm->method('getController')->willReturn($controller);

        $before = $page->ElementalArea()->Elements()->count();

        $extension = new CMSPageAddControllerExtension();
        $extension->setOwner($page);
        $extension->updateDoAdd($page, $mockForm);

        // No template selected: element count must be unchanged.
        $this->assertSame($before, $page->ElementalArea()->Elements()->count());
    }
}
