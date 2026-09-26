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

    #[Property(example: 4.5, description: 'Сумма баллов по ответам, до 2 знаков')]
    public float $total_score;

    #[Property(example: 6, description: 'Сумма баллов всех вопросов теста, в том числе без ответа')]
    public int $max_score;

    #[Property(
        type: 'array',
        items: new Attributes\Items(ref: '#/components/schemas/Result'),
    )]
    public array $results;
}
