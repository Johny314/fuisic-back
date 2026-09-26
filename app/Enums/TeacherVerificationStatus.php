<?php

namespace App\Enums;

/**
 * Статус заявки на «Проверенного учителя». Значения — стабильные коды для логики фронта,
 * подписи — label().
 */
enum TeacherVerificationStatus: string
{
    use Arrayable;

    case pending = 'pending';
    case approved = 'approved';
    case rejected = 'rejected';
    case revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::pending => 'На рассмотрении',
            self::approved => 'Одобрена',
            self::rejected => 'Отклонена',
            self::revoked => 'Статус отозван',
        };
    }
}
