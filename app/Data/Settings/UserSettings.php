<?php

namespace App\Data\Settings;

use App\Data\Data;
use App\Models\User;
use App\OpenApi\Property;
use App\Support\RepetitionLimits;
use OpenApi\Attributes\Schema;

/** Настройки текущего пользователя (ответ `GET/PUT settings` и поле `settings` в `/me`). */
#[Schema(required: ['timezone', 'timezone_set', 'new_cards_per_day', 'new_cards_per_day_max', 'reminder_hour', 'email_reminders'])]
class UserSettings extends Data
{
    #[Property(description: 'Часовой пояс IANA; UTC, пока устройство не передало свой', example: 'Europe/Moscow')]
    public string $timezone;

    #[Property(description: 'false — часовой пояс ещё не передан (действует UTC): приложению стоит отправить пояс устройства', example: true)]
    public bool $timezone_set;

    #[Property(description: 'Новых карточек в день', example: 20)]
    public int $new_cards_per_day;

    #[Property(description: 'Потолок настройки new_cards_per_day (может зависеть от подписки)', example: 200)]
    public int $new_cards_per_day_max;

    #[Property(description: 'Час email-напоминания по часовому поясу пользователя, 0–23', example: 19)]
    public int $reminder_hour;

    #[Property(description: 'Согласие на email-напоминания о повторениях', example: false)]
    public bool $email_reminders;

    public static function fromUser(User $user): self
    {
        $settings = $user->settings;

        return static::from([
            'timezone' => $settings->effectiveTimezone(),
            'timezone_set' => $settings->timezone !== null,
            'new_cards_per_day' => $settings->new_cards_per_day,
            'new_cards_per_day_max' => app(RepetitionLimits::class)->maxNewCardsPerDay($user),
            'reminder_hour' => $settings->reminder_hour,
            'email_reminders' => $settings->email_reminders,
        ]);
    }
}
