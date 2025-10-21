<?php

namespace Dynamic\ElementalTemplates\Controller;

use App\PageController;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use Dynamic\ElementalTemplates\Models\Template;

class TemplatePreviewController extends \PageController
{
    private static $allowed_actions = [
        'index',
    ];

    protected function init()
    {
        parent::init();
        // Removed unnecessary CSS requirement for preview.css
    }

    public function index()
    {
        $templateID = $this->getRequest()->param('ID');
        $template = Template::get()->byID($templateID);

        if (!$template) {
            return $this->httpError(404, 'Template not found');
        }

        $pageType = $template->PageType;
        if (!class_exists($pageType)) {
            return $this->httpError(500, 'Invalid Page Type');
        }

        $page = $pageType::create();
        $page->Title = $template->Title;
        $page->ElementalArea = $template->Elements();

        // Render with the page type template which will automatically include Layout
        return $this->customise($page)->renderWith([$pageType, 'Page']);
    }
}
