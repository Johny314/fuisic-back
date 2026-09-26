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
 * Вопрос для редактора (автор, catalog.manage, admin) — с правильными ответами.
 * При прохождении отдаётся ShortTask.
 */
#[Schema(
    required: ['problem_statement'],
    description: 'Вопрос целиком. На запись: не переданное поле не меняется; `options` заменяют все варианты (с `id` — изменить, без — добавить, отсутствующие удаляются). '
        .'Старый формат `{test_id, problem_statement, answer}` без `type` и `settings` работает: создаёт `text` с одним ответом, при изменении ответа у `number` меняет значение.',
)]
class Task extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: '1', description: 'Обязателен при создании, при изменении игнорируется')]
    public ?string $test_id;

    #[Property(example: TaskType::single, description: 'single — один вариант, multiple — несколько, text — ввод текста, number — ввод числа. По умолчанию text')]
    public TaskType $type;

    #[Property(example: 'Какая единица силы в СИ? $F = ma$', description: 'Условие; формулы — текст в `$…$`. Обязательно, если нет картинки')]
    public ?string $problem_statement;

    #[Property(example: 'task-images/abc.png', description: 'Путь из `POST /files` (purpose=task_image); null — убрать картинку')]
    public ?string $image_path;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/task-images/abc.png')]
    public ?string $image_url;

    #[Property(example: 1, description: '0–100, по умолчанию 1')]
    public int $points;

    #[Property(example: 'Сила измеряется в ньютонах: $1\,Н = 1\,кг·м/с^2$', description: 'Разбор, показывается после сдачи')]
    public ?string $explanation;

    #[Property(example: false, description: 'Перемешивать варианты при прохождении')]
    public bool $shuffle_options;

    #[Property(
        type: 'object',
        nullable: true,
        description: 'Настройки проверки по типу. text: `{answers: string[]}` (1–20 допустимых ответов). '
            .'number: `{value: number, tolerance: number|null, tolerance_type: "absolute"|"percent", units: string[]}` (units пусто — единицы не нужны). '
            .'single/multiple: null — верность в `options`.',
        example: ['answers' => ['ньютон', 'Н']],
    )]
    public ?array $settings;

    #[Property(
        type: 'array',
        description: 'Варианты (2–10) для single/multiple: у single ровно один верный, у multiple — хотя бы один. У text/number — пусто',
        items: new Attributes\Items(ref: '#/components/schemas/TaskOption'),
    )]
    #[DataCollectionOf(TaskOption::class)]
    public array $options;

    #[Property(example: '4', description: 'Устарело (клиенты до fuisic-front#30): правильный ответ строкой. На запись без `settings`: text — единственный допустимый ответ, number — значение')]
    public ?string $answer;

    #[Property(example: 'Addition problem')]
    public ?string $description;

    #[Property(readOnly: true)]
    public ?Test $test;

    public static function fromModel(Model $model): Task
    {
        $model->loadMissing('options');

        return static::from([
            'id' => $model->id,
            'test_id' => $model->test_id === null ? null : (string) $model->test_id,
            'type' => $model->type,
            'problem_statement' => $model->problem_statement,
            'image_path' => $model->image_path,
            'image_url' => $model->image_url,
            'points' => $model->points,
            'explanation' => $model->explanation,
            'shuffle_options' => $model->shuffle_options,
            'settings' => $model->settings,
            'options' => $model->options->map(fn ($option) => TaskOption::fromModel($option))->all(),
            'answer' => $model->answer,
            'description' => null,
            'test' => $model->relationLoaded('test') && $model->test ? Test::from($model->test) : null,
        ]);
    }
}
