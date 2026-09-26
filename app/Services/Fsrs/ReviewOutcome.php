<?php

namespace App\Services\Fsrs;

use App\Enums\ReviewRating;
use Carbon\CarbonImmutable;

/**
 * Результат оценки: состояние до и после, интервал до следующего показа.
 * scheduledDays — целые дни интервала (0 для шагов в минутах), elapsedDays — дней с прошлой оценки.
 */
final readonly class ReviewOutcome
{
    public function __construct(
        public ReviewRating $rating,
        public CarbonImmutable $reviewedAt,
        public MemoryState $before,
        public MemoryState $after,
        public int $intervalSeconds,
        public int $elapsedDays,
        public int $scheduledDays,
    ) {}

    public function due(): CarbonImmutable
    {
        return $this->after->due;
    }
}
