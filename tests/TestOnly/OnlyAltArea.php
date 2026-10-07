<?php

namespace Dynamic\ElementalTemplates\Tests\TestOnly;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only page whose only elemental area relation is 'ElementalHomePage'.
 *
 * Unlike AltAreaSamplePage it has no 'ElementalArea' relation to fall back to: ElementalAreasExtension
 * is applied to it (via SapphireTest::$required_extensions) instead of ElementalPageExtension, so it
 * exposes exactly one area. That makes it the fixture for the "the conventional name does not exist on
 * this record" path of TemplateApplicator::resolveAreaRelationName().
 *
 * The class name is deliberately short: a test-only class's private static config is only visible when
 * the class manifest was built with tests included, and when it is not, SilverStripe falls back to a
 * fully-qualified-name-derived table name. Anything longer than thirteen characters would push that
 * fallback, plus Versioned's '_Versions' suffix, past MySQL's 64 character table-name limit.
 */
class OnlyAltArea extends SiteTree implements TestOnly
{
    private static array $has_one = [
        'ElementalHomePage' => ElementalArea::class,
    ];

    private static array $owns = [
        'ElementalHomePage',
    ];

    private static $table_name = 'OnlyAltArea';
}
