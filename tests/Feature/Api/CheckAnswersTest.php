<?php

namespace Tests\Feature\Api;

use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Проверка ответов по типам вопросов и частичные баллы (fuisic-back#61).
 */
class CheckAnswersTest extends TestCase
{
    use RefreshDatabase;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->teacher()->create();
        $this->test = Test::factory()->for($owner)->create();
        Sanctum::actingAs($owner);
    }

    private function option(Task $task, string $text): int
    {
        return $task->options->firstWhere('text', $text)->id;
    }

    public function test_mixed_test_is_scored_by_type(): void
    {
        $single = Task::factory()->for($this->test)->single()->create(['points' => 2]);
        $multiple = Task::factory()->for($this->test)->multiple(['A' => true, 'B' => true, 'C' => true, 'D' => false])->create(['points' => 3]);
        $text = Task::factory()->for($this->test)->text(['ёж'])->create();
        $number = Task::factory()->for($this->test)->number(9.8, 0.1, 'absolute', ['м/с²'])->create(['points' => 4]);
        $unitless = Task::factory()->for($this->test)->number(1000)->create(['points' => 2]);
        $legacy = Task::factory()->for($this->test)->text(['ньютон'])->create();
        Task::factory()->for($this->test)->text(['без ответа'])->create(['points' => 5]);
        $foreign = Task::factory()->create(['points' => 10]);

        $this->postJson("/test/{$this->test->id}/answers", [
            'time' => 60,
            'answers' => [
                ['task_id' => $single->id, 'option_id' => $this->option($single, 'Верный')],
                ['task_id' => $multiple->id, 'option_ids' => [$this->option($multiple, 'A'), $this->option($multiple, 'B'), $this->option($multiple, 'D'), $foreign->id + 1000]],
                ['task_id' => $text->id, 'text' => '  ЕЖ '],
                ['task_id' => $number->id, 'value' => '9,9 м / с²'],
                ['task_id' => $unitless->id, 'value' => '1 000 км'],
                ['task_id' => $legacy->id, 'answer' => 'Ньютон'],
                ['task_id' => $foreign->id, 'text' => 'что угодно'],
            ],
        ])
            ->assertSuccessful()
            // 2 + 1 + 1 + 4 + 0 + 1; максимум — все вопросы теста, в том числе без ответа
            ->assertJsonPath('total_score', 9)
            ->assertJsonPath('max_score', 18)
            ->assertJsonPath('time', 60)
            ->assertJsonCount(7, 'results')
            ->assertJsonPath('results.0.score', 2)
            ->assertJsonPath('results.0.max_score', 2)
            ->assertJsonPath('results.0.status', 'correct')
            ->assertJsonPath('results.0.is_correct', true)
            ->assertJsonPath('results.0.answer', (string) $this->option($single, 'Верный'))
            ->assertJsonPath('results.0.correct_answer', 'Верный')
            ->assertJsonPath('results.1.score', 1)
            ->assertJsonPath('results.1.max_score', 3)
            ->assertJsonPath('results.1.status', 'partial')
            ->assertJsonPath('results.1.is_correct', false)
            ->assertJsonPath('results.2.status', 'correct')
            ->assertJsonPath('results.2.answer', 'ЕЖ')
            ->assertJsonPath('results.3.score', 4)
            ->assertJsonPath('results.3.status', 'correct')
            ->assertJsonPath('results.4.score', 0)
            ->assertJsonPath('results.4.status', 'incorrect')
            ->assertJsonPath('results.5.status', 'correct')
            ->assertJsonPath('results.5.answer', 'Ньютон')
            // вопрос другого теста: без баллов и без раскрытия
            ->assertJsonPath('results.6.task', null)
            ->assertJsonPath('results.6.correct_answer', null)
            ->assertJsonPath('results.6.max_score', 0)
            ->assertJsonPath('results.6.status', 'incorrect')
            // формат не расширяется: ни разбора, ни признаков верности вариантов
            ->assertJsonMissingPath('results.0.explanation')
            ->assertJsonMissingPath('results.0.task.explanation')
            ->assertJsonMissingPath('results.1.task.options.0.is_correct')
            ->assertJsonMissingPath('results.3.task.settings.value');
    }

    public function test_score_is_rounded_and_empty_answers_are_incorrect(): void
    {
        $multiple = Task::factory()->for($this->test)->multiple(['A' => true, 'B' => true, 'C' => true, 'D' => false])->create();
        $single = Task::factory()->for($this->test)->single()->create();
        $text = Task::factory()->for($this->test)->text(['ватт'])->create();
        $number = Task::factory()->for($this->test)->number(0)->create();

        $this->postJson("/test/{$this->test->id}/answers", [
            'time' => 5,
            'answers' => [
                ['task_id' => $multiple->id, 'option_ids' => [$this->option($multiple, 'A')]],
                ['task_id' => $single->id],
                ['task_id' => $text->id, 'text' => ''],
                ['task_id' => $number->id, 'value' => null],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('results.0.score', 0.33)
            ->assertJsonPath('results.0.status', 'partial')
            ->assertJsonPath('results.1.status', 'incorrect')
            ->assertJsonPath('results.1.answer', null)
            ->assertJsonPath('results.2.status', 'incorrect')
            ->assertJsonPath('results.3.status', 'incorrect')
            ->assertJsonPath('total_score', 0.33)
            ->assertJsonPath('max_score', 4);
    }

    public function test_numeric_json_values_are_accepted(): void
    {
        $number = Task::factory()->for($this->test)->number(-3)->create();
        $legacy = Task::factory()->for($this->test)->number(0.5)->create();

        $this->postJson("/test/{$this->test->id}/answers", [
            'time' => 5,
            'answers' => [
                ['task_id' => $number->id, 'value' => -3],
                ['task_id' => $legacy->id, 'answer' => 0.5],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('results.0.status', 'correct')
            ->assertJsonPath('results.0.answer', '-3')
            ->assertJsonPath('results.1.status', 'correct')
            ->assertJsonPath('results.1.answer', '0.5');
    }

    public static function invalidAnswers(): array
    {
        return [
            'no task_id' => [[['text' => 'а']], 'answers.0.task_id'],
            'duplicate task_id' => [[['task_id' => 1, 'text' => 'а'], ['task_id' => 1, 'text' => 'б']], 'answers.1.task_id'],
            'option_id not integer' => [[['task_id' => 1, 'option_id' => 'A']], 'answers.0.option_id'],
            'option_ids not array' => [[['task_id' => 1, 'option_ids' => '1,2']], 'answers.0.option_ids'],
            'option_ids items not integer' => [[['task_id' => 1, 'option_ids' => ['A']]], 'answers.0.option_ids.0'],
            'text not string' => [[['task_id' => 1, 'text' => ['а']]], 'answers.0.text'],
            'value array' => [[['task_id' => 1, 'value' => [1]]], 'answers.0.value'],
            'answer array' => [[['task_id' => 1, 'answer' => [1]]], 'answers.0.answer'],
            'answer too long' => [[['task_id' => 1, 'answer' => str_repeat('а', 1001)]], 'answers.0.answer'],
            'item not object' => [['1'], 'answers.0'],
        ];
    }

    #[DataProvider('invalidAnswers')]
    public function test_invalid_answers_are_rejected(array $answers, string $error): void
    {
        $this->postJson("/test/{$this->test->id}/answers", ['time' => 5, 'answers' => $answers])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    }
}
