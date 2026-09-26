<?php

namespace App\Support\Tasks;

/**
 * Один вариант: ответ — `option_id`; верно, только если выбран ровно верный вариант.
 */
class SingleChoice extends ChoiceQuestion
{
    protected function correctCountIsValid(int $count): bool
    {
        return $count === 1;
    }

    protected function correctCountMessage(): string
    {
        return 'Отметьте ровно один верный вариант';
    }

    public function answerRules(): array
    {
        return ['option_id' => ['nullable', 'integer']];
    }

    public function answerText(array $answer): ?string
    {
        return isset($answer['option_id']) ? (string) (int) $answer['option_id'] : parent::answerText($answer);
    }

    protected function selectedIds(array $answer): array
    {
        return isset($answer['option_id']) ? [(int) $answer['option_id']] : self::legacyIds($answer);
    }

    protected function fraction(array $selected, array $correct): float
    {
        return count($selected) === 1 && in_array($selected[0], $correct, true) ? 1.0 : 0.0;
    }
}
