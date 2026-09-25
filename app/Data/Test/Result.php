<?php

namespace App\Data\Test;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema]
class Result extends Data
{
    #[Property(example: '1')]
    public ?ShortTask $task;

    #[Property(example: '1')]
    public ?string $answer;

    #[Property(example: '1')]
    public ?string $correct_answer;

    #[Property(example: true)]
    public bool $is_correct;
}
