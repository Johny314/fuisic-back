<?php

namespace App\Models\Card;

use App\Enums\ReviewRating;
use App\Enums\ReviewState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись журнала оценок: оценка и состояние FSRS до и после. Не меняется после создания.
 */
class CardReviewLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'card_id',
        'review_id',
        'rating',
        'reviewed_at',
        'duration_ms',
        'state_before',
        'state_after',
        'stability_before',
        'stability_after',
        'difficulty_before',
        'difficulty_after',
        'due_before',
        'due_after',
        'elapsed_days',
        'scheduled_days',
        'interval_seconds',
    ];

    protected function casts(): array
    {
        return [
            'rating' => ReviewRating::class,
            'reviewed_at' => 'immutable_datetime',
            'duration_ms' => 'integer',
            'state_before' => ReviewState::class,
            'state_after' => ReviewState::class,
            'stability_before' => 'float',
            'stability_after' => 'float',
            'difficulty_before' => 'float',
            'difficulty_after' => 'float',
            'due_before' => 'immutable_datetime',
            'due_after' => 'immutable_datetime',
            'elapsed_days' => 'integer',
            'scheduled_days' => 'integer',
            'interval_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }
}
