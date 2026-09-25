<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_accepts_token_requests_without_csrf(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret-123')]);

        $this->postJson('/login', ['email' => $user->email, 'password' => 'Secret-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_admin_panel_is_protected_by_csrf(): void
    {
        // CSRF раньше был отключён для всего приложения (except '/*'). В тестах Laravel
        // сам пропускает CSRF-проверку, поэтому проверяем конфигурацию маршрутов.
        $admin = Route::getRoutes()->match(Request::create('/admin/login', 'POST'));
        $api = Route::getRoutes()->match(Request::create('/card_set', 'POST'));

        $this->assertContains('web', $admin->gatherMiddleware());
        $this->assertContains(PreventRequestForgery::class, app(Kernel::class)->getMiddlewareGroups()['web']);
        $this->assertNotContains('web', $api->gatherMiddleware());
    }

    public function test_login_is_rate_limited(): void
    {
        $payload = ['email' => 'nobody@example.com', 'password' => 'wrong'];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/login', $payload)->assertTooManyRequests();
    }

    public function test_passkey_registration_options_require_auth(): void
    {
        $this->postJson('/passkeys/register/options')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/passkeys/register/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rp' => ['id'], 'user' => ['id', 'name']]]);
    }

    public function test_passkey_login_options_are_public(): void
    {
        $this->postJson('/passkeys/login/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge']]);
    }

    public function test_malformed_passkey_credential_is_a_validation_error(): void
    {
        $this->postJson('/passkeys/login', ['credential' => ['id' => 'x', 'rawId' => 'x', 'type' => 'public-key', 'response' => ['foo' => 'bar']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }

    public function test_passkey_login_with_unknown_challenge_is_rejected(): void
    {
        $clientData = rtrim(strtr(base64_encode(json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'origin' => 'http://localhost:8081',
        ])), '+/', '-_'), '=');

        $this->postJson('/passkeys/login', ['credential' => [
            'id' => 'AAAA',
            'rawId' => 'AAAA',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $clientData,
                'authenticatorData' => 'AAAA',
                'signature' => 'AAAA',
                'userHandle' => null,
            ],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }

    public function test_user_lists_own_passkeys(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/passkeys')->assertOk()->assertExactJson(['passkeys' => []]);
    }
}
