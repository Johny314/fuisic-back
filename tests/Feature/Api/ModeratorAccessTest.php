<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Models\Card\Card;
use App\Models\Card\CardSet;
use App\Models\Section;
use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Политики на правах: модератор (catalog.manage) ведёт каталог и разделы, но не пользователей;
 * учитель и ученик — только свои материалы.
 */
class ModeratorAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->moderator()->create();
        $this->student = User::factory()->create();
    }

    public function test_moderator_can_manage_sections(): void
    {
        $section = Section::factory()->create();
        Sanctum::actingAs($this->moderator);

        $this->postJson('/section', ['name' => 'Оптика'])->assertSuccessful();
        $this->putJson("/section/{$section->id}", ['name' => 'Механика'])->assertOk();
        $this->deleteJson("/section/{$section->id}")->assertOk();

        $this->assertDatabaseHas('sections', ['name' => 'Оптика']);
        $this->assertSoftDeleted($section);
    }

    public function test_teacher_cannot_manage_sections(): void
    {
        $section = Section::factory()->create();
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->postJson('/section', ['name' => 'Оптика'])->assertForbidden();
        $this->putJson("/section/{$section->id}", ['name' => 'Механика'])->assertForbidden();
        $this->deleteJson("/section/{$section->id}")->assertForbidden();
    }

    public function test_moderator_can_manage_catalog_card_sets_and_cards(): void
    {
        $set = CardSet::factory()->for($this->admin)->create();
        $card = Card::factory()->for($set)->create();
        Sanctum::actingAs($this->moderator);

        $this->putJson("/card_set/{$set->id}", $this->updateCardSetPayload($set, 'Каталог'))->assertOk();
        $this->postJson('/card', ['card_set_id' => (string) $set->id, 'front_text' => 'F', 'back_text' => 'B'])->assertSuccessful();
        $this->putJson("/card/{$card->id}", ['card_set_id' => (string) $set->id, 'front_text' => 'F2', 'back_text' => 'B2'])->assertOk();
        $this->deleteJson("/card/{$card->id}")->assertOk();
        $this->deleteJson("/card_set/{$set->id}")->assertOk();

        // автор каталога не меняется
        $this->assertSame($this->admin->id, (int) $set->fresh()->user_id);
        $this->assertSoftDeleted($set);
    }

    public function test_moderator_can_manage_catalog_tests_and_tasks(): void
    {
        $test = Test::factory()->for($this->admin)->create();
        $task = Task::factory()->for($test)->create();
        Sanctum::actingAs($this->moderator);

        $this->putJson("/test/{$test->id}", $this->updateTestPayload($test, 'Каталог'))->assertOk();
        $this->postJson('/task', ['test_id' => (string) $test->id, 'problem_statement' => '2 + 2?', 'answer' => '1'])->assertSuccessful();
        $this->putJson("/task/{$task->id}", ['test_id' => (string) $test->id, 'answer' => '2'])->assertOk();
        $this->deleteJson("/task/{$task->id}")->assertOk();
        $this->deleteJson("/test/{$test->id}")->assertOk();

        $this->assertSoftDeleted($test);
    }

    public function test_moderator_cannot_see_or_edit_personal_content(): void
    {
        $set = CardSet::factory()->for($this->student)->create();
        $test = Test::factory()->for($this->student)->create();
        Sanctum::actingAs($this->moderator);

        $this->getJson("/card_set/{$set->id}")->assertForbidden()->assertJsonPath('message', 'Набор недоступен');
        $this->getJson("/test/{$test->id}")->assertForbidden()->assertJsonPath('message', 'Тест недоступен');
        $this->putJson("/card_set/{$set->id}", $this->updateCardSetPayload($set, 'Чужое'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Недостаточно прав');
        $this->deleteJson("/test/{$test->id}")->assertForbidden();

        $this->assertNotSoftDeleted($set);
        $this->assertNotSoftDeleted($test);
    }

    public function test_teacher_cannot_edit_catalog_but_edits_own(): void
    {
        $teacher = User::factory()->teacher()->create();
        $catalog = CardSet::factory()->for($this->admin)->create();
        $own = CardSet::factory()->for($teacher)->create();
        Sanctum::actingAs($teacher);

        $this->getJson("/card_set/{$catalog->id}")->assertOk();
        $this->putJson("/card_set/{$catalog->id}", $this->updateCardSetPayload($catalog, 'Чужое'))->assertForbidden();
        $this->deleteJson("/card_set/{$catalog->id}")->assertForbidden();
        $this->putJson("/card_set/{$own->id}", $this->updateCardSetPayload($own, 'Моё'))->assertOk();

        $this->assertNotSoftDeleted($catalog);
    }

    public function test_studio_scope_includes_catalog_only_for_catalog_managers(): void
    {
        $catalog = CardSet::factory()->for($this->admin)->create();
        $mine = CardSet::factory()->for($this->moderator)->create();
        CardSet::factory()->for($this->student)->create();

        Sanctum::actingAs($this->moderator);
        $ids = collect($this->getJson('/card_set?filter[scope]=studio')->assertOk()->json('data'))->pluck('id')->sort()->values();
        $this->assertEquals(collect([$catalog->id, $mine->id])->sort()->values(), $ids);

        $teacher = User::factory()->teacher()->create();
        $own = CardSet::factory()->for($teacher)->create();
        Sanctum::actingAs($teacher);
        $this->getJson('/card_set?filter[scope]=studio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_moderator_can_view_but_not_manage_users(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($this->moderator);

        $this->getJson('/user')->assertOk();
        $this->getJson("/user/{$other->id}")->assertOk()->assertJsonPath('id', $other->id);

        $this->postJson('/user', ['name' => 'Новый', 'email' => 'new@example.com', 'password' => 'password'])->assertForbidden();
        $this->putJson("/user/{$other->id}", ['name' => 'Чужое имя', 'email' => $other->email])->assertForbidden();
        $this->deleteJson("/user/{$other->id}")->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertNotSoftDeleted($other);
        $this->assertNotSame('Чужое имя', $other->fresh()->name);
    }

    public function test_moderator_updates_own_profile(): void
    {
        Sanctum::actingAs($this->moderator);

        $this->putJson("/user/{$this->moderator->id}", ['name' => 'Модератор', 'email' => $this->moderator->email])->assertOk();
    }

    public function test_users_manage_does_not_reach_admins(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(PermissionName::usersManage->value);
        $student = User::factory()->create();
        Sanctum::actingAs($manager);

        $this->putJson("/user/{$student->id}", ['name' => 'Переименован', 'email' => $student->email])->assertOk();
        $this->postJson('/user', ['name' => 'Ученик', 'email' => 'pupil@example.com', 'password' => 'password'])->assertSuccessful();

        $this->putJson("/user/{$this->admin->id}", ['name' => 'Взлом', 'email' => $this->admin->email])->assertForbidden();
        $this->deleteJson("/user/{$this->admin->id}")->assertForbidden();
        // роль из запроса API не берётся: создаётся ученик
        $this->postJson('/user', [
            'name' => 'Второй админ',
            'email' => 'root@example.com',
            'password' => 'password',
            'roles' => ['admin'],
        ])->assertSuccessful();

        $this->assertSame(['student'], User::query()->where('email', 'root@example.com')->firstOrFail()->getRoleNames()->all());
        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_bearer_token_is_honoured_on_public_routes(): void
    {
        // публичные маршруты без auth:sanctum: владелец по настоящему токену видит свой набор
        $set = CardSet::factory()->for($this->student)->create();
        $token = $this->student->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson("/card_set/{$set->id}")->assertOk();
        $this->withToken($token)->getJson('/user')->assertForbidden();
    }

    private function updateCardSetPayload(CardSet $set, string $name): array
    {
        return ['name' => $name, 'section_id' => (string) $set->section_id] + $set->only(['subject', 'class', 'difficulty']);
    }

    private function updateTestPayload(Test $test, string $name): array
    {
        return ['name' => $name, 'section_id' => (string) $test->section_id] + $test->only(['subject', 'class', 'difficulty']);
    }
}
