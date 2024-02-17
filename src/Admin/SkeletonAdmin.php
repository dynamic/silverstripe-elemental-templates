<?php

namespace DNADesign\ElementalArchetypes\Admin;

use DNADesign\ElementalSkeletons\Models\Skeleton;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \DNADesign\ElementalArchetypes\Admin\SkeletonAdmin
 *
 */
class SkeletonAdmin extends ModelAdmin
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
        Skeleton::class,
    ];

    /**
     * @var string
     */
    private static string $menu_title = 'Element Skeletons';

    /**
     * @var string
     */
    private static string $url_segment = 'elemental-skeletons';

    /**
     * @var string
     */
    private static string $menu_icon_class = 'font-icon-block-layout';
}
