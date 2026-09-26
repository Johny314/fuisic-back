<?php

namespace App\Support\Tasks;

use App\Enums\AnswerStatus;

/**
 * Результат проверки ответа на вопрос: баллы (до 2 знаков), максимум (`points` вопроса), статус.
 */
final readonly class AnswerCheck
{
    public function __construct(
        public float $score,
        public int $maxScore,
        public AnswerStatus $status,
    ) {}

    /** Доля верности 0…1 → баллы из `points`; статус — по доле, чтобы работал и вопрос на 0 баллов. */
    public static function fraction(float $fraction, int $points): self
    {
        $fraction = max(0.0, min(1.0, $fraction));

        return new self(
            round($fraction * $points, 2),
            $points,
            match (true) {
                $fraction >= 1.0 => AnswerStatus::correct,
                $fraction > 0.0 => AnswerStatus::partial,
                default => AnswerStatus::incorrect,
            },
        );
    }

    public static function correct(int $points): self
    {
        return self::fraction(1.0, $points);
    }

    public static function incorrect(int $points): self
    {
        return self::fraction(0.0, $points);
    }

    public function isCorrect(): bool
    {
        return $this->status === AnswerStatus::correct;
    }
}
