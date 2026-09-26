<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Enums\TaskType;
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
        description: 'Ответы, по одному на вопрос (task_id не повторяются)',
        items: new Attributes\Items(ref: '#/components/schemas/Answer'),
    )]
    public array $answers;

    public static function rules(): array
    {
        $rules = [
            'answers.*' => ['array'],
            'answers.*.task_id' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['nullable', static function (string $attribute, mixed $value, \Closure $fail) {
                if (! (is_int($value) || is_float($value) || (is_string($value) && mb_strlen($value) <= 1000))) {
                    $fail('Ответ — строка до 1000 символов');
                }
            }],
        ];

        // поля ответа — из типов вопросов: новый тип добавляет свои
        foreach (TaskType::cases() as $type) {
            foreach ($type->definition()->answerRules() as $field => $fieldRules) {
                $rules["answers.*.{$field}"] ??= $fieldRules;
            }
        }

        return $rules;
    }
}
