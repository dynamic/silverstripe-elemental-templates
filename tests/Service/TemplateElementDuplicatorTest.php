<?php

namespace Dynamic\ElememtalTemplates\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use Dynamic\ElememtalTemplates\Models\Template;
use Dynamic\ElememtalTemplates\Service\TemplateElementDuplicator;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\BaseElement;

class TemplateElementDuplicatorTest extends SapphireTest
{
    protected static $fixture_file = 'TemplateElementDuplicatorTest.yml';

    public function testAvailableGloballyResetOnDuplicate()
    {
        $template = $this->objFromFixture(Template::class, 'default');
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
