<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateElementDuplicator;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\BaseElement;

class TemplateElementDuplicatorTest extends SapphireTest
{
    public function testAvailableGloballyResetOnDuplicate()
    {
        // Create test data programmatically
        $templateArea = ElementalArea::create();
        $templateArea->Title = 'Template Elemental Area';
        $templateArea->write();

        $template = Template::create();
        $template->Title = 'Test Template';
        $template->AvailableGlobally = true;
        $template->ElementsID = $templateArea->ID;
        $template->write();
        $template->copyVersionToStage('Stage', 'Stage');

        $area = ElementalArea::create();
        $area->write();

        $duplicator = new TemplateElementDuplicator();
        $duplicator->duplicateElements($template, $area);

        $duplicatedElement = $area->Elements()->first();

        $this->assertNotNull($duplicatedElement, 'Duplicated element should exist');

        $defaultAvailableGlobally = singleton(BaseElement::class)->config()->get('default_global_elements');

        $this->assertEquals(
            $defaultAvailableGlobally,
            $duplicatedElement->AvailableGlobally,
            'AvailableGlobally should be reset to default value on duplication'
        );
    }
}
