<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Models\Test\Task;
use App\Models\Test\TaskOption;
use App\Models\Test\Test;
use App\Support\Tasks\QuestionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Создание и изменение вопроса целиком: общие поля, настройки проверки по типу, варианты.
 * Не переданное поле не меняется. Старый формат `{problem_statement, answer}` (до fuisic-front#30) поддержан.
 */
class TaskEditor
{
    public function create(Test $test, array $input): Task
    {
        return $this->save(new Task(['test_id' => $test->id]), $input);
    }

    public function update(Task $task, array $input): Task
    {
        unset($input['test_id']);

        return $this->save($task, $input);
    }

    private function save(Task $task, array $input): Task
    {
        [$attributes, $options] = $this->validate($task, $input);

        DB::transaction(function () use ($task, $attributes, $options) {
            $task->fill($attributes)->save();

            if (! $task->definition()->usesOptions()) {
                $task->options()->delete();
            } elseif ($options !== null) {
                $this->syncOptions($task, $options);
            }
        });

        return $task->unsetRelation('options')->load('options');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>|null} атрибуты вопроса и варианты (null — не менять)
     */
    private function validate(Task $task, array $input): array
    {
        $base = Validator::make($input, [
            'type' => ['sometimes', 'required', Rule::enum(TaskType::class)],
            'problem_statement' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image_path' => ['sometimes', ...QuestionType::IMAGE_PATH_RULES],
            'points' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'shuffle_options' => ['sometimes', 'required', 'boolean'],
            'answer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array'],
        ])->validate();

        $type = isset($base['type']) ? TaskType::from($base['type']) : ($task->type ?? TaskType::text);
        $definition = $type->definition();
        $sameType = $task->exists && $task->type === $type;
        $existing = $task->exists ? $task->options()->get() : collect();

        $typed = $definition->prepare([
            'settings' => $this->settingsInput($task, $definition, $sameType, $input),
            'options' => array_key_exists('options', $input)
                ? $this->optionsInput($existing, $input['options'] ?? [])
                : $this->existingOptions($existing),
        ]);

        $validator = Validator::make($typed, $definition->rules());
        $validator->after(function ($validator) use ($definition, $typed, $existing, $input) {
            $definition->after($typed, $validator);

            if (! $definition->usesOptions() && ! empty($input['options'])) {
                $validator->errors()->add('options', 'У этого типа вопроса нет вариантов ответа');
            }

            foreach ((array) ($input['options'] ?? []) as $index => $option) {
                $id = is_array($option) ? ($option['id'] ?? null) : null;
                if ($id !== null && ! $existing->contains('id', (int) $id)) {
                    $validator->errors()->add("options.{$index}.id", 'Вариант не принадлежит этому вопросу');
                }
            }
        });
        $validated = $validator->validate();

        $statement = array_key_exists('problem_statement', $base) ? $base['problem_statement'] : $task->problem_statement;
        $image = array_key_exists('image_path', $base) ? $base['image_path'] : $task->image_path;
        if (blank($statement) && blank($image)) {
            throw ValidationException::withMessages(['problem_statement' => 'Укажите условие или картинку']);
        }

        $attributes = ['type' => $type, 'settings' => $definition->settings($validated + ['settings' => null, 'options' => null])]
            + array_intersect_key($base, array_flip(['problem_statement', 'image_path', 'points', 'explanation', 'shuffle_options']));

        if (! $definition->usesOptions() || ! array_key_exists('options', $input)) {
            return [$attributes, null];
        }

        // validated() собирает массив по правилам — порядок вариантов восстанавливаем по индексам
        $options = $validated['options'];
        ksort($options);

        return [$attributes, array_values($options)];
    }

    /** Настройки из запроса; без них — из старого `answer` или текущие. */
    private function settingsInput(Task $task, QuestionType $definition, bool $sameType, array $input): mixed
    {
        if (isset($input['settings'])) {
            return $input['settings'];
        }

        $current = $sameType ? $task->settings : null;
        $answer = $input['answer'] ?? null;
        if (! is_string($answer) || ($sameType && $answer === $definition->answer($task))) {
            return $current;
        }

        $settings = $definition->settingsFromAnswer($answer, $current);
        if ($settings === null && $definition->usesOptions()) {
            throw ValidationException::withMessages(['answer' => 'Ответ вопроса с вариантами задаётся в options']);
        }
        if ($settings === null) {
            throw ValidationException::withMessages(['answer' => 'Ответ должен быть числом']);
        }

        return $settings;
    }

    /** У существующего варианта без `image_path` картинка остаётся прежней. */
    private function optionsInput($existing, mixed $options): mixed
    {
        if (! is_array($options)) {
            return $options;
        }

        return array_map(function ($option) use ($existing) {
            if (! is_array($option) || ! isset($option['id']) || array_key_exists('image_path', $option)) {
                return $option;
            }

            return $option + ['image_path' => $existing->firstWhere('id', (int) $option['id'])?->image_path];
        }, array_values($options));
    }

    private function existingOptions($existing): array
    {
        return $existing->map(fn (TaskOption $option) => [
            'id' => $option->id,
            'text' => $option->text,
            'image_path' => $option->image_path,
            'is_correct' => $option->is_correct,
        ])->all();
    }

    /** @param  list<array<string, mixed>>  $options */
    private function syncOptions(Task $task, array $options): void
    {
        $existing = $task->options()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($options) as $position => $option) {
            $attributes = [
                'text' => $option['text'] ?? null,
                'image_path' => $option['image_path'] ?? null,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'position' => $position,
            ];

            $model = isset($option['id']) ? $existing->get((int) $option['id']) : null;
            if ($model) {
                $model->update($attributes);
            } else {
                $model = $task->options()->create($attributes);
            }
            $kept[] = $model->id;
        }

        $task->options()->whereNotIn('id', $kept)->delete();
    }
}
