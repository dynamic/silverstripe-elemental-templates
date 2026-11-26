<?php

namespace Dynamic\ElementalTemplates\Controller;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\View\SSViewer;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
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
        // Previews are for people who can reach the CMS, where the template picker lives.
        // Checked before the lookup so an anonymous visitor cannot probe which IDs exist.
        if (!Permission::check('CMS_ACCESS')) {
            return Security::permissionFailure($this);
        }

        $this->getResponse()->addHeader('X-Robots-Tag', 'noindex, nofollow');

        $templateID = $this->getRequest()->param('ID');
        $template = Template::get()->byID($templateID);

        if (!$template || !$template->canView()) {
            return $this->httpError(404, 'Template not found');
        }

        // Preview against the base Page when the template is untyped (it applies to any
        // page type) or its PageType is not an existing SiteTree class, rather than 500ing.
        $pageType = $template->PageType;
        if (!$pageType || !is_a($pageType, SiteTree::class, true)) {
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

        // One render: the engine picks the main template and the Layout subtemplate
        // from the page type's class ancestry, falling back to Page.
        $templates = array_merge(SSViewer::get_templates_by_class($pageType, '', SiteTree::class), ['Page']);

        // $Page matches content-only mode. Without it, a Page.ss reading $Page reaches
        // ContentController::Page($link) and errors for want of the argument.
        return $this->customise($page)->customise(['Page' => $page])->renderWith($templates);
    }
}
