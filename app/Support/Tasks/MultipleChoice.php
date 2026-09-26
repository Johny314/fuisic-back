<?php

namespace App\Support\Tasks;

/**
 * Несколько вариантов: ответ — `option_ids`; баллы пропорционально
 * (выбрано верных − выбрано неверных) / всего верных, не меньше 0.
 */
class MultipleChoice extends ChoiceQuestion
{
    protected function correctCountIsValid(int $count): bool
    {
        return $count >= 1;
    }

    protected function correctCountMessage(): string
    {
        return 'Отметьте хотя бы один верный вариант';
    }

    public function answerRules(): array
    {
        return [
            'option_ids' => ['nullable', 'array', 'max:50'],
            'option_ids.*' => ['integer'],
        ];
    }

    public function answerText(array $answer): ?string
    {
        return is_array($answer['option_ids'] ?? null) ? implode(',', $this->selectedIds($answer)) : parent::answerText($answer);
    }

    protected function selectedIds(array $answer): array
    {
        return is_array($answer['option_ids'] ?? null)
            ? array_values(array_map('intval', $answer['option_ids']))
            : self::legacyIds($answer);
    }

    protected function fraction(array $selected, array $correct): float
    {
        if ($correct === []) {
            return 0.0;
        }

        $right = count(array_intersect($selected, $correct));
        $wrong = count($selected) - $right;

        return max(0, $right - $wrong) / count($correct);
    }
}
