<?php

namespace App\Services;

use App\Enums\ReviewRating;
use App\Models\Card\Card;
use App\Models\Card\CardReviewLog;
use App\Models\Card\CardReviewState;
use App\Models\User;
use App\Services\Fsrs\ReviewOutcome;
use App\Services\Fsrs\Scheduler;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Интервальные повторения карточек пользователя: состояние FSRS, предпросмотр интервалов
 * и оценка с записью в журнал. Доступ к карточке проверяет вызывающий код.
 */
class CardReviews
{
    public function __construct(private readonly Scheduler $scheduler) {}

    /** Состояние пары; для ещё не оценённой карточки — новое, несохранённое. */
    public function state(User $user, Card $card): CardReviewState
    {
        return self::pair($user, $card)->firstOrNew([
            'user_id' => $user->id,
            'card_id' => $card->id,
        ]);
    }

    /**
     * Следующий интервал и дата показа для каждой оценки — подписи кнопок.
     *
     * @return array<int, ReviewOutcome> ключ — ReviewRating::value
     */
    public function preview(User $user, Card $card, ?DateTimeInterface $now = null): array
    {
        return $this->scheduler->preview($this->state($user, $card)->toMemoryState(), self::moment($now), self::fuzzSeed($user, $card));
    }

    /** Оценка карточки: пересчёт состояния и запись в журнал в одной транзакции. */
    public function review(User $user, Card $card, ReviewRating $rating, ?DateTimeInterface $now = null, ?int $durationMs = null): CardReviewState
    {
        $now = self::moment($now);

        return DB::transaction(function () use ($user, $card, $rating, $now, $durationMs) {
            // Блокировка строки — параллельные оценки одной карточки считаются по очереди
            $state = self::pair($user, $card)->lockForUpdate()->first();
            if ($state === null) {
                CardReviewState::query()->createOrFirst(['user_id' => $user->id, 'card_id' => $card->id]);
                $state = self::pair($user, $card)->lockForUpdate()->firstOrFail();
            }

            $outcome = $this->scheduler->review($state->toMemoryState(), $rating, $now, self::fuzzSeed($user, $card));
            $state->applyOutcome($outcome);
            $state->save();

            CardReviewLog::query()->create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'rating' => $rating,
                'reviewed_at' => $outcome->reviewedAt,
                'duration_ms' => $durationMs,
                'state_before' => $outcome->before->state,
                'state_after' => $outcome->after->state,
                'stability_before' => $outcome->before->stability,
                'stability_after' => $outcome->after->stability,
                'difficulty_before' => $outcome->before->difficulty,
                'difficulty_after' => $outcome->after->difficulty,
                'due_before' => $outcome->before->due,
                'due_after' => $outcome->after->due,
                'elapsed_days' => $outcome->elapsedDays,
                'scheduled_days' => $outcome->scheduledDays,
                'interval_seconds' => $outcome->intervalSeconds,
            ]);

            return $state;
        });
    }

    /** @return Builder<CardReviewState> */
    private static function pair(User $user, Card $card): Builder
    {
        return CardReviewState::query()->where('user_id', $user->id)->where('card_id', $card->id);
    }

    /** В БД время хранится с точностью до секунды — считаем от того же момента. */
    private static function moment(?DateTimeInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->startOfSecond();
    }

    /** Fuzz зависит от пары и числа повторений: предпросмотр и оценка дают одну дату. */
    private static function fuzzSeed(User $user, Card $card): string
    {
        return $user->id.':'.$card->id;
    }
}
