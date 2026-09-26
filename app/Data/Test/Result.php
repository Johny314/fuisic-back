<?php

namespace App\Data\Test;

use App\Data\Data;
use App\Enums\AnswerStatus;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema]
class Result extends Data
{
    #[Property(example: '1')]
    public ?ShortTask $task;

    #[Property(example: '1', description: 'Ответ строкой: id вариантов через запятую, текст или число')]
    public ?string $answer;

    #[Property(example: '1')]
    public ?string $correct_answer;

    #[Property(example: true, description: 'status = correct')]
    public bool $is_correct;

    #[Property(example: 1.5, description: 'Набранные баллы, до 2 знаков')]
    public float $score;

    #[Property(example: 3, description: 'Баллы вопроса (points); 0 — вопрос не из этого теста')]
    public int $max_score;

    #[Property(type: 'string', enum: ['correct', 'partial', 'incorrect'], example: AnswerStatus::partial, description: 'correct — все баллы, partial — часть (multiple), incorrect — 0')]
    public AnswerStatus $status;
}
