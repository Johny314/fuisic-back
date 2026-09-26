<?php

namespace Tests\Feature\Review;

use App\Enums\ReviewRating;
use App\Enums\ReviewState;
use App\Models\Card\Card;
use App\Models\Card\CardReviewLog;
use App\Models\Card\CardReviewState;
use App\Models\User;
use App\Services\CardReviews;
use App\Services\Fsrs\Scheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Хранение состояния FSRS по паре «пользователь — карточка» и журнал оценок.
 */
class CardReviewsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Card $card;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->card = Card::factory()->create();
        $this->now = CarbonImmutable::parse('2026-09-26 10:00:00.750', 'UTC');
    }

    public function test_unreviewed_card_has_new_unsaved_state(): void
    {
        $state = $this->reviews()->state($this->user, $this->card);

        $this->assertFalse($state->exists);
        $this->assertSame(ReviewState::new, $state->state);
        $this->assertSame(0, $state->reps);
    }

    public function test_preview_for_new_card(): void
    {
        $this->withoutFuzz();

        $preview = $this->reviews()->preview($this->user, $this->card, $this->now);

        $this->assertSame([1 => 60, 2 => 330, 3 => 600, 4 => 16 * 86400], array_map(fn ($outcome) => $outcome->intervalSeconds, $preview));
        $this->assertSame('2026-09-26 10:10:00', $preview[ReviewRating::good->value]->due()->toDateTimeString());
        $this->assertSame(0, CardReviewState::query()->count());
    }

    public function test_review_saves_state_and_log(): void
    {
        $this->withoutFuzz();

        $state = $this->reviews()->review($this->user, $this->card, ReviewRating::good, $this->now, 4200);

        $state->refresh();
        $this->assertSame(ReviewState::learning, $state->state);
        $this->assertSame(1, $state->step);
        $this->assertEqualsWithDelta(3.173, $state->stability, 1e-9);
        $this->assertEqualsWithDelta(5.282434422319005, $state->difficulty, 1e-9);
        $this->assertSame('2026-09-26 10:10:00', $state->due->toDateTimeString());
        $this->assertSame('2026-09-26 10:00:00', $state->last_review_at->toDateTimeString());
        $this->assertSame(ReviewRating::good, $state->last_rating);
        $this->assertSame(1, $state->reps);

        $log = CardReviewLog::query()->sole();
        $this->assertSame(ReviewRating::good, $log->rating);
        $this->assertSame(4200, $log->duration_ms);
        $this->assertSame(ReviewState::new, $log->state_before);
        $this->assertSame(ReviewState::learning, $log->state_after);
        $this->assertNull($log->stability_before);
        $this->assertEqualsWithDelta(3.173, $log->stability_after, 1e-9);
        $this->assertSame(600, $log->interval_seconds);
        $this->assertSame('2026-09-26 10:10:00', $log->due_after->toDateTimeString());
    }

    public function test_repeated_reviews_update_one_state(): void
    {
        $this->withoutFuzz();
        $reviews = $this->reviews();

        $reviews->review($this->user, $this->card, ReviewRating::good, $this->now);
        $reviews->review($this->user, $this->card, ReviewRating::good, $this->now->addMinutes(10));
        $state = $reviews->review($this->user, $this->card, ReviewRating::again, $this->now->addDays(4)->addMinutes(10));

        $this->assertSame(1, CardReviewState::query()->count());
        $this->assertSame(3, CardReviewLog::query()->count());
        $this->assertSame(ReviewState::relearning, $state->state);
        $this->assertSame(3, $state->reps);
        $this->assertSame(1, $state->lapses);
        $this->assertSame(4, $state->elapsed_days);

        $lapse = CardReviewLog::query()->latest('id')->first();
        $this->assertSame(ReviewState::review, $lapse->state_before);
        $this->assertEqualsWithDelta(4.466858064362218, $lapse->stability_before, 1e-9);
        $this->assertSame(4, $lapse->elapsed_days);
    }

    public function test_preview_matches_review_with_fuzz(): void
    {
        $reviews = $this->reviews();
        $reviews->review($this->user, $this->card, ReviewRating::easy, $this->now);
        $next = $reviews->state($this->user, $this->card)->due;

        $preview = $reviews->preview($this->user, $this->card, $next);
        $state = $reviews->review($this->user, $this->card, ReviewRating::good, $next);

        $this->assertTrue($preview[ReviewRating::good->value]->due()->equalTo($state->due));
    }

    public function test_states_are_per_user(): void
    {
        $other = User::factory()->create();

        $this->reviews()->review($this->user, $this->card, ReviewRating::good, $this->now);

        $this->assertFalse($this->reviews()->state($other, $this->card)->exists);
        $this->assertTrue($this->reviews()->state($this->user, $this->card)->exists);
    }

    public function test_soft_deleted_card_keeps_progress(): void
    {
        $this->reviews()->review($this->user, $this->card, ReviewRating::good, $this->now);

        $this->card->delete();

        $this->assertSame(1, CardReviewState::query()->count());
        $this->assertSame(1, CardReviewLog::query()->count());
        $this->assertTrue(CardReviewState::query()->sole()->card->is($this->card));
    }

    public function test_force_deleting_card_or_user_removes_progress(): void
    {
        $otherCard = Card::factory()->create();
        $this->reviews()->review($this->user, $this->card, ReviewRating::good, $this->now);
        $this->reviews()->review($this->user, $otherCard, ReviewRating::good, $this->now);

        $this->card->forceDelete();
        $this->assertSame(1, CardReviewState::query()->count());
        $this->assertSame(1, CardReviewLog::query()->count());

        $this->user->forceDelete();
        $this->assertSame(0, CardReviewState::query()->count());
        $this->assertSame(0, CardReviewLog::query()->count());
    }

    private function reviews(): CardReviews
    {
        return $this->app->make(CardReviews::class);
    }

    private function withoutFuzz(): void
    {
        $this->app->instance(Scheduler::class, new Scheduler(enableFuzzing: false));
    }
}
