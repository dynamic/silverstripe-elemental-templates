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

    private function makeTemplate(string $title, ?string $pageType): Template
    {
        $template = Template::create();
        $template->Title = $title;
        $template->PageType = $pageType;
        $template->write();

        return $template;
    }
}
