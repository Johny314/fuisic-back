<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;
use App\Models\Test\TaskOption;
use Illuminate\Validation\Validator;

/**
 * Вопрос с вариантами (`task_options`): у варианта текст и/или картинка, признак верности, порядок — по массиву.
 */
abstract class ChoiceQuestion extends QuestionType
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 10;

    /** Сколько верных вариантов допустимо. */
    abstract protected function correctCountIsValid(int $count): bool;

    abstract protected function correctCountMessage(): string;

    public function usesOptions(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['nullable', 'array'],
            'options' => ['required', 'array', 'min:'.self::MIN_OPTIONS, 'max:'.self::MAX_OPTIONS],
            'options.*' => ['array'],
            'options.*.id' => ['nullable', 'integer', 'distinct'],
            'options.*.text' => ['nullable', 'string', 'max:1000', 'required_without:options.*.image_path'],
            'options.*.image_path' => self::IMAGE_PATH_RULES,
            'options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    public function after(array $input, Validator $validator): void
    {
        if (! is_array($input['options'] ?? null)) {
            return;
        }

        $correct = count(array_filter($input['options'], static fn ($option) => filter_var(
            is_array($option) ? ($option['is_correct'] ?? false) : false,
            FILTER_VALIDATE_BOOLEAN,
        )));

        if (! $this->correctCountIsValid($correct)) {
            $validator->errors()->add('options', $this->correctCountMessage());
        }
    }

    public function settings(array $input): ?array
    {
        return null;
    }

    public function answer(Task $task): ?string
    {
        $texts = $this->correctOptions($task)->map(fn (TaskOption $option) => $option->text ?? "#{$option->id}");

        return $texts->isEmpty() ? null : $texts->implode('; ');
    }

    // Временно (до #61): ответ — id вариантов через запятую, порядок не важен
    public function matches(Task $task, ?string $answer): bool
    {
        if ($answer === null || trim($answer) === '') {
            return false;
        }

        $given = collect(explode(',', $answer))->map(fn ($id) => (int) trim($id))->unique()->sort()->values();
        $expected = $this->correctOptions($task)->pluck('id')->sort()->values();

        return $given->all() === $expected->all();
    }

    private function correctOptions(Task $task)
    {
        return $task->options->filter(fn (TaskOption $option) => $option->is_correct)->values();
    }
}
