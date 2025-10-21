<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;

class TemplateApplicatorTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        SamplePage::class,
    ];

    /**
     * @var string[]
     */
    protected static $required_extensions = [
        SamplePage::class => [
            \DNADesign\Elemental\Extensions\ElementalPageExtension::class,
        ],
    ];

    public function testApplyTemplateToRecordSuccess()
    {
        // Create test data programmatically instead of using fixtures
        $validElementalArea = ElementalArea::create();
        $validElementalArea->Title = 'Valid Elemental Area';
        $validElementalArea->write();

        // Add an element to the valid template
        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>This is template content</p>';
        $templateElement->ParentID = $validElementalArea->ID;
        $templateElement->write();

        $pageElementalArea = ElementalArea::create();
        $pageElementalArea->Title = 'Page Elemental Area';
        $pageElementalArea->write();

        $validTemplate = Template::create();
        $validTemplate->Title = 'Valid Template';
        $validTemplate->ElementsID = $validElementalArea->ID;
        $validTemplate->write();

        // Test that applying a valid template to a valid record succeeds
        $record = SamplePage::create();
        $record->Title = 'Test Page';
        $record->ElementalAreaID = $pageElementalArea->ID;
        $record->write();

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        // Should succeed when both template and record have valid elemental areas
        $this->assertTrue($result['success'], 'Result message: ' . ($result['message'] ?? 'no message'));
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }

    public function testApplyTemplateToRecordInvalidTemplate()
    {
        // Create test data programmatically
        $invalidTemplate = Template::create();
        $invalidTemplate->Title = 'Invalid Template';
        // Do NOT write() - Template 'owns' Elements, so write() would auto-create ElementalArea
        // We want to test with no ElementsID to validate proper error handling

        $pageElementalArea = ElementalArea::create();
        $pageElementalArea->Title = 'Page Elemental Area';
        $pageElementalArea->write();

        $record = SamplePage::create();
        $record->Title = 'Test Page';
        $record->write();
        $record->ElementalAreaID = $pageElementalArea->ID;
        $record->write();

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $invalidTemplate);

        // Should fail because the template has no elemental area
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }

    public function testApplyTemplateToRecordNoElementalArea()
    {
        // Create test data programmatically
        $validElementalArea = ElementalArea::create();
        $validElementalArea->Title = 'Valid Elemental Area';
        $validElementalArea->write();

        $validTemplate = Template::create();
        $validTemplate->Title = 'Valid Template';
        $validTemplate->ElementsID = $validElementalArea->ID;
        $validTemplate->write();

        $record = SamplePage::create();
        $record->Title = 'Test Page No Elements';
        // Do NOT write() - Page 'owns' ElementalArea, so write() would auto-create it
        // We want to test with no ElementalAreaID to validate proper error handling

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        // Should fail because the record doesn't support or have elemental areas
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }
}
