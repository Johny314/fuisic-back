<?php

namespace App\Models;

use App\Support\LocalDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Настройки пользователя (1:1). У пользователя без строки — значения по умолчанию
 * (`User::settings()` c `withDefault`), строка появляется при первом сохранении.
 */
class UserSetting extends Model
{
    public const DEFAULT_TIMEZONE = 'UTC';

    protected $fillable = [
        'timezone',
        'new_cards_per_day',
        'reminder_hour',
        'email_reminders',
    ];

    // те же значения, что default в миграции
    protected $attributes = [
        'timezone' => null,
        'new_cards_per_day' => 20,
        'reminder_hour' => 19,
        'email_reminders' => false,
    ];

    protected function casts(): array
    {
        return [
            'new_cards_per_day' => 'integer',
            'reminder_hour' => 'integer',
            'email_reminders' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Часовой пояс для расчётов: UTC, пока устройство не передало свой. */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?? self::DEFAULT_TIMEZONE;
    }

    public function currentDay(): LocalDay
    {
        return LocalDay::current($this->effectiveTimezone());
    }
}
