<?php

namespace App\Enums;

/**
 * Состояние карточки в интервальных повторениях: new — ещё не оценивалась,
 * learning / relearning — короткие шаги в минутах, review — интервалы в днях.
 */
enum ReviewState: int
{
    use Arrayable;

    case new = 0;
    case learning = 1;
    case review = 2;
    case relearning = 3;

    public function label(): string
    {
        return match ($this) {
            self::new => 'Новая',
            self::learning => 'Изучение',
            self::review => 'Повторение',
            self::relearning => 'Переучивание',
        };
    }
}
