<?php

namespace Dynamic\ElememtalTemplates\Tests\Service;

use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\ElememtalTemplates\Service\TemplateApplicator;
use DNADesign\Elemental\Tests\Src\TestElement\ElementOne;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use DNADesign\Elemental\Models\ElementalArea;

class TemplateApplicatorTest extends SapphireTest
{
    protected static $fixture_file = 'TemplateApplicatorTest.yml';

    public function testApplyTemplateToRecordSuccess()
    {
        // Test that applying a valid template to a valid record succeeds
        $record = $this->getMockBuilder(SiteTree::class)
            ->addMethods(['ElementalArea'])
            ->getMock();

        $template = $this->objFromFixture(Template::class, 'validTemplate');

        // Mock the ElementalArea method to return a valid ElementalArea.
        $record->method('ElementalArea')->willReturn($this->objFromFixture(ElementalArea::class, 'pageElementalArea'));

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        // Should succeed when both template and record have valid elemental areas
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }

    public function testApplyTemplateToRecordInvalidTemplate()
    {
        // Test that applying a template without an elemental area fails
        $record = $this->objFromFixture(SiteTree::class, 'recordWithElementalArea');
        $template = $this->objFromFixture(Template::class, 'invalidTemplate');

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        // Should fail because the template has no elemental area
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }

    public function testApplyTemplateToRecordNoElementalArea()
    {
        // Test that applying a template to a record without elemental area support fails
        $record = $this->objFromFixture(SiteTree::class, 'recordWithoutElementalArea');
        $template = $this->objFromFixture(Template::class, 'validTemplate');

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        // Should fail because the record doesn't support or have elemental areas
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        $this->assertIsString($result['message']);
    }
}
