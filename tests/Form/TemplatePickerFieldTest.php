<?php

namespace Dynamic\ElementalTemplates\Tests\Form;

use Dynamic\ElementalTemplates\Form\TemplatePickerField;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
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
    ];

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
}
