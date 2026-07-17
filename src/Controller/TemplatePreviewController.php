<?php

namespace Dynamic\ElementalTemplates\Controller;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBField;
use Dynamic\ElementalTemplates\Models\Template;

/**
 * Controller for rendering template previews.
 * Supports two modes:
 * - Full page preview (default): Renders template within Page.ss wrapper
 * - Content-only mode (?content_only=1): Renders only elemental area for screenshots
 */
class TemplatePreviewController extends \PageController
{
    private static $allowed_actions = [
        'index',
    ];

    protected function init()
    {
        parent::init();
    }

    public function index()
    {
        $templateID = $this->getRequest()->param('ID');
        $template = Template::get()->byID($templateID);

        if (!$template) {
            return $this->httpError(404, 'Template not found');
        }

        // Untyped templates apply to any page type, so preview them against the
        // base Page rather than 500ing — mirrors Template::getAllowedTypes().
        $pageType = $template->PageType;
        if (!$pageType || !class_exists($pageType)) {
            $pageType = \Page::class;
        }
        if (!class_exists($pageType)) {
            return $this->httpError(500, 'Invalid Page Type');
        }

        // Check for content-only mode (for screenshots)
        $contentOnly = $this->getRequest()->getVar('content_only');

        if ($contentOnly) {
            return $this->renderContentOnly($template, $pageType);
        }

        return $this->renderFullPage($template, $pageType);
    }

    /**
     * Render content-only mode for clean screenshots.
     * Shows only the elemental area without page wrapper (header/footer).
     *
     * @param Template $template
     * @param string $pageType
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    protected function renderContentOnly(Template $template, string $pageType)
    {
        $page = $pageType::create();
        $page->Title = $template->Title;
        $page->ElementalArea = $template->Elements();

        return $this->customise([
            'Template' => $template,
            'Page' => $page,
        ])->renderWith('Dynamic\\ElementalTemplates\\Layout\\TemplatePreviewContent');
    }

    /**
     * Render full page preview with page wrapper (header/footer).
     *
     * @param Template $template
     * @param string $pageType
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    protected function renderFullPage(Template $template, string $pageType)
    {
        $page = $pageType::create();
        $page->Title = $template->Title;
        $page->ElementalArea = $template->Elements();

        $templatePath = str_replace('\\', '/', $pageType);
        $templateSegments = explode('/', $templatePath);

        // Inject 'Layout' between the second-to-last and last segments
        if (count($templateSegments) > 1) {
            array_splice($templateSegments, -1, 0, 'Layout');
        }

        $adjustedTemplatePath = implode('/', $templateSegments);

        // Render the BlockPage layout template and store it in the Layout variable
        $layoutContent = $this->customise($page)->renderWith($adjustedTemplatePath);

        // Render the Page.ss template with the Layout variable properly set
        return $this->customise([
            'Page' => $page,
            'Layout' => DBField::create_field('HTMLText', $layoutContent)
        ])->renderWith('Page');
    }
}
