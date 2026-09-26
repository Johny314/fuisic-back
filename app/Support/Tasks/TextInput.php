<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;

/**
 * Ввод текста: `settings.answers` — допустимые ответы.
 */
class TextInput extends QuestionType
{
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.answers' => ['required', 'array', 'min:1', 'max:20'],
            'settings.answers.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    public function settings(array $input): ?array
    {
        return ['answers' => array_values($input['settings']['answers'])];
    }

    public function settingsFromAnswer(string $answer, ?array $current): ?array
    {
        return ['answers' => [$answer]];
    }

    public function answer(Task $task): ?string
    {
        return $task->settings['answers'][0] ?? null;
    }

    public function matches(Task $task, ?string $answer): bool
    {
        return $answer !== null && in_array($answer, $task->settings['answers'] ?? [], true);
    }
}
