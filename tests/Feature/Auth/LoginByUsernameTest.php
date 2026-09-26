<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fuisic\Auth\Jobs\SendVerificationEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SoftwarePasskey;
use Tests\TestCase;

/**
 * POST /login с полем `login` (email или логин) и старым полем `email`.
 */
class LoginByUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_field_accepts_email(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_legacy_email_field_still_works(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/login', ['email' => 'not-an-email', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_or_email_is_required(): void
    {
        $this->postJson('/login', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'email']);
    }

    public function test_regular_registration_still_requires_email_verification(): void
    {
        Queue::fake();

        $this->postJson('/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'Secret-123',
            'password_confirmation' => 'Secret-123',
        ])->assertCreated();

        Queue::assertPushed(SendVerificationEmailJob::class);

        foreach (['login', 'email'] as $field) {
            $this->postJson('/login', [$field => 'ivan@example.com', 'password' => 'Secret-123'])
                ->assertForbidden()
                ->assertJsonValidationErrors('email');
        }
    }

    public function test_username_login_is_disabled_without_username_column(): void
    {
        config(['fuisic-auth.login.username_column' => null]);
        $child = User::factory()->child()->create();

        $this->postJson('/login', ['login' => $child->username, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    public function test_user_with_username_can_still_log_in_by_verified_email(): void
    {
        $child = User::factory()->child()->create([
            'email' => 'masha@example.com',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/login', ['login' => 'masha@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $child->id);
    }

    public function test_child_without_email_cannot_request_verification_email(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->child()->create());

        $this->postJson('/email/verify/resend')->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_password_forgot_does_not_find_child_without_email(): void
    {
        User::factory()->child()->create(['username' => 'masha']);

        $this->postJson('/password/forgot', ['email' => 'masha'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_child_without_email_registers_passkey_under_username(): void
    {
        config(['fuisic-auth.frontend_url' => 'http://localhost:8081']);
        $child = User::factory()->child()->create(['username' => 'masha']);
        $device = new SoftwarePasskey;
        Sanctum::actingAs($child);

        $options = $this->postJson('/passkeys/register/options')
            ->assertOk()
            ->assertJsonPath('options.user.name', 'masha')
            ->json('options');

        $this->postJson('/passkeys/register', ['name' => 'iPad', 'credential' => $device->register($options)])
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/passkeys/login/options')->json('options');
        $this->postJson('/passkeys/login', ['credential' => $device->login($login)])
            ->assertOk()
            ->assertJsonPath('user.id', $child->id)
            ->assertJsonPath('user.email', null);
    }
}
