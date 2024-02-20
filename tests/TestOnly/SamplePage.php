<?php

namespace Dynamic\ElememtalTemplates\Tests\TestOnly;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use SilverStripe\Dev\TestOnly;

class SamplePage extends \Page implements TestOnly
{
    /**
     * @var string[]
     */
    private static array $disallowed_elements = [
        ElementOne::class,
    ];

    /**
     * @var array|string[]
     */
    private static array $extensions = [
        ElementalPageExtension::class,
    ];
}
