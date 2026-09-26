<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Enums\TaskType;
use App\Models\Test\Task as Model;
use App\OpenApi\Property;
use OpenApi\Attributes;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Attributes\DataCollectionOf;

/**
 * Вопрос при прохождении: без правильных ответов, настроек проверки и разбора.
 */
#[Schema(required: ['problem_statement'])]
class ShortTask extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: TaskType::single)]
    public TaskType $type;

    #[Property(example: 'What is 2 + 2?')]
    public ?string $problem_statement;

    #[Property(example: 'http://localhost:9000/fuisic/task-images/abc.png')]
    public ?string $image_url;

    #[Property(example: 1)]
    public int $points;

    #[Property(type: 'object', nullable: true, description: 'Публичная часть настроек типа (для текущих типов — null)')]
    public ?array $settings;

    #[Property(
        type: 'array',
        description: 'Варианты single/multiple; при shuffle_options — в случайном порядке',
        items: new Attributes\Items(ref: '#/components/schemas/ShortTaskOption'),
    )]
    #[DataCollectionOf(ShortTaskOption::class)]
    public array $options;

    #[Property(example: 'Addition problem')]
    public ?string $description;

    public static function fromModel(Model $model): ShortTask
    {
        $definition = $model->definition();
        $options = $definition->usesOptions() ? $model->loadMissing('options')->options : collect();
        if ($model->shuffle_options) {
            $options = $options->shuffle();
        }

        return static::from([
            'id' => $model->id,
            'type' => $model->type,
            'problem_statement' => $model->problem_statement,
            'image_url' => $model->image_url,
            'points' => $model->points,
            'settings' => $definition->publicSettings($model->settings),
            'options' => $options->map(fn ($option) => ShortTaskOption::fromModel($option))->values()->all(),
            'description' => null,
        ]);
    }
}
