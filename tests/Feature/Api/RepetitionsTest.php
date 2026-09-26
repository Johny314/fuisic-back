<?php

namespace Tests\Feature\Api;

use App\Enums\ReviewState;
use App\Models\Card\Card;
use App\Models\Card\CardReviewLog;
use App\Models\Card\CardReviewState;
use App\Models\Card\CardSet;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\LocalDay;
use App\Support\RepetitionLimits;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «Мои повторения», очередь «На сегодня» и оценка карточек через API.
 */
class RepetitionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private CardSet $set;

    /** @var list<Card> */
    private array $cards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00:00', 'UTC'));

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->create();
        $this->set = CardSet::factory()->for($this->admin)->create();
        $this->cards = Card::factory()->for($this->set)->count(3)->create()->sortBy('id')->values()->all();
    }

    public function test_guest_gets_401(): void
    {
        $card = $this->cards[0];

        $this->getJson('/repetitions')->assertUnauthorized();
        $this->getJson('/repetitions/queue')->assertUnauthorized();
        $this->postJson("/repetitions/{$this->set->id}")->assertUnauthorized();
        $this->deleteJson("/repetitions/{$this->set->id}")->assertUnauthorized();
        $this->postJson("/card/{$card->id}/review", ['review_id' => (string) Str::uuid(), 'rating' => 3])->assertUnauthorized();
    }

    public function test_user_adds_lists_and_removes_sets(): void
    {
        $own = CardSet::factory()->for($this->student)->create();
        Card::factory()->for($own)->create();
        $private = CardSet::factory()->for(User::factory())->create();
        Sanctum::actingAs($this->student);

        $this->postJson("/repetitions/{$this->set->id}")
            ->assertCreated()
            ->assertJsonPath('card_set.id', $this->set->id)
            ->assertJsonPath('card_set.user.name', $this->admin->name)
            ->assertJsonMissingPath('card_set.user.email')
            ->assertJsonPath('cards_count', 3)
            ->assertJsonPath('due_count', 0)
            ->assertJsonPath('new_count', 3);
        $this->postJson("/repetitions/{$this->set->id}")->assertOk()->assertJsonPath('card_set.id', $this->set->id);
        $this->postJson("/repetitions/{$own->id}")->assertCreated()->assertJsonPath('cards_count', 1);

        $this->postJson("/repetitions/{$private->id}")->assertForbidden();
        $this->postJson('/repetitions/999999')->assertNotFound();

        $this->getJson('/repetitions')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.card_set.id', $this->set->id)
            ->assertJsonPath('1.card_set.id', $own->id);

        $this->deleteJson("/repetitions/{$this->set->id}")->assertOk();
        $this->deleteJson("/repetitions/{$private->id}")->assertOk();
        $this->getJson('/repetitions')->assertJsonCount(1)->assertJsonPath('0.card_set.id', $own->id);
        $this->assertDatabaseCount('card_set_repetitions', 1);
    }

    public function test_sets_limit_comes_from_repetition_limits(): void
    {
        $this->app->instance(RepetitionLimits::class, new class extends RepetitionLimits
        {
            public function maxSetsInRepetition(User $user): ?int
            {
                return 1;
            }
        });
        $other = CardSet::factory()->for($this->admin)->create();
        Sanctum::actingAs($this->student);

        $this->postJson("/repetitions/{$this->set->id}")->assertCreated();
        $this->postJson("/repetitions/{$this->set->id}")->assertOk();
        $this->postJson("/repetitions/{$other->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'repetition_sets_limit')
            ->assertJsonPath('limit', 1);
    }

    public function test_full_scenario_add_queue_review_and_due_dates(): void
    {
        [$c1, $c2, $c3] = $this->cards;
        $this->student->settings()->create(['timezone' => 'Europe/Moscow']);
        Sanctum::actingAs($this->student);
        $this->postJson("/repetitions/{$this->set->id}")->assertCreated();

        $queue = $this->queue()
            ->assertJsonPath('day', '2026-09-26')
            ->assertJsonPath('day_ends_at', '2026-09-27T01:00:00.000000Z')
            ->assertJsonPath('due_count', 0)
            ->assertJsonPath('new_count', 3)
            ->assertJsonPath('new_limit', 20)
            ->assertJsonPath('new_started_today', 0)
            ->assertJsonPath('next_due_at', null)
            ->assertJsonPath('cards.0.state', 'new')
            ->assertJsonPath('cards.0.due', null)
            ->assertJsonPath('cards.0.card.front_text', $c1->front_text)
            ->assertJsonPath('cards.0.card_set_id', $this->set->id)
            ->assertJsonPath('cards.0.intervals.*.rating', [1, 2, 3, 4])
            ->assertJsonPath('cards.0.intervals.*.label', ['Снова', 'Трудно', 'Хорошо', 'Легко'])
            ->assertJsonPath('cards.0.intervals.2.interval_seconds', 600);
        $this->assertSame([$c1->id, $c2->id, $c3->id], $this->ids($queue));
        $easyDue = $queue->json('cards.0.intervals.3.due');

        // оценка даёт ту же дату, что подпись кнопки
        $this->review($c1, 4)->assertCreated()->assertJsonPath('state', 'review')->assertJsonPath('due', $easyDue);
        $this->review($c2, 3)->assertJsonPath('state', 'learning')->assertJsonPath('due', '2026-09-26T10:10:00.000000Z');
        $this->review($c3, 1)->assertJsonPath('state', 'learning')->assertJsonPath('due', '2026-09-26T10:01:00.000000Z');

        // шаги 1 и 10 мин — в конце очереди уже сейчас, новых больше нет
        $queue = $this->queue()
            ->assertJsonPath('due_count', 2)
            ->assertJsonPath('new_count', 0)
            ->assertJsonPath('new_started_today', 3);
        $this->assertSame([$c3->id, $c2->id], $this->ids($queue));

        $this->travel(15)->minutes();
        $this->assertSame([$c3->id, $c2->id], $this->ids($this->queue()));
        $this->review($c3, 3)->assertJsonPath('state', 'learning');
        $c2Due = CarbonImmutable::parse($this->review($c2, 3)->assertJsonPath('state', 'review')->json('due'));

        // шаг 10 мин за пределом 20 мин не показывается, но отдаётся время его возвращения
        $this->travel(-15)->minutes();
        $this->queue()->assertJsonPath('cards', [])->assertJsonPath('next_due_at', '2026-09-26T10:25:00.000000Z');
        $this->travel(15)->minutes();

        $this->assertComesOnDueDay($c2, $c2Due);
        $this->assertComesOnDueDay($c1, CarbonImmutable::parse($easyDue));

        $this->getJson('/repetitions')->assertJsonPath('0.due_count', 3)->assertJsonPath('0.new_count', 0);
    }

    public function test_daily_new_limit_follows_user_timezone(): void
    {
        $extra = Card::factory()->for($this->set)->count(3)->create()->sortBy('id')->values();
        $tashkent = User::factory()->create();
        $tashkent->settings()->create(['timezone' => 'Asia/Tashkent', 'new_cards_per_day' => 2]);
        $newYork = User::factory()->create();
        $newYork->settings()->create(['timezone' => 'America/New_York', 'new_cards_per_day' => 2]);

        foreach ([$tashkent, $newYork] as $user) {
            Sanctum::actingAs($user);
            $this->postJson("/repetitions/{$this->set->id}")->assertCreated();
            $queue = $this->queue()->assertJsonPath('new_count', 2)->assertJsonPath('new_limit', 2);
            $this->assertSame([$this->cards[0]->id, $this->cards[1]->id], $this->ids($queue));
            $this->queue(['limit' => 1])->assertJsonCount(1, 'cards')->assertJsonPath('new_count', 2);

            $this->review($this->cards[0], 3);
            $this->review($this->cards[1], 4);
            $this->queue()->assertJsonPath('new_count', 0)->assertJsonPath('new_started_today', 2);
        }

        // 03:59:59 в Ташкенте (UTC+5) — ещё тот же день
        $this->travelTo(CarbonImmutable::parse('2026-09-26 22:59:59', 'UTC'));
        Sanctum::actingAs($tashkent);
        $this->queue()->assertJsonPath('day', '2026-09-26')->assertJsonPath('new_count', 0);

        // 04:30 в Ташкенте — новый день; в Нью-Йорке (UTC-4) 19:30 того же дня
        $this->travelTo(CarbonImmutable::parse('2026-09-26 23:30:00', 'UTC'));
        $queue = $this->queue()
            ->assertJsonPath('day', '2026-09-27')
            ->assertJsonPath('new_count', 2)
            ->assertJsonPath('new_started_today', 0);
        // сначала просроченный шаг изучения, затем две новые
        $this->assertSame([$this->cards[0]->id, $this->cards[2]->id, $extra[0]->id], $this->ids($queue));

        Sanctum::actingAs($newYork);
        $this->queue()->assertJsonPath('day', '2026-09-26')->assertJsonPath('new_count', 0);

        $this->travelTo(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
        $this->queue()->assertJsonPath('day', '2026-09-27')->assertJsonPath('new_count', 2);
    }

    public function test_review_is_idempotent_by_review_id(): void
    {
        [$card, $other] = $this->cards;
        Sanctum::actingAs($this->student);
        $id = strtoupper((string) Str::uuid());

        $first = $this->review($card, 3, $id, ['duration_ms' => 99_000_000])->assertCreated()->json();
        $this->travel(5)->minutes();
        $this->review($card, 3, $id)->assertOk()->assertExactJson($first);

        $this->assertSame(strtolower($id), $first['review_id']);
        $this->assertSame(1, CardReviewLog::query()->count());
        $this->assertSame(3_600_000, CardReviewLog::query()->sole()->duration_ms);
        $this->assertSame(1, CardReviewState::query()->sole()->reps);

        $this->review($card, 1, $id)->assertConflict();
        $this->review($other, 3, $id)->assertConflict();
        $this->assertSame(1, CardReviewLog::query()->count());

        // тот же id у другого пользователя — его собственная оценка
        Sanctum::actingAs(User::factory()->create());
        $this->review($card, 3, $id)->assertCreated();
        $this->assertSame(2, CardReviewLog::query()->count());
    }

    public function test_review_validation(): void
    {
        Sanctum::actingAs($this->student);
        $card = $this->cards[0];

        $this->postJson("/card/{$card->id}/review", ['rating' => 3])->assertUnprocessable()->assertJsonValidationErrors('review_id');
        $this->postJson("/card/{$card->id}/review", ['review_id' => 'abc', 'rating' => 3])->assertJsonValidationErrors('review_id');
        $this->review($card, 5)->assertJsonValidationErrors('rating');
        $this->review($card, 3, extra: ['duration_ms' => -1])->assertJsonValidationErrors('duration_ms');
        $this->assertSame(0, CardReviewLog::query()->count());
    }

    public function test_progress_is_per_user_and_private_cards_are_not_rated(): void
    {
        $card = $this->cards[0];
        $private = Card::factory()->for(CardSet::factory()->for(User::factory()))->create();
        Sanctum::actingAs($this->student);
        $this->postJson("/repetitions/{$this->set->id}");
        $this->review($card, 4);

        $this->review($private, 3)->assertForbidden();

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/repetitions')->assertExactJson([]);
        $this->postJson("/repetitions/{$this->set->id}")->assertJsonPath('new_count', 3);
        $this->queue()->assertJsonPath('new_count', 3)->assertJsonPath('cards.0.state', 'new')->assertJsonPath('cards.0.reps', 0);
        $this->assertSame(0, CardReviewState::query()->where('user_id', $other->id)->count());
    }

    public function test_set_hidden_from_catalog_is_not_served(): void
    {
        Sanctum::actingAs($this->student);
        $this->postJson("/repetitions/{$this->set->id}")->assertCreated();
        $this->review($this->cards[0], 1);

        UserBlock::query()->create(['user_id' => $this->admin->id, 'blocked_by_id' => User::factory()->admin()->create()->id, 'reason' => 'Нарушение']);

        $this->getJson('/repetitions')->assertExactJson([]);
        $this->queue()->assertJsonPath('cards', [])->assertJsonPath('due_count', 0)->assertJsonPath('new_count', 0);
        $this->queue(['card_set_id' => $this->set->id])->assertNotFound();
        $this->review($this->cards[1], 3)->assertForbidden();

        // убрать скрытый набор можно
        $this->deleteJson("/repetitions/{$this->set->id}")->assertOk();
        $this->assertDatabaseCount('card_set_repetitions', 0);
    }

    public function test_queue_for_single_set(): void
    {
        $other = CardSet::factory()->for($this->admin)->create();
        $otherCard = Card::factory()->for($other)->create();
        Sanctum::actingAs($this->student);

        $this->queue(['card_set_id' => $this->set->id])->assertNotFound();
        $this->postJson("/repetitions/{$this->set->id}");
        $this->postJson("/repetitions/{$other->id}");

        $this->assertSame([$otherCard->id], $this->ids($this->queue(['card_set_id' => $other->id])->assertJsonPath('new_count', 1)));
        $this->assertCount(4, $this->ids($this->queue()));
        $this->queue(['limit' => 0])->assertUnprocessable();
    }

    public function test_author_edits_keep_progress_deleted_cards_leave_and_new_cards_join(): void
    {
        [$c1, $c2] = $this->cards;
        Sanctum::actingAs($this->student);
        $this->postJson("/repetitions/{$this->set->id}");
        $this->review($c1, 1);
        $this->review($c2, 1);

        Sanctum::actingAs($this->admin);
        $this->putJson("/card/{$c1->id}", ['front_text' => 'Новый вопрос', 'back_text' => 'Новый ответ'])->assertOk();
        $this->deleteJson("/card/{$c2->id}")->assertOk();
        $added = Card::factory()->for($this->set)->create();

        Sanctum::actingAs($this->student);
        $queue = $this->queue()->assertJsonPath('due_count', 1)->assertJsonPath('new_count', 2);
        $this->assertSame([$this->cards[2]->id, $added->id, $c1->id], $this->ids($queue));
        $this->assertSame('Новый вопрос', $queue->json('cards.2.card.front_text'));
        $this->assertSame('learning', $queue->json('cards.2.state'));
        $this->assertSame(1, $queue->json('cards.2.reps'));
        $this->getJson('/repetitions')->assertJsonPath('0.cards_count', 3)->assertJsonPath('0.due_count', 1)->assertJsonPath('0.new_count', 2);

        $this->review($c2, 3)->assertNotFound();

        // удалённый набор уходит целиком, прогресс остаётся в БД
        $this->set->delete();
        $this->getJson('/repetitions')->assertExactJson([]);
        $this->queue()->assertJsonPath('cards', []);
        $this->review($c1, 3)->assertNotFound();
        $this->assertSame(2, CardReviewState::query()->where('state', '!=', ReviewState::new->value)->count());
    }

    private function assertComesOnDueDay(Card $card, CarbonImmutable $due): void
    {
        $day = LocalDay::current('Europe/Moscow', $due);

        $this->travelTo($day->start->subSecond());
        $this->assertNotContains($card->id, $this->ids($this->queue()), 'карточка пришла раньше своего дня');

        $this->travelTo($day->start);
        $queue = $this->queue();
        $this->assertContains($card->id, $this->ids($queue), 'карточка не пришла в свой день');
        $this->assertSame('review', collect($queue->json('cards'))->firstWhere('card.id', $card->id)['state']);
    }

    private function queue(array $query = []): TestResponse
    {
        return $this->getJson('/repetitions/queue'.($query ? '?'.http_build_query($query) : ''));
    }

    /** @return list<int> */
    private function ids(TestResponse $queue): array
    {
        return array_column(array_column($queue->json('cards'), 'card'), 'id');
    }

    private function review(Card $card, int $rating, ?string $reviewId = null, array $extra = []): TestResponse
    {
        return $this->postJson("/card/{$card->id}/review", ['review_id' => $reviewId ?? (string) Str::uuid(), 'rating' => $rating, ...$extra]);
    }
}
