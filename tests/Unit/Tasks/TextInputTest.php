<?php

namespace Tests\Unit\Tasks;

use App\Enums\AnswerStatus;
use App\Enums\TaskType;
use App\Support\Tasks\TextInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TextInputTest extends TestCase
{
    use BuildsTasks;

    public static function answers(): array
    {
        return [
            'exact' => ['ватт', true],
            'second allowed answer' => ['Вт', true],
            'case' => ['ВАТТ', true],
            'edge spaces' => ["  ватт\t", true],
            'repeated inner spaces' => ['джоуль  на   секунду', true],
            'non-breaking space' => ["джоуль\u{00A0}на секунду", true],
            'ё = е in answer' => ['Ёж', true],
            'ё = е in allowed answer' => ['ЕЖИК', true],
            'decomposed ё' => ["е\u{0308}ж", true],
            'other word' => ['джоуль', false],
            'part of answer' => ['ват', false],
            'empty' => ['', false],
            'only spaces' => ['   ', false],
        ];
    }

    #[DataProvider('answers')]
    public function test_check(string $text, bool $correct): void
    {
        $task = $this->task(TaskType::text, ['answers' => ['ватт', 'Вт', 'Джоуль на секунду', 'еж', 'Ёжик']], points: 2);

        $this->assertCheck(
            $this->check($task, ['text' => $text]),
            $correct ? 2.0 : 0.0,
            $correct ? AnswerStatus::correct : AnswerStatus::incorrect,
        );
    }

    public function test_legacy_answer_and_missing_answer(): void
    {
        $task = $this->task(TaskType::text, ['answers' => ['ньютон']]);

        $this->assertTrue($this->check($task, ['answer' => ' Ньютон '])->isCorrect());
        $this->assertTrue($this->check($task, ['text' => 'ньютон', 'answer' => 'джоуль'])->isCorrect());
        $this->assertFalse($this->check($task, [])->isCorrect());
        $this->assertFalse($this->check($task, ['text' => null])->isCorrect());
        $this->assertFalse($this->check($this->task(TaskType::text, null), ['text' => 'ньютон'])->isCorrect());
    }

    public function test_normalize(): void
    {
        $this->assertSame('еж и елка', TextInput::normalize("  Ёж\u{202F} и\n\nЁлка "));
    }
}
