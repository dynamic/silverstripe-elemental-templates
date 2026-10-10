<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\AltAreaSamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\OnlyAltArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
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
        OnlyAltArea::class,
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
        // Deliberately not ElementalPageExtension: that would add the 'ElementalArea' relation this
        // fixture exists to be without.
        OnlyAltArea::class => [
            ElementalAreasExtension::class,
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
     * Applying to a record that has not been written yet must still target the visible area.
     *
     * No area field exists before the record is in the database, and getElementalRelations() lists
     * the relation the extension contributes ('ElementalArea') before the one the page declares, so
     * resolving straight away would pick the area a member of content authors cannot see.
     */
    public function testApplyTemplateWritesIntoActiveAreaRelationForUnsavedRecord()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>Unsaved record content</p>';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $template = Template::create();
        $template->Title = 'Unsaved Record Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        // Deliberately not written: this is the shape of a page handed to the applicator by a
        // caller that has only just created it.
        $record = AltAreaSamplePage::create();
        $record->Title = 'Unsaved Alt Area Page';
        $this->assertFalse($record->isInDb(), 'Precondition: the record should not be in the database yet.');

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertTrue(
            $result['success'],
            'Expected the template to apply to an unsaved record, got: ' . ($result['message'] ?? 'no message')
        );

        $record->flushCache();
        $this->assertGreaterThan(
            0,
            $record->ElementalHomePage()->Elements()->count(),
            'Template elements should have been duplicated into the visible area relation.'
        );
        $this->assertSame(
            0,
            $record->ElementalArea()->Elements()->count(),
            'The area the page hides in the CMS must not receive the template elements.'
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
     * A record that exposes no elemental relation at all must resolve to the fallback name and the
     * applicator must produce a graceful failure rather than an exception.
     *
     * ElementalArea is the record type used here because it has no elemental relation *method*: it
     * exercises the hasMethod('getElementalRelations') guard. The "relations exist but none is
     * visible" branch is covered separately by
     * testResolveAreaRelationNameFallsBackToFirstDeclaredRelation.
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
        $result = $applicator->applyTemplateToRelation($record, $template, 'ElementalArea');

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
        $this->assertGreaterThan(
            0,
            $record->ElementalArea()->Elements()->count(),
            'The explicitly named relation is the one that should have received the elements.'
        );
    }

    /**
     * An explicit relation name is called as a method, so anything that is not one of the record's
     * elemental area relations is refused before it is called: a has_one that is not an area, an
     * unknown name, and a side-effecting method.
     */
    public function testApplyTemplateRefusesNonElementalRelationName()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->write();

        $template = Template::create();
        $template->Title = 'Relation Guard Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        $record = AltAreaSamplePage::create();
        $record->Title = 'Relation Guard Page';
        $record->write();

        $applicator = new TemplateApplicator();
        foreach (['Parent', 'NoSuchRelation', 'doArchive'] as $relationName) {
            $result = $applicator->applyTemplateToRelation($record, $template, $relationName);

            $this->assertFalse($result['success'], "'{$relationName}' must be refused.");
            $this->assertStringContainsString('is not an elemental area relation', $result['message']);
        }

        $this->assertNotNull(
            AltAreaSamplePage::get()->byID($record->ID),
            "The 'doArchive' method must not have been called on the record."
        );

        // A real elemental relation other than the default still passes the check.
        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Relation Guard Element';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $result = $applicator->applyTemplateToRelation($record, $template, 'ElementalHomePage');
        $this->assertTrue($result['success'], 'ElementalHomePage must be accepted: ' . $result['message']);
        $record->flushCache();
        $this->assertSame(1, $record->ElementalHomePage()->Elements()->count());
    }

    /**
     * A record whose conventional 'ElementalArea' relation does not exist at all must still resolve
     * to the area it really has - both before it is written (no CMS area field exists yet, because
     * ElementalAreasExtension::updateCMSFields() only builds one once the record is in the database)
     * and after.
     */
    public function testResolveAreaRelationNameFallsBackToFirstDeclaredRelation()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Area';
        $templateArea->write();

        $templateElement = \DNADesign\Elemental\Models\ElementContent::create();
        $templateElement->Title = 'Template Content Element';
        $templateElement->HTML = '<p>Only-alt-area content</p>';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $template = Template::create();
        $template->Title = 'Only Alt Area Template';
        $template->ElementsID = $templateArea->ID;
        $template->write();

        $applicator = new TemplateApplicator();

        // Not yet in the database: getCMSFields() has no area field for any relation, so the
        // resolver has to use the relation the record declares rather than assume 'ElementalArea'.
        $unsaved = OnlyAltArea::create();
        $unsaved->Title = 'Unsaved Only Alt Area Page';
        $this->assertSame(
            'ElementalHomePage',
            $applicator->resolveAreaRelationName($unsaved),
            'An unsaved record must resolve to the relation it declares, not to a name it lacks.'
        );

        // Once it is in the database the same relation is the visible one, and the template's blocks
        // land there instead of failing with "does not support elemental areas".
        $record = OnlyAltArea::create();
        $record->Title = 'Only Alt Area Page';
        $record->write();

        $this->assertSame(
            'ElementalHomePage',
            $applicator->resolveAreaRelationName($record)
        );

        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertTrue(
            $result['success'],
            'Expected the template to apply to the only area the record has, got: '
            . ($result['message'] ?? 'no message')
        );

        $record->flushCache();
        $this->assertGreaterThan(
            0,
            $record->ElementalHomePage()->Elements()->count(),
            'Template elements should have been duplicated into the record\'s only area.'
        );
    }

    /**
     * The Injector-resolved applicator can be subclassed with the two-argument signature
     * applyTemplateToRecord() has always had; a wider parent signature would be a fatal at class load.
     */
    public function testSubclassOverridingTheTwoArgumentMethodStillLoadsAndIsCalled()
    {
        $subclass = new class () extends TemplateApplicator {
            public function applyTemplateToRecord(DataObject $record, Template $template): array
            {
                return ['success' => true, 'message' => 'overridden'];
            }
        };

        $this->assertSame(
            'overridden',
            $subclass->applyTemplateToRecord(SamplePage::create(), Template::create())['message']
        );
    }
}
