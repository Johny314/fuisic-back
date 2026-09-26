<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fuisic\Auth\Jobs\SendVerificationEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Смена email сбрасывает подтверждение и отправляет письмо (fuisic-back#36).
 */
class EmailChangeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_changing_email_resets_verification_and_queues_letter(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $this->putJson("/user/{$user->id}", ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'new@example.com')
            ->assertJsonPath('email_verified_at', null);

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertLetterQueuedFor($user);
    }

    public function test_saving_profile_without_email_change_keeps_verification(): void
    {
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;
        Sanctum::actingAs($user);

        $this->putJson("/user/{$user->id}", ['name' => 'Новое имя', 'email' => $user->email])
            ->assertOk()
            ->assertJsonPath('name', 'Новое имя');

        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
        Queue::assertNotPushed(SendVerificationEmailJob::class);
    }

    public function test_new_email_cannot_log_in_until_verified(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        Sanctum::actingAs($user);
        $this->putJson("/user/{$user->id}", ['name' => $user->name, 'email' => 'new@example.com'])->assertOk();
        $this->asGuest();

        $this->postJson('/login', ['login' => 'new@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonValidationErrors('email');
        $this->postJson('/login', ['login' => 'old@example.com', 'password' => 'password'])
            ->assertUnauthorized();

        // ссылка на старый адрес новый не подтверждает
        $this->getJson($this->verificationUrl($user, 'old@example.com'))->assertForbidden();

        $this->getJson($this->verificationUrl($user, 'new@example.com'))->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/login', ['login' => 'new@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_child_adding_first_email_gets_letter_and_still_logs_in_by_username(): void
    {
        $child = User::factory()->child()->create(['username' => 'masha']);
        Sanctum::actingAs($child);

        $this->putJson("/user/{$child->id}", ['name' => $child->name, 'email' => 'masha@example.com'])
            ->assertOk()
            ->assertJsonPath('email_verified_at', null);

        $this->assertLetterQueuedFor($child);
        $this->asGuest();

        $this->postJson('/login', ['login' => 'masha', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $child->id);
        $this->postJson('/login', ['login' => 'masha@example.com', 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_child_removing_email_resets_verification_without_letter(): void
    {
        $child = User::factory()->child()->create(['email' => 'masha@example.com', 'email_verified_at' => now()]);
        Sanctum::actingAs($child);

        $this->putJson("/user/{$child->id}", ['name' => $child->name, 'email' => null])
            ->assertOk()
            ->assertJsonPath('email', null);

        $this->assertNull($child->fresh()->email_verified_at);
        Queue::assertNotPushed(SendVerificationEmailJob::class);
    }

    public function test_admin_panel_email_change_resets_verification_too(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'backpack')
            ->put("/admin/user/{$user->id}", ['id' => $user->id, 'name' => $user->name, 'email' => 'fixed@example.com', 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('fixed@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertLetterQueuedFor($user);
    }

    public function test_admin_panel_save_without_email_change_sends_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create(), 'backpack')
            ->put("/admin/user/{$user->id}", ['id' => $user->id, 'name' => 'Переименован', 'email' => $user->email, 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Queue::assertNotPushed(SendVerificationEmailJob::class);
    }

    /** Сбросить Sanctum::actingAs перед POST /login (он переключает guard по умолчанию). */
    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function assertLetterQueuedFor(User $user): void
    {
        Queue::assertPushed(SendVerificationEmailJob::class, 1);
        Queue::assertPushed(SendVerificationEmailJob::class, fn (SendVerificationEmailJob $job) => $job->user->is($user));
    }

    private function verificationUrl(User $user, string $email): string
    {
        return URL::temporarySignedRoute('fuisic-auth.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($email),
        ]);
    }
}
