<?php

namespace App\Enums;

/**
 * Оценка ответа при повторении карточки (FSRS). Значения 1–4 — как в FSRS и журнале оценок.
 */
enum ReviewRating: int
{
    use Arrayable;

    case again = 1;
    case hard = 2;
    case good = 3;
    case easy = 4;

    public function label(): string
    {
        return match ($this) {
            self::again => 'Снова',
            self::hard => 'Трудно',
            self::good => 'Хорошо',
            self::easy => 'Легко',
        };
    }
}
