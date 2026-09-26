<?php

namespace App\Enums;

/**
 * Итог проверки ответа на вопрос: partial — часть баллов (сейчас только у multiple).
 */
enum AnswerStatus: string
{
    use Arrayable;

    case correct = 'correct';
    case partial = 'partial';
    case incorrect = 'incorrect';

    public function label(): string
    {
        return match ($this) {
            self::correct => 'Верно',
            self::partial => 'Частично',
            self::incorrect => 'Неверно',
        };
    }
}
