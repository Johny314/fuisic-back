<?php

namespace App\Services\Fsrs;

use App\Enums\ReviewRating;
use App\Enums\ReviewState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Планировщик интервальных повторений FSRS-5 (19 весов, DECAY = -0.5) — чистые вычисления, без БД.
 *
 * Формулы, шаги изучения и fuzz перенесены из эталонной реализации py-fsrs v5.1.3
 * (https://github.com/open-spaced-repetition/py-fsrs/blob/v5.1.3/fsrs/fsrs.py),
 * описание алгоритма: https://github.com/open-spaced-repetition/fsrs4anki/wiki/The-Algorithm (FSRS-5).
 * Отличия: состояние new до первой оценки (в py-fsrs — learning с шагом 0); счётчики reps/lapses;
 * fuzz берёт floor, а не round, как ts-fsrs — интервал не выходит за верхнюю границу диапазона;
 * округление интервала — PHP round (половина от нуля), в Python — банковское (на практике не различаются).
 */
final class Scheduler
{
    public const string VERSION = 'FSRS-5';

    /** Веса FSRS-5 по умолчанию (py-fsrs v5.1.3 DEFAULT_PARAMETERS). */
    public const array DEFAULT_PARAMETERS = [
        0.40255, 1.18385, 3.173, 15.69105, 7.1949, 0.5345, 1.4604, 0.0046, 1.54575, 0.1192,
        1.01925, 1.9395, 0.11, 0.29605, 2.2698, 0.2315, 2.9898, 0.51655, 0.6621,
    ];

    private const float DECAY = -0.5;

    /** 0.9^(1/DECAY) - 1 = 19/81: при R = 0,9 интервал равен стабильности. */
    private const float FACTOR = 0.9 ** (1 / self::DECAY) - 1;

    private const int DAY = 86400;

    /** Диапазоны fuzz: [от, до, доля] в днях. */
    private const array FUZZ_RANGES = [[2.5, 7.0, 0.15], [7.0, 20.0, 0.1], [20.0, INF, 0.05]];

    /**
     * @param  list<float>  $parameters  19 весов FSRS-5
     * @param  list<int>  $learningSteps  шаги изучения новой карточки, секунды
     * @param  list<int>  $relearningSteps  шаги переучивания после «Снова», секунды
     * @param  int  $maximumInterval  максимальный интервал, дни
     * @param  Randomizer|null  $randomizer  генератор для fuzz без seed (в тестах — с фиксированным engine)
     */
    public function __construct(
        private readonly array $parameters = self::DEFAULT_PARAMETERS,
        private readonly float $desiredRetention = 0.9,
        private readonly array $learningSteps = [60, 600],
        private readonly array $relearningSteps = [600],
        private readonly int $maximumInterval = 36500,
        private readonly bool $enableFuzzing = true,
        private ?Randomizer $randomizer = null,
    ) {
        if (count($parameters) !== 19) {
            throw new InvalidArgumentException('FSRS-5 требует 19 весов');
        }
        if ($desiredRetention <= 0 || $desiredRetention >= 1) {
            throw new InvalidArgumentException('Желаемая удерживаемость — от 0 до 1');
        }
        if ($maximumInterval < 1) {
            throw new InvalidArgumentException('Максимальный интервал — не меньше дня');
        }
    }

    /**
     * Оценка карточки в момент $now. $fuzzSeed (например, «пользователь:карточка») делает fuzz
     * детерминированным для данного числа повторений — предпросмотр и оценка дадут одну дату.
     */
    public function review(MemoryState $card, ReviewRating $rating, DateTimeInterface $now, ?string $fuzzSeed = null): ReviewOutcome
    {
        $now = CarbonImmutable::instance($now);
        $elapsedDays = $card->lastReview === null
            ? null
            : (int) floor(($now->getTimestamp() - $card->lastReview->getTimestamp()) / self::DAY);

        [$stability, $difficulty] = $this->nextMemory($card, $rating, $now, $elapsedDays);

        $lapses = $card->lapses;
        [$state, $step, $interval] = match ($card->state) {
            ReviewState::new, ReviewState::learning => $this->stepTransition(
                $this->learningSteps, $card->step ?? 0, $rating, $stability, ReviewState::learning,
            ),
            ReviewState::relearning => $this->stepTransition(
                $this->relearningSteps, $card->step ?? 0, $rating, $stability, ReviewState::relearning,
            ),
            ReviewState::review => $this->reviewTransition($rating, $stability),
        };
        if ($card->state === ReviewState::review && $rating === ReviewRating::again) {
            $lapses++;
        }

        if ($this->enableFuzzing && $state === ReviewState::review) {
            $interval = $this->fuzz(intdiv($interval, self::DAY), $card, $fuzzSeed) * self::DAY;
        }

        $after = new MemoryState(
            state: $state,
            step: $step,
            stability: $stability,
            difficulty: $difficulty,
            due: $now->addSeconds($interval),
            lastReview: $now,
            reps: $card->reps + 1,
            lapses: $lapses,
        );

        return new ReviewOutcome(
            rating: $rating,
            reviewedAt: $now,
            before: $card,
            after: $after,
            intervalSeconds: $interval,
            elapsedDays: max(0, $elapsedDays ?? 0),
            scheduledDays: intdiv($interval, self::DAY),
        );
    }

    /**
     * Что будет при каждой из четырёх оценок — для подписей кнопок («через 10 мин / 3 дня»).
     *
     * @return array<int, ReviewOutcome> ключ — ReviewRating::value
     */
    public function preview(MemoryState $card, DateTimeInterface $now, ?string $fuzzSeed = null): array
    {
        $outcomes = [];
        foreach (ReviewRating::cases() as $rating) {
            $outcomes[$rating->value] = $this->review($card, $rating, $now, $fuzzSeed);
        }

        return $outcomes;
    }

    /** Вероятность вспомнить карточку в момент $now (0 — ещё не оценивалась). */
    public function retrievability(MemoryState $card, DateTimeInterface $now): float
    {
        if ($card->lastReview === null || $card->stability === null) {
            return 0.0;
        }

        $elapsedDays = max(0, (int) floor(($now->getTimestamp() - $card->lastReview->getTimestamp()) / self::DAY));

        return (1 + self::FACTOR * $elapsedDays / $card->stability) ** self::DECAY;
    }

    /** @return array{float, float} стабильность и сложность после оценки */
    private function nextMemory(MemoryState $card, ReviewRating $rating, CarbonImmutable $now, ?int $elapsedDays): array
    {
        if ($card->stability === null || $card->difficulty === null) {
            return [$this->initialStability($rating), $this->initialDifficulty($rating)];
        }

        // Повтор в тот же день — краткосрочная формула, иначе — по вероятности вспомнить
        $stability = $elapsedDays !== null && $elapsedDays < 1
            ? $this->shortTermStability($card->stability, $rating)
            : $this->nextStability($card->difficulty, $card->stability, $this->retrievability($card, $now), $rating);

        return [$stability, $this->nextDifficulty($card->difficulty, $rating)];
    }

    /**
     * Переход по шагам изучения/переучивания.
     *
     * @param  list<int>  $steps
     * @return array{ReviewState, ?int, int} состояние, шаг, интервал в секундах
     */
    private function stepTransition(array $steps, int $step, ReviewRating $rating, float $stability, ReviewState $stepState): array
    {
        $count = count($steps);
        // Шагов нет или карточка прошла больше шагов, чем настроено сейчас
        if ($count === 0 || ($step >= $count && $rating !== ReviewRating::again)) {
            return $this->graduate($stability);
        }

        return match ($rating) {
            ReviewRating::again => [$stepState, 0, $steps[0]],
            ReviewRating::hard => [$stepState, $step, match (true) {
                $step === 0 && $count === 1 => (int) round($steps[0] * 1.5),
                $step === 0 => (int) round(($steps[0] + $steps[1]) / 2),
                default => $steps[$step],
            }],
            ReviewRating::good => $step + 1 === $count
                ? $this->graduate($stability)
                : [$stepState, $step + 1, $steps[$step + 1]],
            ReviewRating::easy => $this->graduate($stability),
        };
    }

    /** @return array{ReviewState, ?int, int} */
    private function reviewTransition(ReviewRating $rating, float $stability): array
    {
        if ($rating === ReviewRating::again && $this->relearningSteps !== []) {
            return [ReviewState::relearning, 0, $this->relearningSteps[0]];
        }

        return $this->graduate($stability);
    }

    /** @return array{ReviewState, null, int} */
    private function graduate(float $stability): array
    {
        return [ReviewState::review, null, $this->nextIntervalDays($stability) * self::DAY];
    }

    private function nextIntervalDays(float $stability): int
    {
        $interval = $stability / self::FACTOR * ($this->desiredRetention ** (1 / self::DECAY) - 1);

        return min(max((int) round($interval), 1), $this->maximumInterval);
    }

    private function initialStability(ReviewRating $rating): float
    {
        return max($this->w($rating->value - 1), 0.1);
    }

    private function initialDifficulty(ReviewRating $rating): float
    {
        return $this->clampDifficulty($this->w(4) - exp($this->w(5) * ($rating->value - 1)) + 1);
    }

    private function shortTermStability(float $stability, ReviewRating $rating): float
    {
        return $stability * exp($this->w(17) * ($rating->value - 3 + $this->w(18)));
    }

    /** Линейное затухание к 10 и возврат к среднему (сложности первой оценки «Легко»). */
    private function nextDifficulty(float $difficulty, ReviewRating $rating): float
    {
        $delta = -$this->w(6) * ($rating->value - 3);
        $damped = $difficulty + (10 - $difficulty) * $delta / 9;
        $reverted = $this->w(7) * $this->initialDifficulty(ReviewRating::easy) + (1 - $this->w(7)) * $damped;

        return $this->clampDifficulty($reverted);
    }

    private function nextStability(float $difficulty, float $stability, float $retrievability, ReviewRating $rating): float
    {
        if ($rating === ReviewRating::again) {
            $longTerm = $this->w(11)
                * $difficulty ** -$this->w(12)
                * (($stability + 1) ** $this->w(13) - 1)
                * exp((1 - $retrievability) * $this->w(14));
            $shortTerm = $stability / exp($this->w(17) * $this->w(18));

            return min($longTerm, $shortTerm);
        }

        $hardPenalty = $rating === ReviewRating::hard ? $this->w(15) : 1;
        $easyBonus = $rating === ReviewRating::easy ? $this->w(16) : 1;

        return $stability * (1
            + exp($this->w(8))
            * (11 - $difficulty)
            * $stability ** -$this->w(9)
            * (exp((1 - $retrievability) * $this->w(10)) - 1)
            * $hardPenalty
            * $easyBonus);
    }

    /** Случайный сдвиг интервала от 3 дней, чтобы карточки одного дня не шли потом одной пачкой. */
    private function fuzz(int $days, MemoryState $before, ?string $seed): int
    {
        if ($days < 2.5) {
            return $days;
        }

        $delta = 1.0;
        foreach (self::FUZZ_RANGES as [$start, $end, $factor]) {
            $delta += $factor * max(min($days, $end) - $start, 0.0);
        }

        $max = min((int) round($days + $delta), $this->maximumInterval);
        $min = min(max(2, (int) round($days - $delta)), $max);

        return (int) floor($this->fuzzFactor($before, $seed) * ($max - $min + 1)) + $min;
    }

    /** Число из [0, 1). */
    private function fuzzFactor(MemoryState $before, ?string $seed): float
    {
        if ($seed !== null) {
            return (new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed.'|'.$before->reps, true))))->nextFloat();
        }

        return ($this->randomizer ??= new Randomizer)->nextFloat();
    }

    private function w(int $index): float
    {
        return $this->parameters[$index];
    }

    private function clampDifficulty(float $difficulty): float
    {
        return min(max($difficulty, 1.0), 10.0);
    }
}
