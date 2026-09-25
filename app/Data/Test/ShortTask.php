<?php

namespace App\Data\Test;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema(required: ['problem_statement'])]
class ShortTask extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 'What is 2 + 2?')]
    public ?string $problem_statement;

    #[Property(example: 'Addition problem')]
    public ?string $description;
}
