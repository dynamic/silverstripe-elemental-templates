<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateElementDuplicator;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * AvailableGlobally on an element that sits on a real page (not an orphan area, and not a Template).
 * Kept apart from BaseElementDataExtensionTest because it needs SamplePage in the test schema.
 */
class BaseElementDataExtensionPageTest extends SapphireTest
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
     * @var array<string, string[]>
     */
    protected static $required_extensions = [
        SamplePage::class => [
            ElementalPageExtension::class,
        ],
    ];

    private function createPageWithArea(): SamplePage
    {
        $area = ElementalArea::create();
        $area->write();

        $page = SamplePage::create();
        $page->Title = 'Sample page';
        $page->ElementalAreaID = $area->ID;
        $page->write();

        return $page;
    }

    private function addElement(SamplePage $page, bool $availableGlobally): ElementContent
    {
        $element = ElementContent::create();
        $element->Title = 'Page element';
        $element->HTML = '<p>Content</p>';
        $element->AvailableGlobally = $availableGlobally;
        $element->ParentID = $page->ElementalAreaID;
        $element->write();

        return ElementContent::get()->byID($element->ID);
    }

    public function testElementOnARealPageKeepsEditorChoice(): void
    {
        $page = $this->createPageWithArea();

        $element = $this->addElement($page, false);
        $this->assertInstanceOf(SamplePage::class, $element->getPage());
        $this->assertFalse((bool) $element->AvailableGlobally, 'Saved as not available globally');

        $element->Title = 'Edited';
        $element->write();
        $this->assertFalse((bool) ElementContent::get()->byID($element->ID)->AvailableGlobally);

        $element->AvailableGlobally = true;
        $element->write();
        $this->assertTrue((bool) ElementContent::get()->byID($element->ID)->AvailableGlobally);
    }

    public function testPageElementChoiceSurvivesApplyingATemplateAndPublishing(): void
    {
        Config::modify()->set(BaseElement::class, 'default_global_elements', true);

        $templateArea = ElementalArea::create();
        $templateArea->write();
        $template = Template::create();
        $template->Title = 'Test template';
        $template->ElementsID = $templateArea->ID;
        $template->write();
        $templateElement = ElementContent::create();
        $templateElement->Title = 'Template element';
        $templateElement->ParentID = $templateArea->ID;
        $templateElement->write();

        $page = $this->createPageWithArea();
        $existing = $this->addElement($page, false);

        (new TemplateElementDuplicator())->duplicateElements($template, $page->ElementalArea());

        // What the Content API apply-template endpoint does next: publish every element on the page.
        $existing->publishSingle();

        $this->assertFalse(
            (bool) ElementContent::get()->byID($existing->ID)->AvailableGlobally,
            'Draft keeps the editor choice'
        );
        $live = Versioned::get_by_stage(ElementContent::class, Versioned::LIVE)->byID($existing->ID);
        $this->assertNotNull($live, 'The element was published');
        $this->assertFalse((bool) $live->AvailableGlobally, 'Live keeps the editor choice');
    }
}
