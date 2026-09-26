<?php

namespace App\Services\Fsrs;

use App\Enums\ReviewState;
use Carbon\CarbonImmutable;

/**
 * Состояние памяти по одной карточке для планировщика FSRS: без БД и привязки к пользователю.
 * step — номер шага изучения/переучивания (null в review); stability — в днях, difficulty — 1..10.
 */
final readonly class MemoryState
{
    public function __construct(
        public ReviewState $state = ReviewState::new,
        public ?int $step = null,
        public ?float $stability = null,
        public ?float $difficulty = null,
        public ?CarbonImmutable $due = null,
        public ?CarbonImmutable $lastReview = null,
        public int $reps = 0,
        public int $lapses = 0,
    ) {}

    public static function new(): self
    {
        return new self;
    }
}
