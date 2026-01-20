<?php

namespace Dynamic\ElementalTemplates\Admin;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\View\Requirements;

/**
 * Class \Dynamic\ElementalTemplates\Admin\TemplateAdmin
 *
 */
class TemplateAdmin extends ModelAdmin
{
    /**
     * @var string[]
     */
    private static array $managed_models = [
        Template::class,
    ];

    /**
     * @var string
     */
    private static string $menu_title = 'Element Templates';

    /**
     * @var string
     */
    private static string $url_segment = 'elemental-templates';

    /**
     * @var string
     */
    private static string $menu_icon_class = 'font-icon-block-layout';

    /**
     * Initialize admin with required JS for screenshot capture.
     */
    protected function init(): void
    {
        parent::init();

        // Require screenshot capture JS for the Capture Preview Image button
        Requirements::javascript(
            'dynamic/silverstripe-elemental-templates:client/dist/js/screenshot-capture.js'
        );
    }
}
