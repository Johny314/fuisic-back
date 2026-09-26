<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Test\Task;
use App\Models\Test\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'type' => TaskType::text->value,
            'problem_statement' => $this->faker->sentence(),
            'settings' => ['answers' => [$this->faker->word()]],
        ];
    }

    /** Ввод текста с одним ответом — как задачи до типов вопросов. */
    public function answer(string $answer): static
    {
        return $this->text([$answer]);
    }

    /** @param  list<string>  $answers */
    public function text(array $answers): static
    {
        return $this->state(['type' => TaskType::text->value, 'settings' => ['answers' => $answers]]);
    }

    /** @param  list<string>  $units */
    public function number(int|float $value, int|float|null $tolerance = null, string $toleranceType = 'absolute', array $units = []): static
    {
        return $this->state([
            'type' => TaskType::number->value,
            'settings' => ['value' => $value, 'tolerance' => $tolerance, 'tolerance_type' => $toleranceType, 'units' => $units],
        ]);
    }

    /**
     * Вопрос с вариантами: [текст => верный?].
     *
     * @param  array<string, bool>  $options
     */
    public function choice(TaskType $type, array $options): static
    {
        return $this->state(['type' => $type->value, 'settings' => null])
            ->afterCreating(function (Task $task) use ($options) {
                $position = 0;
                foreach ($options as $text => $isCorrect) {
                    $task->options()->create(['text' => (string) $text, 'is_correct' => $isCorrect, 'position' => $position++]);
                }
            });
    }

    /** @param  array<string, bool>|null  $options */
    public function single(?array $options = null): static
    {
        return $this->choice(TaskType::single, $options ?? ['Верный' => true, 'Неверный' => false, 'Тоже неверный' => false]);
    }

    /** @param  array<string, bool>|null  $options */
    public function multiple(?array $options = null): static
    {
        return $this->choice(TaskType::multiple, $options ?? ['Верный' => true, 'Второй верный' => true, 'Неверный' => false]);
    }
}
