<?php

namespace Dynamic\ElementalTemplates\Tests\TestOnly;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;

/**
 * Test-only page whose visible elemental relation is NOT 'ElementalArea'.
 *
 * It mirrors the 'HomePage' / 'ElementalHomePage' shape described in issue #73: the page owns a
 * second elemental area relation and hides the default one in the CMS, so the area a content
 * author actually edits is 'ElementalHomePage'.
 *
 * Extends SiteTree directly rather than SamplePage so it stays autoloadable before
 * SapphireTest boots the kernel (the app's `Page` class is only resolvable afterwards).
 */
class AltAreaSamplePage extends SiteTree implements TestOnly
{
    private static array $has_one = [
        'ElementalHomePage' => ElementalArea::class,
    ];

    private static array $owns = [
        'ElementalHomePage',
    ];

    private static $table_name = 'AltAreaSamplePage';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        // Hide the default area so 'ElementalHomePage' becomes the active relation.
        $fields->removeByName('ElementalArea');
        return $fields;
    }
}
