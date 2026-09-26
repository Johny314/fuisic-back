<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;
use App\Models\Test\TaskOption;
use Illuminate\Support\Collection;
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

    public function check(Task $task, array $answer): AnswerCheck
    {
        $correct = $this->correctOptions($task)->pluck('id')->all();
        // id не из этого вопроса не засчитываются ни в плюс, ни в минус
        $selected = array_values(array_intersect(array_unique($this->selectedIds($answer)), $task->options->pluck('id')->all()));

        return AnswerCheck::fraction($this->fraction($selected, $correct), $task->points);
    }

    /**
     * id выбранных вариантов из ответа.
     *
     * @param  array<string, mixed>  $answer
     * @return list<int>
     */
    abstract protected function selectedIds(array $answer): array;

    /**
     * Доля верности по выбранным (только варианты этого вопроса, без повторов) и верным id.
     *
     * @param  list<int>  $selected
     * @param  list<int>  $correct
     */
    abstract protected function fraction(array $selected, array $correct): float;

    /**
     * id из устаревшего `answer`: «3» или «3,5», порядок не важен; не числа пропускаются.
     *
     * @param  array<string, mixed>  $answer
     * @return list<int>
     */
    protected static function legacyIds(array $answer): array
    {
        $ids = array_map('trim', explode(',', self::legacyAnswer($answer) ?? ''));

        return array_values(array_map('intval', array_filter($ids, 'ctype_digit')));
    }

    /** @return Collection<int, TaskOption> */
    private function correctOptions(Task $task): Collection
    {
        return $task->options->filter(fn (TaskOption $option) => $option->is_correct)->values();
    }
}
