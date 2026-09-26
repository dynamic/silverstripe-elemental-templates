<?php

namespace Dynamic\ElementalTemplates\Tests\Models;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use Dynamic\ElementalTemplates\Models\Template;
use ReflectionException;
use ReflectionMethod;
use SilverStripe\Dev\SapphireTest;

/**
 * Untyped templates fall back to the base Page's elemental types.
 *
 * Kept apart from TemplateTest on purpose: the behavior under test only exists
 * when the base Page is elemental, which is project configuration a real site
 * provides and this module's own test environment does not. Applying
 * ElementalPageExtension to Page via $required_extensions here keeps that
 * assumption scoped to this class instead of changing Page for every test in
 * TemplateTest (whose SamplePage fixtures apply the extension themselves).
 */
class UntypedTemplateTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected static $required_extensions = [
        \Page::class => [
            ElementalPageExtension::class,
        ],
    ];

    /**
     * An untyped template must fall back to the base Page's elemental types so
     * it stays editable in the CMS instead of allowing nothing.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testUntypedTemplateFallsBackToBasePageElementalTypes(): void
    {
        $template = Template::create();
        $template->Title = 'Untyped';
        $template->write();

        $method = new ReflectionMethod($template, 'getAllowedTypes');
        $method->setAccessible(true);
        $allowed = $method->invoke($template);

        $expected = \Page::singleton()->getElementalTypes();
        $this->assertNotEmpty($expected, 'precondition: an elemental base Page offers at least one element type');
        $this->assertEquals($expected, $allowed);
    }
}
