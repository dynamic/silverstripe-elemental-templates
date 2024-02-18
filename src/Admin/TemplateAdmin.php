<?php

namespace Dynamic\ElememtalTemplates\Admin;

use Dynamic\ElememtalTemplates\Models\Template;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \Dynamic\ElememtalTemplates\Admin\TemplateAdmin
 *
 */
class TemplateAdmin extends ModelAdmin
{
    /**
     * @var array|string[]
     */
    private static array $allowed_actions = [
        'createPage',
    ];

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
}
