<?php

namespace App\Data\Repetition;

use App\Data\Data;
use App\Models\Card\CardReviewLog;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/** Итог оценки — из журнала, поэтому повторная отправка с тем же review_id возвращает то же самое. */
#[Schema(required: ['review_id', 'card_id', 'rating', 'state', 'due', 'interval_seconds', 'scheduled_days', 'reviewed_at'])]
class ReviewResult extends Data
{
    #[Property(format: 'uuid', example: '0b6f7c1e-3f1a-4c55-9f0e-2a4d0f8a9c11')]
    public string $review_id;

    #[Property(example: 1)]
    public int $card_id;

    #[Property(type: 'integer', enum: [1, 2, 3, 4], example: 3)]
    public int $rating;

    #[Property(type: 'string', enum: ['new', 'learning', 'review', 'relearning'], description: 'Состояние после оценки', example: 'review')]
    public string $state;

    #[Property(format: 'date-time', description: 'Следующий показ, UTC', example: '2026-09-29T10:00:00.000000Z')]
    public string $due;

    #[Property(example: 259200)]
    public int $interval_seconds;

    #[Property(description: 'Целых дней интервала (0 — шаг в минутах)', example: 3)]
    public int $scheduled_days;

    #[Property(format: 'date-time', description: 'Когда оценка принята сервером, UTC', example: '2026-09-26T10:00:00.000000Z')]
    public string $reviewed_at;

    public static function fromModel(CardReviewLog $log): self
    {
        return static::from([
            'review_id' => $log->review_id,
            'card_id' => $log->card_id,
            'rating' => $log->rating->value,
            'state' => $log->state_after->name,
            'due' => $log->due_after->toJSON(),
            'interval_seconds' => $log->interval_seconds,
            'scheduled_days' => $log->scheduled_days,
            'reviewed_at' => $log->reviewed_at->toJSON(),
        ]);
    }
}
