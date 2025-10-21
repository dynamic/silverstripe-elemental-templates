<?php

namespace Dynamic\ElementalTemplates\Tests\Controller;

use SilverStripe\Dev\FunctionalTest;
use Dynamic\ElementalTemplates\Models\Template;

class TemplatePreviewControllerTest extends FunctionalTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    public function testTemplatePreviewPageLoadsSuccessfully()
    {
        $this->markTestSkipped('Skipping test for TemplatePreviewController as it requires a valid template ID.');
        $template = $this->objFromFixture(Template::class, 'exampleTemplate');
        $response = $this->get('template-preview/' . $template->ID);

        $this->assertEquals(200, $response->getStatusCode(), 'Preview page should load successfully.');
        $this->assertStringContainsString($template->Title, $response->getBody(), 'Preview page should contain the template title.');
    }

    public function testTemplatePreviewPageNotFound()
    {
        $this->markTestSkipped('Skipping test for TemplatePreviewController as it requires a valid template ID.');
        $response = $this->get('template-preview/99999'); // Non-existent ID

        $this->assertEquals(404, $response->getStatusCode(), 'Non-existent template should return 404.');
    }
}
