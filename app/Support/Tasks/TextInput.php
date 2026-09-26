<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;

/**
 * Ввод текста: `settings.answers` — допустимые ответы; ответ — `text`, сравнение через normalize().
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

    public function answerRules(): array
    {
        return ['text' => ['nullable', 'string', 'max:1000']];
    }

    public function answerText(array $answer): ?string
    {
        return is_string($answer['text'] ?? null) ? $answer['text'] : parent::answerText($answer);
    }

    public function check(Task $task, array $answer): AnswerCheck
    {
        $given = self::normalize($this->answerText($answer) ?? '');
        if ($given === '') {
            return AnswerCheck::incorrect($task->points);
        }

        foreach ($task->settings['answers'] ?? [] as $allowed) {
            if (is_string($allowed) && self::normalize($allowed) === $given) {
                return AnswerCheck::correct($task->points);
            }
        }

        return AnswerCheck::incorrect($task->points);
    }

    /** Без учёта регистра, краевых и повторных пробелов (в т.ч. неразрывных), «ё» = «е». */
    public static function normalize(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        $text = preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '';

        return str_replace('ё', 'е', mb_strtolower(trim($text)));
    }
}
