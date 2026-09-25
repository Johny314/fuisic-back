<?php

namespace App\Data\Test;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes;
use OpenApi\Attributes\Schema;

#[Schema]
class Answers extends Data
{
    #[Property(example: '1')]
    public int $time;

    #[Property(
        type: 'array',
        items: new Attributes\Items(ref: '#/components/schemas/Answer'),
    )]
    public array $answers;
}
