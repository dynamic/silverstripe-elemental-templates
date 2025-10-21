<?php

namespace Dynamic\ElementalTemplates\Admin;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Admin\ModelAdmin;

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
}
