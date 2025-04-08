<?php

namespace Dynamic\ElementalTemplates\Controller;

use App\PageController;
use SilverStripe\Dev\Debug;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBField;
use Dynamic\ElememtalTemplates\Models\Template;

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

        // Explicitly assign the Template's ElementalArea to the PreviewPage
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
            'Layout' => DBField::create_field('HTMLText', $layoutContent) // Ensure the layout is treated as HTML
        ])->renderWith('Page');
    }
}
