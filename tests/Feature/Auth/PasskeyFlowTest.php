<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SoftwarePasskey;
use Tests\TestCase;

/**
 * Полная церемония passkeys (регистрация → вход → токен) с настоящей криптографией.
 */
class PasskeyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['fuisic-auth.frontend_url' => 'http://localhost:8081']);
    }

    public function test_register_passkey_then_login_with_it(): void
    {
        $user = User::factory()->create();
        $device = new SoftwarePasskey;

        Sanctum::actingAs($user);
        $options = $this->postJson('/passkeys/register/options')->assertOk()->json('options');

        $this->postJson('/passkeys/register', ['name' => 'MacBook', 'credential' => $device->register($options)])
            ->assertCreated();

        $this->getJson('/passkeys')->assertOk()->assertJsonPath('passkeys.0.name', 'MacBook');

        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/passkeys/login/options')->assertOk()->json('options');
        $response = $this->postJson('/passkeys/login', ['credential' => $device->login($login)])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($response->json('token'))->getJson('/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_login_options_cannot_be_replayed(): void
    {
        $device = new SoftwarePasskey;
        Sanctum::actingAs(User::factory()->create());
        $options = $this->postJson('/passkeys/register/options')->json('options');
        $this->postJson('/passkeys/register', ['name' => 'Key', 'credential' => $device->register($options)])->assertCreated();
        $this->app['auth']->forgetGuards();

        $credential = $device->login($this->postJson('/passkeys/login/options')->json('options'));

        $this->postJson('/passkeys/login', ['credential' => $credential])->assertOk();
        $this->postJson('/passkeys/login', ['credential' => $credential])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $device = new SoftwarePasskey;
        Sanctum::actingAs(User::factory()->create());
        $options = $this->postJson('/passkeys/register/options')->json('options');
        $this->postJson('/passkeys/register', ['name' => 'Key', 'credential' => $device->register($options)])->assertCreated();
        $this->app['auth']->forgetGuards();

        $credential = $device->login($this->postJson('/passkeys/login/options')->json('options'));
        $credential['response']['signature'] = (new SoftwarePasskey)->login(['challenge' => 'AAAA'])['response']['signature'];

        $this->postJson('/passkeys/login', ['credential' => $credential])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential')
            ->assertJsonMissingPath('token');
    }

    public function test_foreign_origin_is_rejected(): void
    {
        $device = new SoftwarePasskey(origin: 'https://evil.example');
        Sanctum::actingAs(User::factory()->create());
        $options = $this->postJson('/passkeys/register/options')->json('options');

        $this->postJson('/passkeys/register', ['name' => 'Key', 'credential' => $device->register($options)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }

    public function test_user_can_delete_only_own_passkey(): void
    {
        $owner = User::factory()->create();
        $device = new SoftwarePasskey;
        Sanctum::actingAs($owner);
        $options = $this->postJson('/passkeys/register/options')->json('options');
        $this->postJson('/passkeys/register', ['name' => 'Key', 'credential' => $device->register($options)])->assertCreated();
        $id = $this->getJson('/passkeys')->json('passkeys.0.id');

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/passkeys/{$id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/passkeys/{$id}")->assertOk();
        $this->getJson('/passkeys')->assertExactJson(['passkeys' => []]);
    }
}
