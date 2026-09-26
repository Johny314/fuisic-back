<?php

namespace Tests\Feature\Database;

use App\Enums\TaskType;
use App\Models\Test\Task;
use App\Models\Test\Test;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Миграция существующих задач в типы вопросов (fuisic-back#60) и её откат.
 */
class TaskQuestionTypesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_09_26_220000_add_question_types_to_tasks.php');
    }

    public function test_existing_tasks_are_converted_and_restored(): void
    {
        $test = Test::factory()->create();
        $this->migration->down();
        $this->assertTrue(Schema::hasColumn('tasks', 'answer'));

        $answers = ['60', '1.25', '-3', '0', 'ньютон', '0,5', '0.50', '007', '1e3', '', null];
        $ids = [];
        foreach ($answers as $answer) {
            $ids[] = DB::table('tasks')->insertGetId([
                'test_id' => $test->id,
                'problem_statement' => 'Условие '.$answer,
                'answer' => $answer,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->migration->up();
        $this->assertFalse(Schema::hasColumn('tasks', 'answer'));

        $tasks = Task::query()->findMany($ids)->keyBy('id');
        $expected = [
            [TaskType::number, ['value' => 60, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]],
            [TaskType::number, ['value' => 1.25, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]],
            [TaskType::number, ['value' => -3, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]],
            [TaskType::number, ['value' => 0, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]],
            [TaskType::text, ['answers' => ['ньютон']]],
            // неканоничная запись числа остаётся текстом — откат вернёт её как была
            [TaskType::text, ['answers' => ['0,5']]],
            [TaskType::text, ['answers' => ['0.50']]],
            [TaskType::text, ['answers' => ['007']]],
            [TaskType::text, ['answers' => ['1e3']]],
            [TaskType::text, ['answers' => ['']]],
            [TaskType::text, ['answers' => []]],
        ];
        foreach ($ids as $i => $id) {
            $this->assertSame($expected[$i][0], $tasks[$id]->type, "answer {$answers[$i]}");
            // jsonb не хранит порядок ключей
            $settings = $tasks[$id]->settings;
            ksort($settings);
            ksort($expected[$i][1]);
            $this->assertSame($expected[$i][1], $settings, "answer {$answers[$i]}");
            $this->assertSame($answers[$i], $tasks[$id]->answer, "answer {$answers[$i]}");
            $this->assertSame(1, $tasks[$id]->points);
        }

        // старые ответы по-прежнему засчитываются
        $this->assertTrue($tasks[$ids[1]]->definition()->check($tasks[$ids[1]], ['answer' => '1.25'])->isCorrect());
        $this->assertTrue($tasks[$ids[4]]->definition()->check($tasks[$ids[4]], ['answer' => 'ньютон'])->isCorrect());

        $this->migration->down();
        $this->assertSame($answers, DB::table('tasks')->whereIn('id', $ids)->orderBy('id')->pluck('answer')->all());
        $this->assertFalse(Schema::hasTable('task_options'));

        $this->migration->up();
    }

    public function test_rollback_keeps_new_questions_readable(): void
    {
        $test = Test::factory()->create();
        $single = Task::factory()->for($test)->single(['А' => false, 'Б' => true])->create();
        $number = Task::factory()->for($test)->number(9.8, 0.1)->create();
        $long = Task::factory()->for($test)->text(['да'])->create(['problem_statement' => str_repeat('я', 300)]);

        $this->migration->down();

        $rows = DB::table('tasks')->get()->keyBy('id');
        $this->assertSame('Б', $rows[$single->id]->answer);
        $this->assertSame('9.8', $rows[$number->id]->answer);
        $this->assertSame('да', $rows[$long->id]->answer);
        $this->assertSame(255, mb_strlen($rows[$long->id]->problem_statement));

        $this->migration->up();
    }
}
