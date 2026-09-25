<?php

namespace Tests\Feature\Api;

use App\Models\Card\Card;
use App\Models\Card\CardSet;
use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Каталог (контент админов) публичный; личный контент видит только владелец и админ.
 */
class ContentAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->owner = User::factory()->create();
        $this->stranger = User::factory()->create();
    }

    public function test_catalog_lists_only_admin_content(): void
    {
        $public = CardSet::factory()->for($this->admin)->create();
        CardSet::factory()->for($this->owner)->create();

        $this->getJson('/card_set')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id);
    }

    public function test_mine_scope_requires_auth_and_returns_own_sets(): void
    {
        $own = CardSet::factory()->for($this->owner)->create();
        CardSet::factory()->for($this->stranger)->create();

        $this->getJson('/card_set?filter[scope]=mine')->assertUnauthorized();

        Sanctum::actingAs($this->owner);
        $this->getJson('/card_set?filter[scope]=mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_private_card_set_is_hidden_from_strangers(): void
    {
        $set = CardSet::factory()->for($this->owner)->create();
        $card = Card::factory()->for($set)->create();

        $this->getJson("/card_set/{$set->id}")->assertForbidden();
        $this->getJson("/card_set/{$set->id}/cards")->assertForbidden();
        $this->getJson("/card/{$card->id}")->assertForbidden();

        Sanctum::actingAs($this->stranger);
        $this->getJson("/card/{$card->id}")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->getJson("/card_set/{$set->id}")->assertOk();
        $this->getJson("/card/{$card->id}")->assertOk();
    }

    public function test_card_index_does_not_leak_private_cards(): void
    {
        $public = Card::factory()->for(CardSet::factory()->for($this->admin))->create();
        $private = Card::factory()->for(CardSet::factory()->for($this->owner))->create();

        $ids = collect($this->getJson('/card')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function test_only_owner_can_edit_card_set(): void
    {
        $set = CardSet::factory()->for($this->owner)->create();

        Sanctum::actingAs($this->stranger);
        $payload = ['name' => 'Чужое', 'section_id' => (string) $set->section_id] + $set->only(['subject', 'class', 'difficulty']);
        $this->putJson("/card_set/{$set->id}", $payload)->assertForbidden();
        $this->deleteJson("/card_set/{$set->id}")->assertForbidden();

        $this->assertNotSoftDeleted($set);
    }

    public function test_private_task_is_hidden_from_strangers(): void
    {
        $task = Task::factory()->for(Test::factory()->for($this->owner))->create();

        $this->getJson("/task/{$task->id}")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->getJson("/task/{$task->id}")->assertOk();
    }

    public function test_check_answers_scores_public_test(): void
    {
        $test = Test::factory()->for($this->admin)->create();
        $right = Task::factory()->for($test)->create(['answer' => '42']);
        $wrong = Task::factory()->for($test)->create(['answer' => '7']);

        $this->postJson("/test/{$test->id}/answers", [
            'time' => 30,
            'answers' => [
                ['task_id' => $right->id, 'answer' => '42'],
                ['task_id' => $wrong->id, 'answer' => '8'],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('total_score', 1)
            ->assertJsonPath('results.0.is_correct', true)
            ->assertJsonPath('results.1.is_correct', false);
    }

    public function test_check_answers_rejects_private_test_for_strangers(): void
    {
        $test = Test::factory()->for($this->owner)->create();
        $task = Task::factory()->for($test)->create(['answer' => 'секрет']);
        $payload = ['time' => 5, 'answers' => [['task_id' => $task->id, 'answer' => '?']]];

        $this->postJson("/test/{$test->id}/answers", $payload)->assertForbidden();

        Sanctum::actingAs($this->stranger);
        $this->postJson("/test/{$test->id}/answers", $payload)->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson("/test/{$test->id}/answers", $payload)->assertSuccessful();
    }

    public function test_check_answers_does_not_reveal_tasks_of_other_tests(): void
    {
        $public = Test::factory()->for($this->admin)->create();
        $foreign = Task::factory()->for(Test::factory()->for($this->owner))->create(['answer' => 'секрет']);

        $this->postJson("/test/{$public->id}/answers", [
            'time' => 5,
            'answers' => [['task_id' => $foreign->id, 'answer' => '?']],
        ])
            ->assertSuccessful()
            ->assertJsonPath('results.0.task', null)
            ->assertJsonPath('results.0.correct_answer', null)
            ->assertJsonPath('results.0.is_correct', false);
    }
}
