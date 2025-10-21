<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use DNADesign\Elemental\Models\ElementalArea;

class TemplateApplicatorTest extends SapphireTest
{
    public function testApplyTemplateToRecordSuccess()
    {
        // Create test data programmatically instead of using fixtures
        $validElementalArea = ElementalArea::create();
        $validElementalArea->Title = 'Valid Elemental Area';
        $validElementalArea->write();

        $pageElementalArea = ElementalArea::create();
        $pageElementalArea->Title = 'Page Elemental Area';
        $pageElementalArea->write();

        $validTemplate = Template::create();
        $validTemplate->Title = 'Valid Template';
        $validTemplate->ElementsID = $validElementalArea->ID;
        $validTemplate->write();

        // Test that applying a valid template to a valid record succeeds
        $record = $this->getMockBuilder(SiteTree::class)
            ->addMethods(['ElementalArea'])
            ->getMock();

        // Mock the ElementalArea method to return a valid ElementalArea.
        $record->method('ElementalArea')->willReturn($pageElementalArea);

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        // Should succeed when both template and record have valid elemental areas
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }

    public function testApplyTemplateToRecordInvalidTemplate()
    {
        // Create test data programmatically
        $invalidTemplate = Template::create();
        $invalidTemplate->Title = 'Invalid Template';
        $invalidTemplate->write(); // No ElementsID set, making it invalid

        $pageElementalArea = ElementalArea::create();
        $pageElementalArea->Title = 'Page Elemental Area';
        $pageElementalArea->write();

        $record = $this->getMockBuilder(SiteTree::class)
            ->addMethods(['ElementalArea'])
            ->getMock();
        $record->method('ElementalArea')->willReturn($pageElementalArea);

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

        $record = $this->getMockBuilder(SiteTree::class)
            ->addMethods(['ElementalArea'])
            ->getMock();
        $record->method('ElementalArea')->willReturn(null); // No elemental area

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $validTemplate);

        // Should fail because the record doesn't support or have elemental areas
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }
}
