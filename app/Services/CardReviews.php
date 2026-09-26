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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
     * $state — уже загруженное состояние пары (очередь грузит их пачкой), иначе читается из БД.
     *
     * @return array<int, ReviewOutcome> ключ — ReviewRating::value
     */
    public function preview(User $user, Card $card, ?DateTimeInterface $now = null, ?CardReviewState $state = null): array
    {
        $state ??= $this->state($user, $card);

        return $this->scheduler->preview($state->toMemoryState(), self::moment($now), self::fuzzSeed($user, $card));
    }

    /** Оценка карточки: пересчёт состояния и запись в журнал в одной транзакции. */
    public function review(User $user, Card $card, ReviewRating $rating, ?DateTimeInterface $now = null, ?int $durationMs = null): CardReviewState
    {
        return $this->apply($user, $card, $rating, self::moment($now), $durationMs)[0];
    }

    /**
     * Идемпотентная оценка по клиентскому id (UUID): повтор с тем же id состояние не меняет
     * и возвращает исходную запись журнала. Тот же id с другой карточкой или оценкой — 409.
     */
    public function submit(User $user, Card $card, ReviewRating $rating, string $reviewId, ?DateTimeInterface $now = null, ?int $durationMs = null): CardReviewLog
    {
        $reviewId = strtolower($reviewId);

        if ($log = self::replay($user, $card, $rating, $reviewId)) {
            return $log;
        }

        try {
            return $this->apply($user, $card, $rating, self::moment($now), $durationMs, $reviewId)[1];
        } catch (UniqueConstraintViolationException $e) {
            // параллельный запрос с тем же id успел раньше (по другой карточке — строки разные)
            return self::replay($user, $card, $rating, $reviewId) ?? throw $e;
        }
    }

    /** @return array{CardReviewState, CardReviewLog} */
    private function apply(User $user, Card $card, ReviewRating $rating, CarbonImmutable $now, ?int $durationMs, ?string $reviewId = null): array
    {
        return DB::transaction(function () use ($user, $card, $rating, $now, $durationMs, $reviewId) {
            // Блокировка строки — параллельные оценки одной карточки считаются по очереди
            $state = self::pair($user, $card)->lockForUpdate()->first();
            if ($state === null) {
                CardReviewState::query()->createOrFirst(['user_id' => $user->id, 'card_id' => $card->id]);
                $state = self::pair($user, $card)->lockForUpdate()->firstOrFail();
            }

            // тот же id мог прийти, пока ждали блокировку
            if ($reviewId !== null && $log = self::replay($user, $card, $rating, $reviewId)) {
                return [$state, $log];
            }

            $outcome = $this->scheduler->review($state->toMemoryState(), $rating, $now, self::fuzzSeed($user, $card));
            $state->applyOutcome($outcome);
            $state->save();

            $log = CardReviewLog::query()->create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'review_id' => $reviewId,
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

            return [$state, $log];
        });
    }

    private static function replay(User $user, Card $card, ReviewRating $rating, string $reviewId): ?CardReviewLog
    {
        $log = CardReviewLog::query()->where('user_id', $user->id)->where('review_id', $reviewId)->first();
        if ($log !== null && ((int) $log->card_id !== (int) $card->id || $log->rating !== $rating)) {
            throw new ConflictHttpException('Этот review_id уже использован для другой оценки');
        }

        return $log;
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
