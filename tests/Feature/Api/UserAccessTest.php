<?php

namespace Tests\Feature\Api;

use App\Models\Card\CardSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $user = User::factory()->create();

        $this->getJson('/user')->assertUnauthorized();
        $this->getJson("/user/{$user->id}")->assertUnauthorized();
    }

    public function test_student_cannot_list_or_view_other_users(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/user')->assertForbidden();
        $this->getJson("/user/{$other->id}")->assertForbidden();
    }

    public function test_admin_can_list_users_without_password(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/user')
            ->assertOk()
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_student_cannot_create_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/user', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'password',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_student_cannot_delete_users(): void
    {
        $victim = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/user/{$victim->id}")->assertForbidden();

        $this->assertNotSoftDeleted($victim);
    }

    public function test_admin_deletes_the_user_not_a_card_set_with_the_same_id(): void
    {
        $admin = User::factory()->admin()->create();
        $victim = User::factory()->create();
        $set = CardSet::factory()->for($admin)->create(['id' => $victim->id]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/user/{$victim->id}")->assertOk();

        $this->assertSoftDeleted($victim);
        $this->assertNotSoftDeleted($set);
    }

    public function test_user_can_update_own_profile_but_not_others(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson("/user/{$user->id}", ['name' => 'Новое имя', 'email' => $user->email])->assertOk();
        $this->putJson("/user/{$other->id}", ['name' => 'Чужое имя', 'email' => $other->email])->assertForbidden();

        $this->assertSame('Новое имя', $user->fresh()->name);
    }

    public function test_registration_cannot_grant_admin_role(): void
    {
        $this->postJson('/register', [
            'name' => 'Мошенник',
            'email' => 'admin-wannabe@example.com',
            'password' => 'Secret-password-123',
            'password_confirmation' => 'Secret-password-123',
            'user_type' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_type');

        $this->assertDatabaseMissing('users', ['email' => 'admin-wannabe@example.com']);
    }

    public function test_registration_allows_choosing_teacher(): void
    {
        $this->postJson('/register', [
            'name' => 'Учитель',
            'email' => 'teacher@example.com',
            'password' => 'Secret-password-123',
            'password_confirmation' => 'Secret-password-123',
            'user_type' => 'teacher',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'teacher@example.com', 'user_type' => 'teacher']);
    }
}
