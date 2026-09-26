<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Models\Test\TaskOption as Model;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/**
 * Вариант ответа для редактора — с признаком верности.
 */
#[Schema]
class TaskOption extends Data
{
    public function __construct(
        #[Property(example: 1, description: 'Для изменения существующего варианта; без id — новый вариант')]
        public ?int $id,
        #[Property(example: 'Ньютон')]
        public ?string $text,
        #[Property(example: 'task-images/abc.png', description: 'Путь из `POST /files` (purpose=task_image); у существующего варианта без поля — картинка не меняется')]
        public ?string $image_path,
        #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/task-images/abc.png')]
        public ?string $image_url,
        #[Property(example: true)]
        public bool $is_correct,
    ) {}

    public static function fromModel(Model $model): TaskOption
    {
        return new self($model->id, $model->text, $model->image_path, $model->image_url, $model->is_correct);
    }
}
