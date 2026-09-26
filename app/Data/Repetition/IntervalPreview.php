<?php

namespace App\Data\Repetition;

use App\Data\Data;
use App\OpenApi\Property;
use App\Services\Fsrs\ReviewOutcome;
use OpenApi\Attributes\Schema;

/** Что будет при оценке — подпись кнопки («через 10 мин», «через 3 дня»). */
#[Schema(required: ['rating', 'label', 'state', 'due', 'interval_seconds'])]
class IntervalPreview extends Data
{
    #[Property(type: 'integer', enum: [1, 2, 3, 4], description: '1 — Снова, 2 — Трудно, 3 — Хорошо, 4 — Легко', example: 3)]
    public int $rating;

    #[Property(example: 'Хорошо')]
    public string $label;

    #[Property(type: 'string', enum: ['new', 'learning', 'review', 'relearning'], description: 'Состояние после оценки', example: 'learning')]
    public string $state;

    #[Property(format: 'date-time', description: 'Следующий показ, UTC', example: '2026-09-26T10:10:00.000000Z')]
    public string $due;

    #[Property(description: 'Интервал до следующего показа, секунды', example: 600)]
    public int $interval_seconds;

    public static function fromOutcome(ReviewOutcome $outcome): self
    {
        return static::from([
            'rating' => $outcome->rating->value,
            'label' => $outcome->rating->label(),
            'state' => $outcome->after->state->name,
            'due' => $outcome->due()->toJSON(),
            'interval_seconds' => $outcome->intervalSeconds,
        ]);
    }
}
