<?php

namespace Tests\Unit\Tasks;

use App\Enums\AnswerStatus;
use App\Enums\TaskType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChoiceQuestionTest extends TestCase
{
    use BuildsTasks;

    public static function singleAnswers(): array
    {
        return [
            'correct option' => [['option_id' => 11], 2.0, AnswerStatus::correct],
            'correct option as string' => [['option_id' => '11'], 2.0, AnswerStatus::correct],
            'wrong option' => [['option_id' => 12], 0.0, AnswerStatus::incorrect],
            'foreign option' => [['option_id' => 99], 0.0, AnswerStatus::incorrect],
            'no answer' => [[], 0.0, AnswerStatus::incorrect],
            'null' => [['option_id' => null], 0.0, AnswerStatus::incorrect],
            'legacy id' => [['answer' => ' 11 '], 2.0, AnswerStatus::correct],
            'legacy two ids' => [['answer' => '11,12'], 0.0, AnswerStatus::incorrect],
            'legacy with foreign id' => [['answer' => '11,99'], 2.0, AnswerStatus::correct],
            'legacy garbage' => [['answer' => 'Верный'], 0.0, AnswerStatus::incorrect],
            'option_id wins over legacy' => [['option_id' => 12, 'answer' => '11'], 0.0, AnswerStatus::incorrect],
        ];
    }

    #[DataProvider('singleAnswers')]
    public function test_single(array $answer, float $score, AnswerStatus $status): void
    {
        $task = $this->task(TaskType::single, options: [11 => true, 12 => false, 13 => false], points: 2);

        $this->assertCheck($this->check($task, $answer), $score, $status);
        $this->assertSame(2, $this->check($task, $answer)->maxScore);
    }

    public static function multipleAnswers(): array
    {
        // верные 1, 2, 3; неверные 4, 5; баллы 3
        return [
            'all correct' => [['option_ids' => [1, 2, 3]], 3.0, AnswerStatus::correct],
            'order does not matter' => [['option_ids' => [3, 1, 2]], 3.0, AnswerStatus::correct],
            'two of three' => [['option_ids' => [1, 2]], 2.0, AnswerStatus::partial],
            'one of three' => [['option_ids' => [2]], 1.0, AnswerStatus::partial],
            'all correct and one wrong' => [['option_ids' => [1, 2, 3, 4]], 2.0, AnswerStatus::partial],
            'one correct and one wrong' => [['option_ids' => [1, 4]], 0.0, AnswerStatus::incorrect],
            'wrong outweighs — not below zero' => [['option_ids' => [1, 4, 5]], 0.0, AnswerStatus::incorrect],
            'everything selected' => [['option_ids' => [1, 2, 3, 4, 5]], 1.0, AnswerStatus::partial],
            'foreign ids ignored' => [['option_ids' => [1, 2, 3, 98, 99]], 3.0, AnswerStatus::correct],
            'duplicates count once' => [['option_ids' => [1, 1, 1]], 1.0, AnswerStatus::partial],
            'empty selection' => [['option_ids' => []], 0.0, AnswerStatus::incorrect],
            'no answer' => [[], 0.0, AnswerStatus::incorrect],
            'legacy ids' => [['answer' => '3, 1,2'], 3.0, AnswerStatus::correct],
            'legacy partial' => [['answer' => '1'], 1.0, AnswerStatus::partial],
        ];
    }

    #[DataProvider('multipleAnswers')]
    public function test_multiple(array $answer, float $score, AnswerStatus $status): void
    {
        $task = $this->task(TaskType::multiple, options: [1 => true, 2 => true, 3 => true, 4 => false, 5 => false], points: 3);

        $this->assertCheck($this->check($task, $answer), $score, $status);
    }

    public function test_multiple_score_is_rounded_to_two_digits(): void
    {
        $task = $this->task(TaskType::multiple, options: [1 => true, 2 => true, 3 => true, 4 => false], points: 1);

        $this->assertCheck($this->check($task, ['option_ids' => [1]]), 0.33, AnswerStatus::partial);
        $this->assertCheck($this->check($task, ['option_ids' => [1, 2]]), 0.67, AnswerStatus::partial);
    }

    public function test_zero_points_question_still_has_status(): void
    {
        $task = $this->task(TaskType::multiple, options: [1 => true, 2 => true, 3 => false], points: 0);

        $this->assertCheck($this->check($task, ['option_ids' => [1, 2]]), 0.0, AnswerStatus::correct);
        $this->assertCheck($this->check($task, ['option_ids' => [1]]), 0.0, AnswerStatus::partial);
    }

    public function test_answer_text(): void
    {
        $single = TaskType::single->definition();
        $multiple = TaskType::multiple->definition();

        $this->assertSame('11', $single->answerText(['option_id' => 11]));
        $this->assertSame('3,1', $multiple->answerText(['option_ids' => [3, '1']]));
        $this->assertSame('', $multiple->answerText(['option_ids' => []]));
        $this->assertSame('1,2', $multiple->answerText(['answer' => '1,2']));
        $this->assertNull($single->answerText([]));
    }
}
