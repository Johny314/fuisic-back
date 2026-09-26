<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fuisic\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Сброс пароля по email: POST /password/forgot → токен из письма → POST /password/reset (fuisic-auth#14).
 */
class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_reset_flow_changes_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('Old-pass-123')]);

        $this->postJson('/password/forgot', ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-pass-456',
            'password_confirmation' => 'New-pass-456',
        ])->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertNotNull($user->fresh()->getRememberToken());

        $this->postJson('/login', ['email' => $user->email, 'password' => 'Old-pass-123'])->assertUnauthorized();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'New-pass-456'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_reset_with_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old-pass-123')]);
        Password::createToken($user);

        $this->postJson('/password/reset', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'New-pass-456',
            'password_confirmation' => 'New-pass-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('Old-pass-123', $user->fresh()->password));
    }

    public function test_token_works_only_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-pass-456',
            'password_confirmation' => 'New-pass-456',
        ];

        $this->postJson('/password/reset', $payload)->assertOk();
        $this->postJson('/password/reset', [...$payload, 'password' => 'Other-pass-789', 'password_confirmation' => 'Other-pass-789'])
            ->assertUnprocessable();

        $this->assertTrue(Hash::check('New-pass-456', $user->fresh()->password));
    }

    public function test_reset_works_without_remember_token_column(): void
    {
        // Пакет переиспользуемый: у приложения может не быть колонки (откатится вместе с транзакцией теста)
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('remember_token'));
        $user = User::factory()->create();

        $this->postJson('/password/reset', [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => 'New-pass-456',
            'password_confirmation' => 'New-pass-456',
        ])->assertOk();

        $this->assertTrue(Hash::check('New-pass-456', $user->fresh()->password));
    }

    public function test_child_without_email_cannot_request_a_reset(): void
    {
        Notification::fake();
        $child = User::factory()->child()->create();

        $this->postJson('/password/forgot', ['email' => $child->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }
}
