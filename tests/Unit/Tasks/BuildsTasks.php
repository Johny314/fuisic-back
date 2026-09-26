<?php

namespace Tests\Unit\Tasks;

use App\Enums\AnswerStatus;
use App\Enums\TaskType;
use App\Models\Test\Task;
use App\Models\Test\TaskOption;
use App\Support\Tasks\AnswerCheck;

/** Вопрос в памяти, без БД: варианты — [id => верный?]. */
trait BuildsTasks
{
    /** @param  array<int, bool>  $options */
    private function task(TaskType $type, ?array $settings = null, array $options = [], int $points = 1): Task
    {
        $task = (new Task)->forceFill(['id' => 1, 'type' => $type, 'points' => $points, 'settings' => $settings]);
        $task->setRelation('options', (new TaskOption)->newCollection(array_map(
            fn (int $id, bool $isCorrect) => (new TaskOption)->forceFill(['id' => $id, 'task_id' => 1, 'is_correct' => $isCorrect]),
            array_keys($options),
            $options,
        )));

        return $task;
    }

    /** @param  array<string, mixed>  $answer */
    private function check(Task $task, array $answer): AnswerCheck
    {
        return $task->definition()->check($task, ['task_id' => $task->id] + $answer);
    }

    private function assertCheck(AnswerCheck $check, float $score, AnswerStatus $status, string $message = ''): void
    {
        $this->assertSame($score, $check->score, $message);
        $this->assertSame($status, $check->status, $message);
        $this->assertSame($status === AnswerStatus::correct, $check->isCorrect(), $message);
    }
}
