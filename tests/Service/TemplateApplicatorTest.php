<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\AltAreaSamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
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
        AltAreaSamplePage::class,
    ];

    /**
     * @var string[]
     */
    protected static $required_extensions = [
        SamplePage::class => [
            ElementalPageExtension::class,
        ],
        AltAreaSamplePage::class => [
            ElementalPageExtension::class,
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

    /**
     * Regression coverage for issue #73: the applicator must target the record's active
     * (first visible in the CMS) elemental area rather than the hard-coded 'ElementalArea'.
     *
     * Deliberately does not call resolveAreaRelationName() itself, so it fails on the pre-fix
     * applicator through behaviour (blocks landing in the wrong area) rather than through a
     * missing method.
     */
    public function testApplyTemplateWritesIntoActiveAreaRelation()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>Active area content</p>';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $template = Template::create();
        $template->Title = 'Active Area Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        // AltAreaSamplePage hides 'ElementalArea' in the CMS, so 'ElementalHomePage' is the
        // area a content author actually edits (the HomePage shape the issue describes).
        $record = AltAreaSamplePage::create();
        $record->Title = 'Alt Area Page';
        $record->write();

        $applicator = new TemplateApplicator();

        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertTrue(
            $result['success'],
            'Expected the template to apply to the active area relation, got: '
            . ($result['message'] ?? 'no message')
        );

        $record->flushCache();
        $this->assertGreaterThan(
            0,
            $record->ElementalHomePage()->Elements()->count(),
            'Template elements should have been duplicated into the active area relation.'
        );
        $this->assertSame(
            0,
            $record->ElementalArea()->Elements()->count(),
            'The non-visible ElementalArea relation must not receive the template elements.'
        );
    }

    /**
     * The standard page shape (only 'ElementalArea' visible) must keep writing there.
     */
    public function testApplyTemplateStillWritesIntoStandardElementalArea()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>Standard area content</p>';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $template = Template::create();
        $template->Title = 'Standard Area Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        $record = SamplePage::create();
        $record->Title = 'Standard Area Page';
        $record->write();

        $applicator = new TemplateApplicator();

        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertTrue(
            $result['success'],
            'Expected the template to apply to ElementalArea, got: ' . ($result['message'] ?? 'no message')
        );

        $record->flushCache();
        $this->assertGreaterThan(
            0,
            $record->ElementalArea()->Elements()->count(),
            'Template elements should have been duplicated into ElementalArea.'
        );
    }

    /**
     * The resolver picks the first elemental relation that is visible in getCMSFields(), and
     * the standard page shape keeps resolving to 'ElementalArea'.
     */
    public function testResolveAreaRelationNamePicksFirstVisibleRelation()
    {
        $altRecord = AltAreaSamplePage::create();
        $altRecord->Title = 'Resolver Alt Page';
        $altRecord->write();

        $standardRecord = SamplePage::create();
        $standardRecord->Title = 'Resolver Standard Page';
        $standardRecord->write();

        $applicator = new TemplateApplicator();

        $this->assertSame(
            'ElementalHomePage',
            $applicator->resolveAreaRelationName($altRecord),
            'The first relation visible in getCMSFields() must win.'
        );
        $this->assertSame(
            'ElementalArea',
            $applicator->resolveAreaRelationName($standardRecord),
            'A page that only exposes ElementalArea must resolve to it.'
        );
    }

    /**
     * A record with no elemental area relation at all must resolve to the fallback name and
     * the applicator must produce a graceful failure rather than an exception.
     */
    public function testResolveAreaRelationNameFallsBackForRecordWithoutElementalRelations()
    {
        // ElementalArea has no has_one relations at all, so getElementalRelations() has nothing
        // to offer and the applicator cannot find a target area on it.
        $record = ElementalArea::create();
        $record->Title = 'Record Without Elemental Relations';
        $record->write();

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $template = Template::create();
        $template->Title = 'Fallback Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        $applicator = new TemplateApplicator();
        $this->assertSame(
            'ElementalArea',
            $applicator->resolveAreaRelationName($record),
            'A record with no elemental relations must fall back to the conventional relation name.'
        );

        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertFalse($result['success'], 'Expected failure for a record without elemental areas.');
        $this->assertStringContainsString('does not support elemental areas', $result['message']);
    }

    /**
     * An explicit relation name still wins over the resolver.
     */
    public function testApplyTemplateHonoursExplicitRelationName()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $template = Template::create();
        $template->Title = 'Explicit Relation Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        $record = AltAreaSamplePage::create();
        $record->Title = 'Explicit Relation Page';
        $record->write();

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template, 'ElementalArea');

        $this->assertTrue(
            $result['success'],
            'Expected the explicitly named relation to be used, got: ' . ($result['message'] ?? 'no message')
        );

        $record->flushCache();
        $this->assertSame(
            0,
            $record->ElementalHomePage()->Elements()->count(),
            'The resolver must be bypassed when a relation name is supplied.'
        );
    }
}
