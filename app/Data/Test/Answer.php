<?php

namespace App\Data\Test;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes;
use OpenApi\Attributes\Schema;

/**
 * Ответ на вопрос: поле по типу вопроса. Устаревшее `answer` — для клиентов до fuisic-front#29.
 * Используется только в документации: валидация — Answers::rules().
 */
#[Schema(required: ['task_id'])]
class Answer extends Data
{
    #[Property(example: 1)]
    public int $task_id;

    #[Property(example: 12, description: 'single: id выбранного варианта')]
    public ?int $option_id;

    #[Property(
        type: 'array',
        description: 'multiple: id выбранных вариантов; [] — ничего не выбрано. id не из этого вопроса не учитываются',
        items: new Attributes\Items(type: 'integer'),
        example: [12, 14],
    )]
    public ?array $option_ids;

    #[Property(example: 'Ньютон', description: 'text: ответ; регистр, пробелы и «ё»/«е» не важны')]
    public ?string $text;

    #[Property(
        oneOf: [new Schema(type: 'number'), new Schema(type: 'string')],
        description: 'number: число или строка с единицей — «9,8 м/с²», «1 000», «−3», «1,5e3»',
        example: '9,8 м/с²',
    )]
    public int|float|string|null $value;

    #[Property(example: '12', description: 'Устарело (до fuisic-front#29): ответ строкой — single/multiple: id вариантов через запятую, text/number: как text/value. Используется, если нет поля своего типа')]
    public ?string $answer;
}
