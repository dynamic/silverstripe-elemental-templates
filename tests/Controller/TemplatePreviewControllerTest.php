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

    public function testContentOnlyPreviewRendersForTypedTemplate()
    {
        $template = $this->makeTemplate('Typed Template', \Page::class);
        $response = $this->get('template-preview/' . $template->ID . '?content_only=1');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testContentOnlyPreviewRendersForUntypedTemplate()
    {
        // A template with no PageType must preview against the base Page rather
        // than 500ing (previously "Invalid Page Type"). Regression guard for the
        // untyped-template support added alongside the Category feature.
        $template = $this->makeTemplate('Untyped Template', null);
        $response = $this->get('template-preview/' . $template->ID . '?content_only=1');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTemplatePreviewPageNotFound()
    {
        $response = $this->get('template-preview/99999'); // Non-existent ID

        $this->assertEquals(404, $response->getStatusCode(), 'Non-existent template should return 404.');
    }

    private function makeTemplate(string $title, ?string $pageType): Template
    {
        $template = Template::create();
        $template->Title = $title;
        $template->PageType = $pageType;
        $template->write();
        // Template is Versioned; a plain front-end GET reads the Live stage, so
        // publish it or the controller's byID() lookup 404s.
        $template->publishSingle();

        return $template;
    }
}
