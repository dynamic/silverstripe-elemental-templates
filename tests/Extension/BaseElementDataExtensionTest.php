<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Service\TemplateElementDuplicator;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * AvailableGlobally rules: Template elements are never global, every other element keeps the
 * editor's value, and TemplateElementDuplicator gives a copy the configured default.
 *
 * The field comes from dnadesign/silverstripe-elemental-virtual (require-dev).
 */
class BaseElementDataExtensionTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            singleton(ElementContent::class)->hasField('AvailableGlobally'),
            'These tests need dnadesign/silverstripe-elemental-virtual, which defines AvailableGlobally'
        );
    }

    private function createElement(ElementalArea $area, bool $availableGlobally): ElementContent
    {
        $element = ElementContent::create();
        $element->Title = 'Test element';
        $element->HTML = '<p>Content</p>';
        $element->AvailableGlobally = $availableGlobally;
        $element->ParentID = $area->ID;
        $element->write();

        return ElementContent::get()->byID($element->ID);
    }

    private function createTemplateArea(): ElementalArea
    {
        $area = ElementalArea::create();
        $area->write();

        $template = Template::create();
        $template->Title = 'Test template';
        $template->ElementsID = $area->ID;
        $template->write();

        return $area;
    }

    private function reload(ElementContent $element): ElementContent
    {
        return ElementContent::get()->byID($element->ID);
    }

    public function testElementOutsideTemplateKeepsEditorChoice(): void
    {
        $area = ElementalArea::create();
        $area->write();

        $element = $this->createElement($area, false);
        $this->assertFalse((bool) $element->AvailableGlobally);

        $element->Title = 'Edited';
        $element->write();
        $this->assertFalse(
            (bool) $this->reload($element)->AvailableGlobally,
            'An element saved as not available globally must stay that way on later saves'
        );

        $element->AvailableGlobally = true;
        $element->write();
        $this->assertTrue((bool) $this->reload($element)->AvailableGlobally);
    }

    public function testTemplateElementIsNeverAvailableGlobally(): void
    {
        $element = $this->createElement($this->createTemplateArea(), true);
        $this->assertInstanceOf(Template::class, $element->getPage());
        $this->assertFalse(
            (bool) $element->AvailableGlobally,
            'A new element in a Template must be saved as not available globally'
        );

        $element->AvailableGlobally = true;
        $element->Title = 'Edited';
        $element->write();
        $this->assertFalse(
            (bool) $this->reload($element)->AvailableGlobally,
            'A later save of a Template element must not make it available globally'
        );
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function defaultProvider(): array
    {
        return [
            'default true' => [true],
            'default false' => [false],
        ];
    }

    #[DataProvider('defaultProvider')]
    public function testDuplicatorGivesCopiesTheConfiguredDefault(bool $default): void
    {
        Config::modify()->set(BaseElement::class, 'default_global_elements', $default);

        $templateArea = $this->createTemplateArea();
        $sources = [
            $this->createElement($templateArea, false),
            $this->createElement($templateArea, false),
        ];
        $template = Template::get()->filter('ElementsID', $templateArea->ID)->first();

        $targetArea = ElementalArea::create();
        $targetArea->write();

        (new TemplateElementDuplicator())->duplicateElements($template, $targetArea);

        $copies = $targetArea->Elements();
        $this->assertCount(2, $copies, 'Both template elements are copied');
        foreach ($copies as $copy) {
            $this->assertSame(
                $default,
                (bool) $copy->AvailableGlobally,
                'Each copy takes the default_global_elements value'
            );
        }
        foreach ($sources as $source) {
            $this->assertFalse(
                (bool) $this->reload($source)->AvailableGlobally,
                'The template element itself stays not available globally'
            );
        }
    }

    public function testApplyingATemplateDoesNotAffectLaterWrites(): void
    {
        Config::modify()->set(BaseElement::class, 'default_global_elements', true);

        $templateArea = $this->createTemplateArea();
        $this->createElement($templateArea, false);
        $template = Template::get()->filter('ElementsID', $templateArea->ID)->first();

        $targetArea = ElementalArea::create();
        $targetArea->write();
        (new TemplateElementDuplicator())->duplicateElements($template, $targetArea);

        // Same PHP process, after an apply: extension instances are shared, so any state left on
        // the extension by the duplicator would show up here.
        $pageElement = $this->createElement($targetArea, false);
        $this->assertFalse(
            (bool) $pageElement->AvailableGlobally,
            'A page element written after a template was applied keeps the editor choice'
        );

        $newTemplateElement = $this->createElement($templateArea, true);
        $this->assertFalse(
            (bool) $newTemplateElement->AvailableGlobally,
            'A Template element written after a template was applied is still not available globally'
        );

        $newTemplateElement->AvailableGlobally = true;
        $newTemplateElement->write();
        $this->assertFalse((bool) $this->reload($newTemplateElement)->AvailableGlobally);
    }
}
