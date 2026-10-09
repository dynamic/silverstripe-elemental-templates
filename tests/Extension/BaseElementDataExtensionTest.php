<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Extension\BaseElementDataExtension;
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

    /**
     * Points the populate step at a fixture file that only defines ElementContent placeholder data.
     */
    private function setPlaceholderFixtures(): void
    {
        Config::modify()->set(
            BaseElementDataExtension::class,
            'fixtures',
            __DIR__ . '/../Service/test-element-placeholder.yml'
        );
    }

    /**
     * Writes a block outside any Template, then moves it into the Template's area. The block keeps
     * its own Title and HTML because populate only ever runs on a block that is new to the database.
     */
    private function createMovedBlock(ElementalArea $templateArea, string $title, string $html): ElementContent
    {
        $outsideArea = ElementalArea::create();
        $outsideArea->write();

        $element = ElementContent::create();
        $element->Title = $title;
        $element->HTML = $html;
        $element->ParentID = $outsideArea->ID;
        $element->write();
        $id = $element->ID;

        $element = ElementContent::get()->byID($id);
        $element->ParentID = $templateArea->ID;
        $element->write();

        return ElementContent::get()->byID($id);
    }

    public function testDuplicateInsideTemplateKeepsContent(): void
    {
        $this->setPlaceholderFixtures();

        $templateArea = $this->createTemplateArea();
        $element = $this->createMovedBlock($templateArea, 'Original title', '<p>Original HTML</p>');

        $copy = $element->duplicate();

        $this->assertSame('Original title', $copy->Title, 'duplicate() must not overwrite the copy with placeholder data');
        $this->assertSame('<p>Original HTML</p>', $copy->HTML, 'duplicate() must not overwrite the copy HTML');
        $this->assertNotEquals('Test Content Block Title', $copy->Title);
    }

    public function testDuplicateThenAddKeepsContent(): void
    {
        $this->setPlaceholderFixtures();

        $templateArea = $this->createTemplateArea();
        $element = $this->createMovedBlock($templateArea, 'Duplicated then added', '<p>Kept HTML</p>');

        $copy = $element->duplicate(false);
        $this->assertSame('Duplicated then added', $copy->Title);

        // The Template's own elemental area, i.e. elemental's Duplicate action inside a Template
        $templateArea->Elements()->add($copy);

        $reloaded = ElementContent::get()->byID($copy->ID);
        $this->assertNotNull($reloaded, 'HasManyList::add() wrote the copy');
        $this->assertSame(
            'Duplicated then added',
            $reloaded->Title,
            'Adding a duplicate back into the Template area must not populate it'
        );
        $this->assertSame('<p>Kept HTML</p>', $reloaded->HTML);
    }

    public function testApplyKeepsFirstBlockContent(): void
    {
        $this->setPlaceholderFixtures();

        $templateArea = $this->createTemplateArea();
        $first = $this->createMovedBlock($templateArea, 'First template block', '<p>First HTML</p>');
        $second = $this->createMovedBlock($templateArea, 'Second template block', '<p>Second HTML</p>');
        $template = Template::get()->filter('ElementsID', $templateArea->ID)->first();

        $targetArea = ElementalArea::create();
        $targetArea->write();
        (new TemplateElementDuplicator())->duplicateElements($template, $targetArea);

        $copies = $targetArea->Elements()->sort('Sort');
        $this->assertCount(2, $copies);
        $titles = $copies->column('Title');
        $this->assertContains('First template block', $titles, 'The first copied block keeps its title');
        $this->assertContains('Second template block', $titles);
        $this->assertContains('<p>First HTML</p>', $copies->column('HTML'));
        $this->assertNotContains('Test Content Block Title', $titles);

        $this->assertSame('First template block', $first->Title, 'The template block itself is untouched');
        $this->assertSame('Second template block', $second->Title);
    }

    public function testNewElementStillPopulatedAfterCopies(): void
    {
        $this->setPlaceholderFixtures();

        $templateArea = $this->createTemplateArea();
        $this->createMovedBlock($templateArea, 'First template block', '<p>First HTML</p>');
        $template = Template::get()->filter('ElementsID', $templateArea->ID)->first();

        // A duplicate inside the Template, as elemental's Duplicate action does
        $toDuplicate = $this->createMovedBlock($templateArea, 'Block to duplicate', '<p>Duplicated HTML</p>');
        $toDuplicate->duplicate();

        // An add back into the Template area, which is what HasManyList::add() does
        $toAdd = $this->createMovedBlock($templateArea, 'Block to add', '<p>Added HTML</p>');
        $templateArea->Elements()->add($toAdd->duplicate(false));

        // A template apply, which is the only thing the CMS normally does in one request
        $targetArea = ElementalArea::create();
        $targetArea->write();
        (new TemplateElementDuplicator())->duplicateElements($template, $targetArea);

        // Afterwards, in the same process, a brand new block written into the Template must still
        // receive its placeholder data - the skip state must not leak onto it.
        $fresh = ElementContent::create();
        $fresh->Title = 'Ignored title';
        $fresh->HTML = '<p>Ignored</p>';
        $fresh->ParentID = $templateArea->ID;
        $fresh->write();

        $reloaded = ElementContent::get()->byID($fresh->ID);
        $this->assertNotNull($reloaded);
        $this->assertSame(
            'Test Content Block Title',
            $reloaded->Title,
            'A new Template block written after duplicates must still be populated'
        );
        $this->assertStringContainsString('Lorem ipsum', $reloaded->HTML);
    }

    /**
     * The skip flag is per block: it must not carry over to the next new Template block.
     */
    public function testSetSkipPopulateDataAppliesToThatBlockOnly(): void
    {
        $this->setPlaceholderFixtures();

        $templateArea = $this->createTemplateArea();

        $skipped = ElementContent::create();
        $skipped->Title = 'Skipped title';
        $skipped->HTML = '<p>Skipped</p>';
        $skipped->ParentID = $templateArea->ID;
        $skipped->setSkipPopulateData(true);
        $skipped->write();
        $this->assertSame(
            'Skipped title',
            ElementContent::get()->byID($skipped->ID)->Title,
            'An explicit skip keeps the block as written'
        );

        $populated = ElementContent::create();
        $populated->Title = 'Ignored title';
        $populated->HTML = '<p>Ignored</p>';
        $populated->ParentID = $templateArea->ID;
        $populated->write();
        $this->assertSame(
            'Test Content Block Title',
            ElementContent::get()->byID($populated->ID)->Title,
            'The skip must not leak to the next new Template block'
        );
    }
}
