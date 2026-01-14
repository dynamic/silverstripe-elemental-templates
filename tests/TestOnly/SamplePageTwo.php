<?php

namespace Dynamic\ElementalTemplates\Tests\TestOnly;

use SilverStripe\Dev\TestOnly;

class SamplePageTwo extends SamplePage implements TestOnly
{
    /**
     * @var string[]
     */
    private static array $disallowed_elements = [
        ElementOne::class,
    ];
}
