<?php

namespace Dynamic\ElememtalTemplates\Tests\TestOnly;

use SilverStripe\Dev\TestOnly;

class SamplePageTwo extends \Page implements TestOnly
{
    /**
     * @var string[]
     */
    private static array $disallowed_elements = [
        ElementOne::class,
    ];
}
