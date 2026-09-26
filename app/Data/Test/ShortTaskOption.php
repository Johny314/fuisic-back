<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Models\Test\TaskOption as Model;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/**
 * Вариант ответа при прохождении — без признака верности.
 */
#[Schema]
class ShortTaskOption extends Data
{
    public function __construct(
        #[Property(example: 1)]
        public int $id,
        #[Property(example: 'Ньютон')]
        public ?string $text,
        #[Property(example: 'http://localhost:9000/fuisic/task-images/abc.png')]
        public ?string $image_url,
    ) {}

    public static function fromModel(Model $model): ShortTaskOption
    {
        return new self($model->id, $model->text, $model->image_url);
    }
}
