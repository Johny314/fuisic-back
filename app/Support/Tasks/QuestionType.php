<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;
use Illuminate\Validation\Validator;

/**
 * Описание типа вопроса: как валидировать настройки проверки (`tasks.settings`) и варианты,
 * что из них можно показать при прохождении. Новый тип — новый класс, существующие не меняются.
 */
abstract class QuestionType
{
    /** Картинка — путь из `POST /files` с purpose=task_image. */
    public const IMAGE_PATH_RULES = ['nullable', 'string', 'max:255', 'starts_with:task-images/', 'not_regex:/\.\./'];

    /** Вопрос с вариантами ответа (`task_options`). */
    public function usesOptions(): bool
    {
        return false;
    }

    /**
     * Нормализация ввода до валидации (например, «0,5» → «0.5»).
     *
     * @param  array{settings: mixed, options: mixed}  $input
     * @return array{settings: mixed, options: mixed}
     */
    public function prepare(array $input): array
    {
        return $input;
    }

    /**
     * Правила для `settings.*` и `options.*` (ключи — от корня тела запроса).
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Проверки, которые не выразить правилами.
     *
     * @param  array{settings: mixed, options: mixed}  $input
     */
    public function after(array $input, Validator $validator): void {}

    /**
     * Настройки проверки для хранения из провалидированного ввода.
     *
     * @param  array{settings: mixed, options: mixed}  $input
     */
    abstract public function settings(array $input): ?array;

    /** Часть настроек, которую можно отдать при прохождении. Правильных ответов здесь быть не должно. */
    public function publicSettings(?array $settings): ?array
    {
        return null;
    }

    /**
     * Настройки из поля `answer` старых клиентов (до fuisic-front#30); null — так этот тип не задаётся.
     */
    public function settingsFromAnswer(string $answer, ?array $current): ?array
    {
        return null;
    }

    /** Правильный ответ одной строкой — поле `answer` редактора и `correct_answer` после сдачи. */
    abstract public function answer(Task $task): ?string;

    /**
     * Ответ-строка из `POST /test/{test}/answers`. Временная проверка «как раньше» — полноценную
     * (регистр, допуск, единицы, частичные баллы) добавит fuisic-back#61.
     */
    abstract public function matches(Task $task, ?string $answer): bool;
}
