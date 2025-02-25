<?php

namespace Dynamic\ElememtalTemplates\Tests\TestOnly;

use DNADesign\Elemental\Models\BaseElement;

class ElementTwo extends BaseElement
{
    private static $table_name = 'ElementTwo';

    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText',
    ];
}
