<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserSetting;
use App\Support\RepetitionLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULTS = [
        'timezone' => 'UTC',
        'timezone_set' => false,
        'new_cards_per_day' => 20,
        'new_cards_per_day_max' => RepetitionLimits::MAX_NEW_CARDS_PER_DAY,
        'reminder_hour' => 19,
        'email_reminders' => false,
    ];

    public function test_guest_gets_401(): void
    {
        $this->getJson('/settings')->assertUnauthorized();
        $this->putJson('/settings', ['timezone' => 'Europe/Moscow'])->assertUnauthorized();
    }

    public function test_new_user_gets_defaults_without_creating_a_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/settings')->assertOk()->assertExactJson(self::DEFAULTS);
        $this->getJson('/me')->assertOk()->assertJsonPath('settings', self::DEFAULTS);

        $this->assertDatabaseMissing('user_settings', ['user_id' => $user->id]);
    }

    public function test_user_updates_all_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'timezone' => 'Europe/Moscow',
            'new_cards_per_day' => 35,
            'reminder_hour' => 8,
            'email_reminders' => true,
        ];

        $this->putJson('/settings', $payload)
            ->assertOk()
            ->assertExactJson([...$payload, 'timezone_set' => true, 'new_cards_per_day_max' => RepetitionLimits::MAX_NEW_CARDS_PER_DAY]);

        $this->getJson('/settings')->assertJsonPath('timezone', 'Europe/Moscow')->assertJsonPath('email_reminders', true);
        $this->getJson('/me')->assertJsonPath('settings.timezone', 'Europe/Moscow');
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id, ...$payload]);
    }

    public function test_partial_update_keeps_other_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/settings', ['timezone' => 'Asia/Almaty', 'reminder_hour' => 7])->assertOk();

        $this->putJson('/settings', ['email_reminders' => true])
            ->assertOk()
            ->assertJsonPath('timezone', 'Asia/Almaty')
            ->assertJsonPath('reminder_hour', 7)
            ->assertJsonPath('new_cards_per_day', 20)
            ->assertJsonPath('email_reminders', true);

        $this->putJson('/settings', [])->assertOk()->assertJsonPath('timezone', 'Asia/Almaty');

        $this->assertSame(1, UserSetting::query()->where('user_id', $user->id)->count());
    }

    public function test_boundary_values_are_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/settings', ['new_cards_per_day' => 0, 'reminder_hour' => 0, 'email_reminders' => false])
            ->assertOk()
            ->assertJsonPath('new_cards_per_day', 0)
            ->assertJsonPath('reminder_hour', 0);

        $this->putJson('/settings', ['new_cards_per_day' => RepetitionLimits::MAX_NEW_CARDS_PER_DAY, 'reminder_hour' => 23])
            ->assertOk()
            ->assertJsonPath('new_cards_per_day', RepetitionLimits::MAX_NEW_CARDS_PER_DAY)
            ->assertJsonPath('reminder_hour', 23);
    }

    public function test_legacy_timezone_names_are_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // так пояс отдают некоторые Android-устройства
        $this->putJson('/settings', ['timezone' => 'Asia/Calcutta'])->assertOk()->assertJsonPath('timezone', 'Asia/Calcutta');
    }

    public static function invalidPayloads(): array
    {
        return [
            'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
            'offset instead of IANA' => [['timezone' => '+03:00'], 'timezone'],
            'wrong case timezone' => [['timezone' => 'europe/moscow'], 'timezone'],
            'null timezone' => [['timezone' => null], 'timezone'],
            'empty timezone' => [['timezone' => ''], 'timezone'],
            'negative limit' => [['new_cards_per_day' => -1], 'new_cards_per_day'],
            'limit above ceiling' => [['new_cards_per_day' => RepetitionLimits::MAX_NEW_CARDS_PER_DAY + 1], 'new_cards_per_day'],
            'fractional limit' => [['new_cards_per_day' => 10.5], 'new_cards_per_day'],
            'hour 24' => [['reminder_hour' => 24], 'reminder_hour'],
            'negative hour' => [['reminder_hour' => -1], 'reminder_hour'],
            'string hour' => [['reminder_hour' => 'evening'], 'reminder_hour'],
            'non-boolean consent' => [['email_reminders' => 'yes'], 'email_reminders'],
            'null consent' => [['email_reminders' => null], 'email_reminders'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_values_are_rejected(array $payload, string $field): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/settings', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseMissing('user_settings', ['user_id' => $user->id]);
    }

    public function test_invalid_field_rejects_whole_update(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/settings', ['timezone' => 'Europe/Berlin', 'reminder_hour' => 30])->assertUnprocessable();
        $this->getJson('/settings')->assertJsonPath('timezone', 'UTC');
    }

    public function test_settings_of_other_users_are_isolated(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $this->putJson('/settings', ['timezone' => 'Europe/Berlin', 'email_reminders' => true])->assertOk();

        // чужой user_id в теле игнорируется
        Sanctum::actingAs($bob);
        $this->getJson('/settings')->assertExactJson(self::DEFAULTS);
        $this->putJson('/settings', ['user_id' => $alice->id, 'reminder_hour' => 6])->assertOk();

        $this->assertDatabaseHas('user_settings', ['user_id' => $alice->id, 'timezone' => 'Europe/Berlin', 'reminder_hour' => 19]);
        $this->assertDatabaseHas('user_settings', ['user_id' => $bob->id, 'timezone' => null, 'reminder_hour' => 6]);

        Sanctum::actingAs($alice);
        $this->getJson('/settings')->assertJsonPath('reminder_hour', 19)->assertJsonPath('timezone', 'Europe/Berlin');
    }

    public function test_child_account_has_own_settings(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create();

        Sanctum::actingAs($child);
        $this->putJson('/settings', ['new_cards_per_day' => 5])->assertOk();

        Sanctum::actingAs($parent);
        $this->getJson('/settings')->assertJsonPath('new_cards_per_day', 20);
    }

    public function test_settings_are_deleted_with_user(): void
    {
        $user = User::factory()->create();
        $user->settings()->create(['timezone' => 'Europe/Moscow']);

        $user->forceDelete();

        $this->assertDatabaseMissing('user_settings', ['user_id' => $user->id]);
    }

    public function test_repetition_limits_cap_user_setting(): void
    {
        $user = User::factory()->create();
        $limits = app(RepetitionLimits::class);

        $this->assertSame(20, $limits->newCardsPerDay($user));
        $this->assertNull($limits->maxSetsInRepetition($user));

        $user->settings()->create(['new_cards_per_day' => 50]);
        $this->assertSame(50, $limits->newCardsPerDay($user->fresh()));

        // потолок ниже настройки (например, после окончания подписки) — действует потолок
        $capped = new class extends RepetitionLimits
        {
            public function maxNewCardsPerDay(User $user): int
            {
                return 30;
            }
        };
        $this->assertSame(30, $capped->newCardsPerDay($user->fresh()));
    }

    public function test_limit_validation_follows_ceiling(): void
    {
        $this->app->instance(RepetitionLimits::class, new class extends RepetitionLimits
        {
            public function maxNewCardsPerDay(User $user): int
            {
                return 30;
            }
        });
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/settings', ['new_cards_per_day' => 31])->assertUnprocessable()->assertJsonValidationErrors('new_cards_per_day');
        $this->putJson('/settings', ['new_cards_per_day' => 30])->assertOk()->assertJsonPath('new_cards_per_day_max', 30);
    }

    public function test_current_day_uses_user_timezone(): void
    {
        $user = User::factory()->create();
        $this->assertSame('UTC', $user->settings->currentDay()->start->timezoneName);

        $this->travelTo('2026-09-26 23:30:00'); // UTC; в Москве 02:30 27-го — ещё «день» 26-го
        $user->settings()->create(['timezone' => 'Europe/Moscow']);

        $day = $user->fresh()->settings->currentDay();
        $this->assertSame('2026-09-26', $day->date);
        $this->assertSame('2026-09-26 01:00:00', $day->start->toDateTimeString());
    }
}
