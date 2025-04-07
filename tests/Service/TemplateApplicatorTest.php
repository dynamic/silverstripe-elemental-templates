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
        $record = $this->getMockBuilder(SiteTree::class)
            ->addMethods(['ElementalArea'])
            ->getMock();

        $template = $this->objFromFixture(Template::class, 'validTemplate');

        // Mock the ElementalArea method to return a valid ElementalArea.
        $record->method('ElementalArea')->willReturn($this->objFromFixture(ElementalArea::class, 'pageElementalArea'));

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('applied to record', $result['message']);
    }

    public function testApplyTemplateToRecordInvalidTemplate()
    {
        $record = $this->objFromFixture(SiteTree::class, 'recordWithElementalArea');
        $template = $this->objFromFixture(Template::class, 'invalidTemplate');

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('does not support elemental areas', $result['message']);
    }

    public function testApplyTemplateToRecordNoElementalArea()
    {
        $record = $this->objFromFixture(SiteTree::class, 'recordWithoutElementalArea');
        $template = $this->objFromFixture(Template::class, 'validTemplate');

        $applicator = new TemplateApplicator();
        $result = $applicator->applyTemplateToRecord($record, $template);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('does not support elemental areas', $result['message']);
    }
}
