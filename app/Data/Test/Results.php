<?php

namespace App\Data\Test;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes;
use OpenApi\Attributes\Schema;

#[Schema]
class Results extends Data
{
    #[Property(example: '1')]
    public int $time;

    #[Property(example: '1')]
    public int $total_score;

    #[Property(
        type: 'array',
        items: new Attributes\Items(ref: '#/components/schemas/Result'),
    )]
    public array $results;
}
