<?php

namespace Dynamic\ElementalTemplates\Tests\Models;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Forms\ElementalAreaField;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tests\TestOnly\ElementOne;
use Dynamic\ElementalTemplates\Tests\TestOnly\ElementTwo;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePageTwo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use ReflectionException;
use ReflectionMethod;

class TemplateTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'TemplateTest.yml';
    
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        ElementOne::class,
        ElementTwo::class,
        SamplePage::class,
        SamplePageTwo::class,
    ];

    /**
     * @var string[]
     */
    protected static $required_extensions = [
        SamplePage::class => [
            ElementalPageExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
    }

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
     * @throws ReflectionException
     */
    public function testAllElementsAllowed()
    {
        // Calculate the expected number of allowed types for SamplePage
        $samplePage = singleton(SamplePage::class);
        $expectedAllowedTypesSamplePage = count($samplePage->getElementalTypes());

        // Calculate the expected number of allowed types for SamplePageTwo
        $samplePageTwo = singleton(SamplePageTwo::class);
        $expectedAllowedTypesSamplePageTwo = count($samplePageTwo->getElementalTypes());

        // Get the actual number of allowed types for templateone
        $template = $this->objFromFixture(Template::class, 'templateone');
        $allowedTypesCount = count($template->getCMSFields()->dataFieldByName('Elements')->getTypes());

        // Assert the expected number of allowed types for SamplePage
        $this->assertEquals($expectedAllowedTypesSamplePage, $allowedTypesCount);

        // Get the actual number of allowed types for templatetwo
        $templateTwo = $this->objFromFixture(Template::class, 'templatetwo');
        $allowedTypesCountTwo = count($templateTwo->getCMSFields()->dataFieldByName('Elements')->getTypes());

        // Assert the expected number of allowed types for SamplePageTwo
        $this->assertEquals($expectedAllowedTypesSamplePageTwo, $allowedTypesCountTwo);
    }

    /**
     * @return void
     */
    public function testCanCreate(): void
    {
        $userWithCreatePermission = $this->objFromFixture(Member::class, 'userWithCreatePermission');
        $userWithoutCreatePermission = $this->objFromFixture(Member::class, 'userWithoutCreatePermission');

        // Test user with create permission
        Security::setCurrentUser($userWithCreatePermission);
        $template = Template::create();
        $this->assertTrue($template->canCreate());

        // Test user without create permission
        Security::setCurrentUser($userWithoutCreatePermission);
        $template = Template::create();
        $this->assertFalse($template->canCreate());
    }

    /**
     * @return void
     */
    public function testCanEdit(): void
    {
        $userWithEditPermission = $this->objFromFixture(Member::class, 'userWithCreatePermission'); // Assuming same user has edit permission
        $userWithoutEditPermission = $this->objFromFixture(Member::class, 'userWithoutCreatePermission');

        // Test user with edit permission
        Security::setCurrentUser($userWithEditPermission);
        $template = $this->objFromFixture(Template::class, 'templateone');
        $this->assertTrue($template->canEdit());

        // Test user without edit permission
        Security::setCurrentUser($userWithoutEditPermission);
        $template = $this->objFromFixture(Template::class, 'templateone');
        $this->assertFalse($template->canEdit());
    }

    /**
     * @return void
     */
    public function testCanDelete(): void
    {
        $userWithDeletePermission = $this->objFromFixture(Member::class, 'userWithCreatePermission'); // Assuming same user has delete permission
        $userWithoutDeletePermission = $this->objFromFixture(Member::class, 'userWithoutCreatePermission');

        // Test user with delete permission
        Security::setCurrentUser($userWithDeletePermission);
        $template = $this->objFromFixture(Template::class, 'templateone');
        $this->assertTrue($template->canDelete());

        // Test user without delete permission
        Security::setCurrentUser($userWithoutDeletePermission);
        $template = $this->objFromFixture(Template::class, 'templateone');
        $this->assertFalse($template->canDelete());
    }
}
