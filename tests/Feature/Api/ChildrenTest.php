<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildrenTest extends TestCase
{
    use RefreshDatabase;

    private const string PASSWORD = 'Child-secret-123';

    /** Sanctum::actingAs() переключает guard по умолчанию, а /login работает через web. */
    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->flushHeaders();
    }

    private function childPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Маша Петрова',
            'username' => 'Masha.Petrova',
            'grade' => 5,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    public function test_parent_creates_child_who_logs_in_by_username(): void
    {
        $parent = User::factory()->parent()->create();
        Sanctum::actingAs($parent);

        $id = $this->postJson('/children', $this->childPayload())
            ->assertCreated()
            ->assertJsonPath('username', 'masha.petrova')
            ->assertJsonPath('grade', 5)
            ->assertJsonPath('email', null)
            ->assertJsonMissingPath('password')
            ->json('id');

        $child = User::query()->findOrFail($id);
        $this->assertNull($child->email);
        $this->assertTrue($child->hasRole(RoleName::student->value));
        $this->assertSame(['student'], $child->getRoleNames()->all());
        $this->assertSame($parent->id, $child->created_by_id);
        $this->assertTrue($parent->children()->whereKey($id)->exists());

        $this->signOut();

        // логин без учёта регистра, email не нужен
        $token = $this->postJson('/login', ['login' => 'MASHA.petrova', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.id', $id)
            ->json('token');

        $this->withToken($token)->getJson('/me')
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('email', null)
            ->assertJsonPath('username', 'masha.petrova')
            ->assertJsonPath('roles', ['student']);
    }

    public function test_child_cannot_log_in_with_wrong_password(): void
    {
        $child = User::factory()->child()->create();

        $this->postJson('/login', ['login' => $child->username, 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_username_is_validated_and_unique_case_insensitively(): void
    {
        User::factory()->child()->create(['username' => 'petya']);
        Sanctum::actingAs(User::factory()->parent()->create());

        $this->postJson('/children', $this->childPayload(['username' => 'PETYA']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        foreach (['ab', 'masha@mail.ru', 'маша', 'with space', str_repeat('a', 33)] as $invalid) {
            $this->postJson('/children', $this->childPayload(['username' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('username');
        }

        $this->postJson('/children', $this->childPayload(['password_confirmation' => 'other']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/children', $this->childPayload(['grade' => 12]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grade');
    }

    public function test_parent_lists_only_own_children(): void
    {
        $parent = User::factory()->parent()->create();
        $own = User::factory()->child($parent)->create(['name' => 'Свой']);
        User::factory()->child(User::factory()->parent()->create())->create(['name' => 'Чужой']);
        Sanctum::actingAs($parent);

        $this->getJson('/children')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $own->id)
            ->assertJsonPath('0.username', $own->username);

        $this->getJson("/children/{$own->id}")->assertOk()->assertJsonPath('name', 'Свой');
    }

    public function test_other_parents_child_is_not_found(): void
    {
        $child = User::factory()->child(User::factory()->parent()->create())->create();
        Sanctum::actingAs(User::factory()->parent()->create());

        $this->getJson("/children/{$child->id}")->assertNotFound();
        $this->putJson("/children/{$child->id}", ['name' => 'Взлом', 'username' => 'hacked'])->assertNotFound();
        $this->putJson("/children/{$child->id}/password", [
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertNotFound();
        $this->deleteJson("/children/{$child->id}")->assertNotFound();
        $this->getJson('/children/abc')->assertNotFound();

        $this->assertNotSoftDeleted($child);
        $this->assertNotSame('hacked', $child->fresh()->username);
    }

    public function test_non_parent_cannot_manage_children(): void
    {
        $child = User::factory()->child(User::factory()->parent()->create())->create();

        foreach ([User::factory()->create(), User::factory()->teacher()->create(), $child] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/children')->assertForbidden();
            $this->postJson('/children', $this->childPayload(['username' => 'nobody']))->assertForbidden();
            $this->deleteJson("/children/{$child->id}")->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['username' => 'nobody']);
    }

    public function test_guest_cannot_access_children(): void
    {
        $this->getJson('/children')->assertUnauthorized();
        $this->postJson('/children', $this->childPayload())->assertUnauthorized();
    }

    public function test_parent_updates_child_profile_and_username(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create(['username' => 'old_login']);
        $other = User::factory()->child()->create(['username' => 'taken']);
        Sanctum::actingAs($parent);

        $this->putJson("/children/{$child->id}", ['name' => 'Маша', 'username' => 'Taken', 'grade' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        // свой логин можно оставить
        $this->putJson("/children/{$child->id}", ['name' => 'Маша', 'username' => 'OLD_login', 'grade' => 6])
            ->assertOk();

        $this->putJson("/children/{$child->id}", ['name' => 'Маша', 'username' => 'new_login', 'grade' => null])
            ->assertOk()
            ->assertJsonPath('name', 'Маша')
            ->assertJsonPath('username', 'new_login')
            ->assertJsonPath('grade', null);

        $this->assertSame('new_login', $child->fresh()->username);
        $this->assertSame('taken', $other->fresh()->username);
    }

    public function test_password_reset_revokes_child_tokens(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create();
        $oldToken = $child->createToken('api-token')->plainTextToken;

        Sanctum::actingAs($parent);
        $this->putJson("/children/{$child->id}/password", [
            'password' => 'Brand-new-456',
            'password_confirmation' => 'Brand-new-456',
        ])->assertOk();

        $this->assertSame(0, $child->tokens()->count());

        $this->signOut();
        $this->withToken($oldToken)->getJson('/me')->assertUnauthorized();

        $this->signOut();
        $this->postJson('/login', ['login' => $child->username, 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/login', ['login' => $child->username, 'password' => 'Brand-new-456'])->assertOk();
    }

    public function test_parent_deletes_child_account(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create();
        $child->createToken('api-token');
        Sanctum::actingAs($parent);

        $this->deleteJson("/children/{$child->id}")->assertOk();

        $this->assertSoftDeleted($child);
        $this->assertSame(0, $child->tokens()->count());
        $this->getJson('/children')->assertOk()->assertJsonCount(0);

        $this->signOut();
        $this->postJson('/login', ['login' => $child->username, 'password' => 'password'])->assertUnauthorized();
    }

    public function test_only_creating_parent_can_delete_child(): void
    {
        $creator = User::factory()->parent()->create();
        $child = User::factory()->child($creator)->create();
        $secondParent = User::factory()->parent()->create();
        $secondParent->children()->attach($child);
        Sanctum::actingAs($secondParent);

        $this->getJson("/children/{$child->id}")->assertOk();
        $this->deleteJson("/children/{$child->id}")->assertForbidden();

        $this->assertNotSoftDeleted($child);
    }

    public function test_child_cannot_change_username_role_or_parent_via_profile_update(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create(['username' => 'masha', 'grade' => 5]);
        Sanctum::actingAs($child);

        $this->putJson("/user/{$child->id}", [
            'name' => 'Новое имя',
            'username' => 'hacker',
            'user_type' => 'admin',
            'roles' => ['admin'],
            'created_by_id' => null,
            'grade' => 11,
        ])->assertOk();

        $child->refresh();
        $this->assertSame('Новое имя', $child->name);
        $this->assertSame('masha', $child->username);
        $this->assertSame(['student'], $child->getRoleNames()->all());
        $this->assertSame($parent->id, $child->created_by_id);
        $this->assertSame(5, $child->grade);
        $this->assertTrue($parent->children()->whereKey($child->id)->exists());

        // отвязаться или удалить аккаунт сам не может
        $this->deleteJson("/children/{$child->id}")->assertForbidden();
        $this->deleteJson("/user/{$child->id}")->assertForbidden();
        $this->assertNotSoftDeleted($child);
    }

    public function test_child_adds_email_later_and_still_logs_in_by_username(): void
    {
        $child = User::factory()->child()->create(['username' => 'masha']);
        $taken = User::factory()->create();
        Sanctum::actingAs($child);

        $this->putJson("/user/{$child->id}", ['name' => $child->name, 'email' => $taken->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->putJson("/user/{$child->id}", ['name' => $child->name, 'email' => 'masha@example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'masha@example.com')
            ->assertJsonPath('username', 'masha');

        $this->signOut();

        // по логину — без подтверждения email, по неподтверждённому email — нельзя
        $this->postJson('/login', ['login' => 'masha', 'password' => 'password'])->assertOk();
        $this->postJson('/login', ['login' => 'masha@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_without_username_must_keep_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson("/user/{$user->id}", ['name' => 'Имя'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_admin_lists_users_including_children_without_email(): void
    {
        User::factory()->child()->create(['username' => 'masha']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/user')->assertOk()->assertJsonFragment(['username' => 'masha', 'email' => null]);
    }
}
