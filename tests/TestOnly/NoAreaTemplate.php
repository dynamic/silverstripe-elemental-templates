<?php

namespace Dynamic\ElementalTemplates\Tests\TestOnly;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\TestOnly;

/**
 * A template whose elemental area never exists, whatever the stage or elemental version, for
 * testing how callers handle a template that could not be given an area.
 */
class NoAreaTemplate extends Template implements TestOnly
{
    private static $table_name = 'NoAreaTemplate';

    public function Elements(): ElementalArea
    {
        return ElementalArea::create();
    }
}
