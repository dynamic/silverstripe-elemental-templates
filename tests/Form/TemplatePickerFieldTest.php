<?php

namespace Dynamic\ElementalTemplates\Tests\Form;

use Dynamic\ElementalTemplates\Form\TemplatePickerField;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePageTwo;
use SilverStripe\Dev\SapphireTest;

class TemplatePickerFieldTest extends SapphireTest
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
        SamplePageTwo::class,
    ];

    public function testPageTypeFilterIncludesAncestorsAndUntypedTemplates()
    {
        $exact = $this->makeTemplate('Exact', SamplePage::class);
        $ancestor = $this->makeTemplate('Ancestor', \Page::class);
        $universal = $this->makeTemplate('Universal', null);
        $descendant = $this->makeTemplate('Descendant', SamplePageTwo::class);

        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template', SamplePage::class);
        $titles = $field->getTemplates()->column('Title');

        // Exact class, an ancestor class, and untyped templates all apply.
        $this->assertContains('Exact', $titles);
        $this->assertContains('Ancestor', $titles);
        $this->assertContains('Universal', $titles);

        // A descendant/unrelated page type must not leak in.
        $this->assertNotContains('Descendant', $titles);
    }

    public function testUntypedTemplateAppearsForAnyPageType()
    {
        $this->makeTemplate('Universal', null);
        $this->makeTemplate('OtherTyped', SamplePageTwo::class);

        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template', SamplePage::class);
        $titles = $field->getTemplates()->column('Title');

        $this->assertContains('Universal', $titles);
        $this->assertNotContains('OtherTyped', $titles);
    }

    public function testNoPageTypeFilterReturnsAllTemplates()
    {
        $this->makeTemplate('Typed', SamplePageTwo::class);
        $this->makeTemplate('Untyped', null);

        // No page-type filter set: every template should be returned regardless of PageType.
        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template');
        $titles = $field->getTemplates()->column('Title');

        $this->assertContains('Typed', $titles);
        $this->assertContains('Untyped', $titles);
    }

    public function testGroupedTemplatesFollowConfiguredCategoryOrder()
    {
        // Created out of configured order on purpose; groups must come back in
        // the template_categories order with uncategorised templates last.
        $this->makeTemplate('Band', null, 'Conversion');
        $this->makeTemplate('Hero', null, 'Heroes');
        $this->makeTemplate('Loose', null, null);

        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template');
        $groups = $field->getGroupedTemplates();

        $this->assertEquals(['Heroes', 'Conversion', 'Other'], $groups->column('Title'));
        $this->assertEquals(['Loose'], $groups->last()->Templates->column('Title'));
    }

    public function testUnknownCategoryGroupsAfterConfiguredOnes()
    {
        $this->makeTemplate('Custom', null, 'Bespoke Category');
        $this->makeTemplate('Hero', null, 'Heroes');

        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template');
        $groups = $field->getGroupedTemplates();

        $this->assertEquals(['Heroes', 'Bespoke Category'], $groups->column('Title'));
    }

    public function testAllUncategorisedRendersSingleUnlabelledGroup()
    {
        $this->makeTemplate('One', null, null);
        $this->makeTemplate('Two', null, null);

        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template');
        $groups = $field->getGroupedTemplates();

        // A library with no categories renders flat: one group, no heading.
        $this->assertEquals(1, $groups->count());
        $this->assertSame('', $groups->first()->Title);
        $this->assertEquals(2, $groups->first()->Templates->count());
    }

    public function testEmptyStateHelpLinkIsWellFormed()
    {
        // A page type with no matching templates renders the empty state, whose help
        // link points at the Element Templates admin. The URL must keep the slash
        // between the admin root and the section (issue #64).
        $field = TemplatePickerField::create('ApplyTemplateID', 'Select template', SamplePage::class);

        $html = (string) $field->Field();

        $this->assertStringContainsString('admin/elemental-templates', $html);
        $this->assertStringNotContainsString('adminelemental-templates', $html);
    }

    private function makeTemplate(string $title, ?string $pageType, ?string $category = null): Template
    {
        $template = Template::create();
        $template->Title = $title;
        $template->PageType = $pageType;
        $template->Category = $category;
        $template->write();

        return $template;
    }
}
