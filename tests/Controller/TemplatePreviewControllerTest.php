<?php

namespace Dynamic\ElementalTemplates\Tests\Controller;

use SilverStripe\Dev\FunctionalTest;
use Dynamic\ElementalTemplates\Models\Template;

class TemplatePreviewControllerTest extends FunctionalTest
{
    protected static $fixture_file = 'template-preview-test.yml';

    protected function setUp(): void
    {
        parent::setUp();

        // Run dev/build to ensure the database schema is up to date
        $this->logInWithPermission('ADMIN');
        $this->get('dev/build?flush=1');
    }

    public function testTemplatePreviewPageLoadsSuccessfully()
    {
        $template = $this->objFromFixture(Template::class, 'exampleTemplate');
        $response = $this->get('template-preview/' . $template->ID);

        $this->assertEquals(200, $response->getStatusCode(), 'Preview page should load successfully.');
        $this->assertStringContainsString($template->Title, $response->getBody(), 'Preview page should contain the template title.');
    }

    public function testTemplatePreviewPageNotFound()
    {
        $response = $this->get('template-preview/99999'); // Non-existent ID

        $this->assertEquals(404, $response->getStatusCode(), 'Non-existent template should return 404.');
    }
}
