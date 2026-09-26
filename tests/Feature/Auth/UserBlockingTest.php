<?php

namespace Tests\Feature\Auth;

use App\Models\Card\Card;
use App\Models\Card\CardSet;
use App\Models\Test\Test;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\UserBlocking;
use Fuisic\Auth\Services\AuthTokenService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\SoftwarePasskey;
use Tests\TestCase;

/**
 * Блокировка: вход всеми способами и любые запросы → 403 user_blocked, отзыв токенов,
 * срок, правило «персонал блокирует только admin», скрытие материалов из каталога.
 */
class UserBlockingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        config(['fuisic-auth.frontend_url' => 'http://localhost:8081']);
    }

    public function test_blocked_user_cannot_log_in_with_password(): void
    {
        $user = User::factory()->create();
        $this->block($user, 'Спам в комментариях');

        $this->login($user)
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Аккаунт заблокирован.',
                'code' => 'user_blocked',
                'block' => ['reason' => 'Спам в комментариях', 'until' => null],
            ]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_block_term_is_returned_in_iso_8601(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $this->block($user, 'Флуд', now()->addDays(3));

        $this->login($user)
            ->assertForbidden()
            ->assertJsonPath('block.until', now()->addDays(3)->toJSON());
    }

    public function test_wrong_password_of_blocked_user_does_not_reveal_block(): void
    {
        $user = User::factory()->create();
        $this->block($user);

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonMissingPath('block');
    }

    public function test_blocked_user_cannot_log_in_with_passkey(): void
    {
        $user = User::factory()->create();
        $device = new SoftwarePasskey;
        Sanctum::actingAs($user);
        $options = $this->postJson('/passkeys/register/options')->json('options');
        $this->postJson('/passkeys/register', ['name' => 'Key', 'credential' => $device->register($options)])->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->block($user, 'Нарушение правил');

        $credential = $device->login($this->postJson('/passkeys/login/options')->json('options'));

        $this->postJson('/passkeys/login', ['credential' => $credential])
            ->assertForbidden()
            ->assertJsonPath('code', 'user_blocked')
            ->assertJsonPath('block.reason', 'Нарушение правил')
            ->assertJsonMissingPath('token');
    }

    public function test_blocked_user_cannot_log_in_with_oauth(): void
    {
        $user = User::factory()->create(['email' => 'oauth@example.com']);
        $this->block($user, 'Мошенничество');

        $this->mockYandexUser('oauth@example.com');

        $this->getJson('/oauth/yandex/callback?state='.urlencode($this->oauthState()))
            ->assertForbidden()
            ->assertJsonPath('code', 'user_blocked')
            ->assertJsonPath('block.reason', 'Мошенничество')
            ->assertJsonMissingPath('token');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_oauth_browser_callback_redirects_blocked_user_with_reason(): void
    {
        $user = User::factory()->create(['email' => 'oauth@example.com']);
        $this->block($user, 'Мошенничество');

        $this->mockYandexUser('oauth@example.com');

        $location = $this->get('/oauth/yandex/callback?state='.urlencode($this->oauthState()))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:8081/auth/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('user_blocked', $query['oauth_error_code']);
        $this->assertSame('Мошенничество', $query['block_reason']);
        $this->assertArrayNotHasKey('token', $query);
    }

    public function test_blocking_revokes_issued_tokens_immediately(): void
    {
        $user = User::factory()->create();
        $token = $this->login($user)->assertOk()->json('token');
        // Auth::attempt пишет в сессию, а в тестах она общая между запросами (в API сессий нет)
        $this->flushSession();

        $this->withToken($token)->getJson('/me')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->block($user);

        $this->withToken($token)->getJson('/me')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_blocking_deletes_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();

        foreach ([$user, $other] as $i => $owner) {
            DB::table('sessions')->insert([
                'id' => "session-{$i}", 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->block($user);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }

    #[DataProvider('authorizedRequests')]
    public function test_any_request_of_blocked_user_is_forbidden(string $method, string $uri): void
    {
        $user = User::factory()->create();
        $this->block($user, 'Причина');
        Sanctum::actingAs($user);

        $this->json($method, $uri)
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Аккаунт заблокирован.',
                'code' => 'user_blocked',
                'block' => ['reason' => 'Причина', 'until' => null],
            ]);
    }

    public static function authorizedRequests(): array
    {
        return [
            'me (fuisic-auth)' => ['GET', '/me'],
            'passkeys (fuisic-auth)' => ['GET', '/passkeys'],
            'protected domain route' => ['POST', '/card_set'],
            'public domain route with token' => ['GET', '/card_set'],
        ];
    }

    public function test_expired_block_stops_working_before_the_scheduler_runs(): void
    {
        $user = User::factory()->create();
        $this->block($user, 'На день', now()->addDay());

        $this->login($user)->assertForbidden();

        $this->travel(25)->hours();

        $token = $this->login($user)->assertOk()->json('token');
        $this->withToken($token)->getJson('/me')->assertOk();
        $this->assertFalse($user->isBlocked());
    }

    public function test_scheduled_command_closes_expired_blocks(): void
    {
        $expiring = User::factory()->create();
        $permanent = User::factory()->create();
        $expired = $this->block($expiring, 'На час', now()->addHour());
        $active = $this->block($permanent, 'Бессрочно');

        $this->travel(2)->hours();
        $this->artisan('users:unblock-expired')->assertSuccessful();

        $this->assertEquals($expired->until, $expired->fresh()->unblocked_at);
        $this->assertNull($expired->fresh()->unblocked_by_id);
        $this->assertNull($active->fresh()->unblocked_at);
        $this->assertTrue($permanent->isBlocked());

        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, 'users:unblock-expired'));
        $this->assertTrue($scheduled, 'users:unblock-expired не в расписании');
    }

    public function test_manual_unblock_restores_access_and_keeps_history(): void
    {
        $user = User::factory()->create();
        $this->block($user, 'Первая');

        app(UserBlocking::class)->unblock($user, $this->admin);

        $this->login($user)->assertOk();

        $this->block($user, 'Вторая');
        $this->login($user)->assertForbidden()->assertJsonPath('block.reason', 'Вторая');

        $this->assertSame(2, $user->blocks()->count());
        $this->assertSame($this->admin->id, $user->blocks()->oldest('id')->first()->unblocked_by_id);
    }

    #[DataProvider('blockRules')]
    public function test_who_can_block_whom(string $actor, string $target, bool $allowed): void
    {
        $actor = $this->userWithRole($actor);
        $target = $this->userWithRole($target);

        $this->assertSame($allowed, app(UserBlocking::class)->canBlock($actor, $target));
    }

    public static function blockRules(): array
    {
        return [
            'moderator → student' => ['moderator', 'student', true],
            'moderator → teacher' => ['moderator', 'teacher', true],
            'moderator → admin' => ['moderator', 'admin', false],
            'moderator → moderator' => ['moderator', 'moderator', false],
            'admin → moderator' => ['admin', 'moderator', true],
            'admin → admin' => ['admin', 'admin', true],
            'teacher → student' => ['teacher', 'student', false],
        ];
    }

    public function test_moderator_block_of_staff_is_rejected_by_the_service(): void
    {
        $moderator = User::factory()->moderator()->create();

        try {
            app(UserBlocking::class)->block($this->admin, $moderator, 'Попытка');
            $this->fail('Модератор заблокировал администратора');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertFalse($this->admin->isBlocked());
    }

    public function test_nobody_can_block_themselves(): void
    {
        $this->assertFalse(app(UserBlocking::class)->canBlock($this->admin, $this->admin));
    }

    public function test_user_with_admin_panel_access_counts_as_staff(): void
    {
        $custom = User::factory()->teacher()->create();
        $custom->givePermissionTo('admin.access');

        $this->assertFalse(app(UserBlocking::class)->canBlock(User::factory()->moderator()->create(), $custom));
    }

    public function test_blocked_author_content_is_hidden_from_catalog(): void
    {
        $author = User::factory()->admin()->create();
        $set = CardSet::factory()->for($author)->create();
        $card = Card::factory()->for($set)->create();
        $test = Test::factory()->for($author)->create();

        $this->getJson('/card_set')->assertJsonCount(1, 'data');
        $this->getJson('/test')->assertJsonCount(1, 'data');
        $this->getJson('/card')->assertJsonCount(1, 'data');

        $this->block($author);

        $this->getJson('/card_set')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/test')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/card')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/card_set/{$set->id}")->assertForbidden();
        $this->getJson("/card/{$card->id}")->assertForbidden();
        $this->getJson("/test/{$test->id}")->assertForbidden();

        app(UserBlocking::class)->unblock($author, $this->admin);

        $this->getJson('/card_set')->assertJsonCount(1, 'data');
        $this->getJson("/test/{$test->id}")->assertOk();
    }

    private function block(User $user, string $reason = 'Нарушение правил', mixed $until = null): UserBlock
    {
        return app(UserBlocking::class)->block($user, $this->admin, $reason, 'внутренний комментарий', $until);
    }

    private function login(User $user): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);
    }

    private function userWithRole(string $role): User
    {
        return match ($role) {
            'admin' => User::factory()->admin()->create(),
            'moderator' => User::factory()->moderator()->create(),
            'teacher' => User::factory()->teacher()->create(),
            default => User::factory()->create(),
        };
    }

    private function oauthState(): string
    {
        return app(AuthTokenService::class)->createOAuthState([
            'provider' => 'yandex', 'intent' => 'login', 'user_id' => null, 'nonce' => 'n',
        ]);
    }

    private function mockYandexUser(string $email): void
    {
        config(['fuisic-auth.oauth.providers.yandex.enabled' => true]);
        $socialiteUser = (new SocialiteUser)->map([
            'id' => '42', 'name' => 'Яндекс Пользователь', 'email' => $email, 'avatar' => null,
        ]);
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('yandex')->andReturn($driver);
    }
}
