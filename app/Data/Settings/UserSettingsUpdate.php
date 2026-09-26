<?php

namespace App\Data\Settings;

use App\Data\Data;
use App\Models\User;
use App\OpenApi\Property;
use App\Support\RepetitionLimits;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Optional;

/** Частичное обновление: меняются только переданные поля. */
#[Schema]
class UserSettingsUpdate extends Data
{
    #[Property(type: 'string', description: 'Часовой пояс IANA (устаревшие имена вроде Europe/Kiev тоже принимаются)', example: 'Europe/Moscow')]
    public string|Optional $timezone;

    #[Property(type: 'integer', minimum: 0, description: 'Не больше new_cards_per_day_max', example: 20)]
    public int|Optional $new_cards_per_day;

    #[Property(type: 'integer', minimum: 0, maximum: 23, example: 19)]
    public int|Optional $reminder_hour;

    #[Property(type: 'boolean', example: true)]
    public bool|Optional $email_reminders;

    public static function rules(): array
    {
        $user = request()->user();
        $max = $user instanceof User
            ? app(RepetitionLimits::class)->maxNewCardsPerDay($user)
            : RepetitionLimits::MAX_NEW_CARDS_PER_DAY;

        return [
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all_with_bc'],
            'new_cards_per_day' => ['sometimes', 'required', 'integer', 'between:0,'.$max],
            'reminder_hour' => ['sometimes', 'required', 'integer', 'between:0,23'],
            'email_reminders' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
