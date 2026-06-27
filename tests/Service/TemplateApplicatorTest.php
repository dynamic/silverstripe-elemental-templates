<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use SilverStripe\Versioned\Versioned;

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
        $this->assertTrue($result['success'], 'Expected successful template application but got: ' . ($result['message'] ?? 'no message'));
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

    public function testApplyTemplateMaterializesMissingElementalArea()
    {
        // Create test data programmatically
        $validElementalArea = ElementalArea::create();
        $validElementalArea->Title = 'Valid Elemental Area';
        $validElementalArea->write();

        // Add an element so we can confirm it is duplicated into the materialized area
        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>This is template content</p>';
        $templateElement->ParentID = $validElementalArea->ID;
        $templateElement->write();

        $validTemplate = Template::create();
        $validTemplate->Title = 'Valid Template';
        $validTemplate->ElementsID = $validElementalArea->ID;
        $validTemplate->write();

        // Apply Template runs in the CMS DRAFT context; elemental only materializes
        // areas while reading the draft stage (allowAlteringElementalArea()).
        Versioned::set_stage(Versioned::DRAFT);

        // Persist the page, then simulate a page whose elemental area was never
        // materialized (ElementalAreaID = 0) — issue #63. Set it in memory only so the
        // applicator is the one that has to create the area.
        $record = SamplePage::create();
        $record->Title = 'Test Page No Elements';
        $record->write();
        $record->ElementalAreaID = 0;

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        // The applicator should materialize the area and apply the template in place.
        $this->assertTrue(
            $result['success'],
            'Expected the applicator to materialize the missing area, got: ' . ($result['message'] ?? 'no message')
        );
        $this->assertGreaterThan(0, $record->ElementalAreaID, 'Elemental area should have been created.');
        $this->assertGreaterThan(
            0,
            $record->ElementalArea()->Elements()->count(),
            'Template elements should have been duplicated into the materialized area.'
        );
    }

    public function testApplyTemplateFailsWhenAreaCannotBeMaterialized()
    {
        $validElementalArea = ElementalArea::create();
        $validElementalArea->Title = 'Valid Elemental Area';
        $validElementalArea->write();

        $validTemplate = Template::create();
        $validTemplate->Title = 'Valid Template';
        $validTemplate->ElementsID = $validElementalArea->ID;
        $validTemplate->write();

        // Outside the DRAFT stage the elemental area is not auto-created on write, so the
        // applicator cannot materialize one and must return a graceful failure rather than
        // proceeding without an area.
        Versioned::set_stage(Versioned::LIVE);

        $record = SamplePage::create();
        $record->Title = 'Live Stage Page';
        $record->write();
        $record->ElementalAreaID = 0;

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        $this->assertFalse($result['success'], 'Expected failure when the area cannot be materialized.');
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }
}
