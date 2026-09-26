<?php

namespace Tests\Unit\Fsrs;

use App\Enums\ReviewRating;
use App\Enums\ReviewState;
use App\Services\Fsrs\MemoryState;
use App\Services\Fsrs\ReviewOutcome;
use App\Services\Fsrs\Scheduler;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Эталонные значения получены из py-fsrs v5.1.3 (FSRS-5, веса по умолчанию, enable_fuzzing=False)
 * для тех же последовательностей оценок и моментов времени; серия Good — тест test_review_card из py-fsrs.
 */
class SchedulerTest extends TestCase
{
    private const float EPS = 1e-9;

    private const int MIN = 60;

    private const int DAY = 86400;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = CarbonImmutable::parse('2022-11-29 12:30:00', 'UTC');
    }

    /** @return array<string, array{ReviewRating, ReviewState, ?int, float, float, int}> */
    public static function firstRatings(): array
    {
        return [
            'снова' => [ReviewRating::again, ReviewState::learning, 0, 0.40255, 7.1949, self::MIN],
            'трудно' => [ReviewRating::hard, ReviewState::learning, 0, 1.18385, 6.488305268471453, 330],
            'хорошо' => [ReviewRating::good, ReviewState::learning, 1, 3.173, 5.282434422319005, 10 * self::MIN],
            'легко' => [ReviewRating::easy, ReviewState::review, null, 15.69105, 3.2245015893713678, 16 * self::DAY],
        ];
    }

    #[DataProvider('firstRatings')]
    public function test_first_rating_of_new_card(ReviewRating $rating, ReviewState $state, ?int $step, float $stability, float $difficulty, int $interval): void
    {
        $outcome = $this->scheduler()->review(MemoryState::new(), $rating, $this->start);

        $this->assertSame($state, $outcome->after->state);
        $this->assertSame($step, $outcome->after->step);
        $this->assertEqualsWithDelta($stability, $outcome->after->stability, self::EPS);
        $this->assertEqualsWithDelta($difficulty, $outcome->after->difficulty, self::EPS);
        $this->assertSame($interval, $outcome->intervalSeconds);
        $this->assertTrue($this->start->addSeconds($interval)->equalTo($outcome->due()));
        $this->assertSame(1, $outcome->after->reps);
        $this->assertSame(0, $outcome->after->lapses);
        $this->assertSame(0, $outcome->elapsedDays);
        $this->assertSame(ReviewState::new, $outcome->before->state);
    }

    public function test_good_series_with_lapse_matches_reference(): void
    {
        $scheduler = $this->scheduler();
        $ratings = [3, 3, 3, 3, 3, 3, 1, 1, 3, 3, 3, 3, 3];
        $states = [];
        $intervals = [];
        $card = MemoryState::new();
        $now = $this->start;

        foreach ($ratings as $rating) {
            $outcome = $scheduler->review($card, ReviewRating::from($rating), $now);
            $card = $outcome->after;
            $intervals[] = $outcome->scheduledDays;
            $states[] = $card->state;
            $now = $card->due;
        }

        $this->assertSame([0, 4, 14, 44, 125, 328, 0, 0, 7, 16, 34, 71, 142], $intervals);
        $this->assertSame(ReviewState::relearning, $states[6]);
        $this->assertSame(ReviewState::relearning, $states[7]);
        $this->assertSame(ReviewState::review, $states[8]);
        $this->assertEqualsWithDelta(141.83400397565077, $card->stability, 1e-7);
        $this->assertEqualsWithDelta(7.689881918138517, $card->difficulty, self::EPS);
        $this->assertSame(13, $card->reps);
        // «Снова» в переучивании — не новая ошибка
        $this->assertSame(1, $card->lapses);
    }

    public function test_again_on_review_moves_to_relearning(): void
    {
        $scheduler = $this->scheduler();
        $card = $this->reviewSeries($scheduler, [3, 3, 3]);

        $outcome = $scheduler->review($card, ReviewRating::again, $card->due);

        $this->assertSame(ReviewState::relearning, $outcome->after->state);
        $this->assertSame(0, $outcome->after->step);
        $this->assertSame(10 * self::MIN, $outcome->intervalSeconds);
        $this->assertSame(0, $outcome->scheduledDays);
        $this->assertSame(14, $outcome->elapsedDays);
        $this->assertSame(1, $outcome->after->lapses);
        $this->assertEqualsWithDelta(2.504170139105898, $outcome->after->stability, self::EPS);
        $this->assertEqualsWithDelta(6.784232087673549, $outcome->after->difficulty, self::EPS);
    }

    public function test_relearning_step_for_each_rating(): void
    {
        $scheduler = $this->scheduler();
        $lapsed = $this->reviewSeries($scheduler, [3, 3, 3, 1]);

        $preview = $scheduler->preview($lapsed, $lapsed->due);

        $this->assertOutcome($preview[1], ReviewState::relearning, 0, 1.2546606691256805, 7.8066805373478925, 10 * self::MIN);
        $this->assertOutcome($preview[2], ReviewState::relearning, 0, 2.103105691310387, 7.2872689323646265, 15 * self::MIN);
        $this->assertOutcome($preview[3], ReviewState::review, null, 3.5252986386385876, 6.767857327381359, 4 * self::DAY);
        $this->assertOutcome($preview[4], ReviewState::review, null, 5.909227740163503, 6.248445722398091, 6 * self::DAY);
        $this->assertSame(1, $preview[1]->after->lapses);
    }

    public function test_second_learning_step_for_each_rating(): void
    {
        $scheduler = $this->scheduler();
        $card = $this->reviewSeries($scheduler, [3]);

        $preview = $scheduler->preview($card, $card->due);

        $this->assertOutcome($preview[1], ReviewState::learning, 0, 1.589763507266002, 6.796932579932991, self::MIN);
        $this->assertOutcome($preview[2], ReviewState::learning, 1, 2.664816680910697, 6.0349502556102195, 10 * self::MIN);
        $this->assertOutcome($preview[3], ReviewState::review, null, 4.466858064362218, 5.272967931287446, 4 * self::DAY);
        $this->assertOutcome($preview[4], ReviewState::review, null, 7.487502277394533, 4.510985606964673, 7 * self::DAY);
    }

    public function test_hard_and_easy_multipliers_on_review(): void
    {
        $scheduler = $this->scheduler();
        $card = $this->reviewSeries($scheduler, [3, 3]);

        $preview = $scheduler->preview($card, $card->due);

        $this->assertOutcome($preview[1], ReviewState::relearning, 0, 1.2976302777410647, 6.790567694566929, 10 * self::MIN);
        $this->assertOutcome($preview[2], ReviewState::review, null, 6.724081693772751, 6.0270563403407795, 7 * self::DAY);
        $this->assertOutcome($preview[3], ReviewState::review, null, 14.217284109332127, 5.263544986114632, 14 * self::DAY);
        $this->assertOutcome($preview[4], ReviewState::review, null, 33.618681853613246, 4.500033631888484, 34 * self::DAY);
    }

    public function test_overdue_review_grows_stability_more(): void
    {
        $scheduler = $this->scheduler();
        $card = $this->reviewSeries($scheduler, [3, 3]);
        $late = $card->due->addDays(30);

        $this->assertEqualsWithDelta(0.9090714482930368, $scheduler->retrievability($card, $card->due), self::EPS);
        $this->assertEqualsWithDelta(0.5991741500148343, $scheduler->retrievability($card, $late), self::EPS);

        $preview = $scheduler->preview($card, $late);

        $this->assertSame(34, $preview[3]->elapsedDays);
        $this->assertOutcome($preview[1], ReviewState::relearning, 0, 2.6220189834659102, 6.790567694566929, 10 * self::MIN);
        $this->assertOutcome($preview[2], ReviewState::review, null, 16.196356431183087, 6.0270563403407795, 16 * self::DAY);
        $this->assertOutcome($preview[3], ReviewState::review, null, 55.13423761866403, 5.263544986114632, 55 * self::DAY);
        $this->assertOutcome($preview[4], ReviewState::review, null, 155.95218945581377, 4.500033631888484, 156 * self::DAY);
    }

    public function test_again_series_on_new_card(): void
    {
        $scheduler = $this->scheduler();
        $expected = [
            [0.40255, 7.1949],
            [0.20168903241409677, 8.082797017759107],
            [0.10105195825645157, 8.679783030429052],
            [0.050629913512093935, 9.08117225935453],
            [0.02536703084700939, 9.351050128648035],
        ];
        $card = MemoryState::new();
        $now = $this->start;

        foreach ($expected as [$stability, $difficulty]) {
            $outcome = $scheduler->review($card, ReviewRating::again, $now);
            $card = $outcome->after;
            $now = $card->due;

            $this->assertOutcome($outcome, ReviewState::learning, 0, $stability, $difficulty, self::MIN);
        }

        // Ошибки в изучении не считаются lapses; после них — минимальный интервал в 1 день
        $this->assertSame(0, $card->lapses);
        $outcome = $scheduler->review($card, ReviewRating::good, $now);
        $this->assertOutcome($outcome, ReviewState::learning, 1, 0.035710975829779085, 9.322868005367361, 10 * self::MIN);
        $outcome = $scheduler->review($outcome->after, ReviewRating::good, $outcome->due());
        $this->assertOutcome($outcome, ReviewState::review, null, 0.05027288382335107, 9.29481551985378, self::DAY);
    }

    public function test_maximum_interval_limits_schedule(): void
    {
        $scheduler = new Scheduler(maximumInterval: 100, enableFuzzing: false);
        $card = MemoryState::new();
        $now = $this->start;
        $intervals = [];

        for ($i = 0; $i < 6; $i++) {
            $outcome = $scheduler->review($card, ReviewRating::easy, $now);
            $card = $outcome->after;
            $now = $card->due;
            $intervals[] = $outcome->scheduledDays;
        }

        $this->assertSame([16, 100, 100, 100, 100, 100], $intervals);
        $this->assertEqualsWithDelta(2996.109849680189, $card->stability, 1e-6);
        $this->assertEqualsWithDelta(1.0, $card->difficulty, self::EPS);
    }

    public function test_default_maximum_interval_is_100_years(): void
    {
        $scheduler = $this->scheduler();
        $card = new MemoryState(ReviewState::review, null, 1_000_000.0, 1.0, $this->start, $this->start->subDays(36500), 10);

        $this->assertSame(36500, $scheduler->review($card, ReviewRating::easy, $this->start)->scheduledDays);
    }

    public function test_desired_retention_changes_interval(): void
    {
        $outcome = (new Scheduler(desiredRetention: 0.8, enableFuzzing: false))
            ->review(MemoryState::new(), ReviewRating::easy, $this->start);

        $this->assertSame(38, $outcome->scheduledDays);
    }

    public function test_without_learning_steps(): void
    {
        $scheduler = new Scheduler(learningSteps: [], relearningSteps: [], enableFuzzing: false);

        $first = $scheduler->review(MemoryState::new(), ReviewRating::good, $this->start);
        $this->assertOutcome($first, ReviewState::review, null, 3.173, 5.282434422319005, 3 * self::DAY);

        $lapse = $scheduler->review($first->after, ReviewRating::again, $first->due());
        $this->assertOutcome($lapse, ReviewState::review, null, 1.0555610912864883, 6.796932579932991, self::DAY);
        $this->assertSame(1, $lapse->after->lapses);
    }

    public function test_fuzz_stays_in_range_and_is_reproducible_with_seeded_generator(): void
    {
        $plain = $this->reviewSeries($this->scheduler(), [3, 3]);
        $days = [];

        for ($seed = 0; $seed < 200; $seed++) {
            $fuzzed = new Scheduler(randomizer: new Randomizer(new Mt19937($seed)));
            $again = new Scheduler(randomizer: new Randomizer(new Mt19937($seed)));

            // Без fuzz — 14 дней; диапазон FSRS: 14 ± 2,375 → [12, 16]
            $outcome = $fuzzed->review($plain, ReviewRating::good, $plain->due);
            $days[] = $outcome->scheduledDays;
            $this->assertSame($outcome->scheduledDays, $again->review($plain, ReviewRating::good, $plain->due)->scheduledDays);
            $this->assertSame($outcome->scheduledDays * self::DAY, $outcome->intervalSeconds);
        }

        $this->assertSame(12, min($days));
        $this->assertSame(16, max($days));
    }

    public function test_fuzz_skips_short_intervals_and_learning_steps(): void
    {
        $scheduler = new Scheduler(randomizer: new Randomizer(new Mt19937(1)));

        $this->assertSame(10 * self::MIN, $scheduler->review(MemoryState::new(), ReviewRating::good, $this->start)->intervalSeconds);

        $weak = $this->reviewSeries($this->scheduler(), [1, 1, 1, 1, 1, 3]);
        $this->assertSame(1, $scheduler->review($weak, ReviewRating::good, $weak->due)->scheduledDays);
    }

    public function test_fuzz_respects_maximum_interval(): void
    {
        $card = new MemoryState(ReviewState::review, null, 1_000_000.0, 1.0, $this->start, $this->start->subDays(100), 10);

        for ($seed = 0; $seed < 50; $seed++) {
            $scheduler = new Scheduler(maximumInterval: 100, randomizer: new Randomizer(new Mt19937($seed)));

            $this->assertLessThanOrEqual(100, $scheduler->review($card, ReviewRating::easy, $this->start)->scheduledDays);
        }
    }

    public function test_seeded_fuzz_matches_between_preview_and_review(): void
    {
        $card = $this->reviewSeries($this->scheduler(), [3, 3, 3]);
        $scheduler = new Scheduler;
        $now = $card->due;

        $preview = $scheduler->preview($card, $now, '7:42');

        foreach (ReviewRating::cases() as $rating) {
            $outcome = (new Scheduler)->review($card, $rating, $now, '7:42');

            $this->assertTrue($preview[$rating->value]->due()->equalTo($outcome->due()), $rating->name);
        }
    }

    public function test_preview_returns_all_ratings_without_changing_state(): void
    {
        $card = $this->reviewSeries($this->scheduler(), [3]);

        $preview = $this->scheduler()->preview($card, $card->due);

        $this->assertSame([1, 2, 3, 4], array_keys($preview));
        foreach ($preview as $value => $outcome) {
            $this->assertSame(ReviewRating::from($value), $outcome->rating);
            $this->assertSame($card, $outcome->before);
        }
        $this->assertSame(1, $card->reps);
        $this->assertSame(ReviewState::learning, $card->state);
    }

    public function test_same_day_review_uses_short_term_stability(): void
    {
        $scheduler = $this->scheduler();
        $card = $this->reviewSeries($scheduler, [3, 3]);

        // Повтор через час после перехода в review: краткосрочная формула S·e^(w17·(G−3+w18))
        $outcome = $scheduler->review($card, ReviewRating::good, $card->lastReview->addHour());

        $expected = $card->stability * exp(0.51655 * 0.6621);
        $this->assertEqualsWithDelta($expected, $outcome->after->stability, self::EPS);
        $this->assertSame(0, $outcome->elapsedDays);
    }

    public function test_rejects_invalid_parameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Scheduler(parameters: [1.0, 2.0]);
    }

    public function test_rejects_invalid_retention(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Scheduler(desiredRetention: 1.0);
    }

    private function scheduler(): Scheduler
    {
        return new Scheduler(enableFuzzing: false);
    }

    /**
     * Оценки подряд, каждая — в момент due предыдущей.
     *
     * @param  list<int>  $ratings
     */
    private function reviewSeries(Scheduler $scheduler, array $ratings): MemoryState
    {
        $card = MemoryState::new();
        $now = $this->start;

        foreach ($ratings as $rating) {
            $card = $scheduler->review($card, ReviewRating::from($rating), $now)->after;
            $now = $card->due;
        }

        return $card;
    }

    private function assertOutcome(ReviewOutcome $outcome, ReviewState $state, ?int $step, float $stability, float $difficulty, int $interval): void
    {
        $this->assertSame($state, $outcome->after->state);
        $this->assertSame($step, $outcome->after->step);
        $this->assertEqualsWithDelta($stability, $outcome->after->stability, self::EPS);
        $this->assertEqualsWithDelta($difficulty, $outcome->after->difficulty, self::EPS);
        $this->assertSame($interval, $outcome->intervalSeconds);
    }
}
