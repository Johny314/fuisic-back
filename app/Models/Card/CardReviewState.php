<?php

namespace App\Models\Card;

use App\Enums\ReviewRating;
use App\Enums\ReviewState;
use App\Models\User;
use App\Services\Fsrs\MemoryState;
use App\Services\Fsrs\ReviewOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Состояние интервальных повторений FSRS по паре «пользователь — карточка».
 * Меняется только через App\Services\CardReviews.
 */
class CardReviewState extends Model
{
    protected $fillable = [
        'user_id',
        'card_id',
        'state',
        'step',
        'stability',
        'difficulty',
        'due',
        'last_review_at',
        'last_rating',
        'reps',
        'lapses',
        'elapsed_days',
        'scheduled_days',
    ];

    protected $attributes = [
        'state' => 0,
        'reps' => 0,
        'lapses' => 0,
        'elapsed_days' => 0,
        'scheduled_days' => 0,
    ];

    protected function casts(): array
    {
        return [
            'state' => ReviewState::class,
            'last_rating' => ReviewRating::class,
            'step' => 'integer',
            'stability' => 'float',
            'difficulty' => 'float',
            'due' => 'immutable_datetime',
            'last_review_at' => 'immutable_datetime',
            'reps' => 'integer',
            'lapses' => 'integer',
            'elapsed_days' => 'integer',
            'scheduled_days' => 'integer',
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

    public function toMemoryState(): MemoryState
    {
        return new MemoryState(
            state: $this->state,
            step: $this->step,
            stability: $this->stability,
            difficulty: $this->difficulty,
            due: $this->due,
            lastReview: $this->last_review_at,
            reps: $this->reps,
            lapses: $this->lapses,
        );
    }

    public function applyOutcome(ReviewOutcome $outcome): void
    {
        $after = $outcome->after;

        $this->fill([
            'state' => $after->state,
            'step' => $after->step,
            'stability' => $after->stability,
            'difficulty' => $after->difficulty,
            'due' => $after->due,
            'last_review_at' => $after->lastReview,
            'last_rating' => $outcome->rating,
            'reps' => $after->reps,
            'lapses' => $after->lapses,
            'elapsed_days' => $outcome->elapsedDays,
            'scheduled_days' => $outcome->scheduledDays,
        ]);
    }
}
