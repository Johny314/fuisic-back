<?php

namespace Tests\Feature\Api;

use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ни один ответ API для прохождения не содержит правильных ответов, настроек проверки и разбора (fuisic-back#60).
 */
class TaskAnswerLeakTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['секрет-текст', 'секрет-альтернатива', '31415.9', 'секрет-разбор'];

    private Test $test;

    /** @var list<Task> */
    private array $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->test = Test::factory()->for(User::factory()->admin())->create();
        $explanation = ['explanation' => 'секрет-разбор'];
        $this->tasks = [
            Task::factory()->for($this->test)->text(['секрет-текст', 'секрет-альтернатива'])->create($explanation),
            Task::factory()->for($this->test)->number(31415.9, 0.5, 'absolute', ['м'])->create($explanation),
            Task::factory()->for($this->test)->single(['Первый' => false, 'Второй' => true])->create($explanation + ['shuffle_options' => true]),
            Task::factory()->for($this->test)->multiple()->create($explanation),
        ];
    }

    public static function viewers(): array
    {
        return ['guest' => [false], 'student' => [true]];
    }

    #[DataProvider('viewers')]
    public function test_taking_endpoints_do_not_reveal_answers(bool $asStudent): void
    {
        if ($asStudent) {
            Sanctum::actingAs(User::factory()->create());
        }

        $this->assertClean($this->getJson('/test')->assertOk());
        $this->assertClean($this->getJson("/test/{$this->test->id}")->assertOk());

        $tasks = $this->getJson("/test/{$this->test->id}/tasks")->assertOk()->assertJsonCount(4);
        $this->assertClean($tasks);
        $tasks->assertJsonPath('2.type', 'single')->assertJsonCount(2, '2.options');
        $this->assertEqualsCanonicalizing(['id', 'text', 'image_url'], array_keys($tasks->json('2.options.0')));

        foreach ($this->tasks as $task) {
            $this->assertClean($this->getJson("/task/{$task->id}")->assertOk()->assertJsonPath('id', $task->id));
        }

        // список задач — для редакторов: гостю 401, ученику — только свои (здесь пусто)
        $index = $this->getJson('/task');
        $asStudent ? $this->assertClean($index->assertOk()->assertJsonCount(0, 'data')) : $index->assertUnauthorized();
    }

    private function assertClean(TestResponse $response): void
    {
        $json = $response->getContent();

        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        foreach (['"is_correct"', '"answer"', '"explanation"', '"tolerance"', '"answers"'] as $key) {
            $this->assertStringNotContainsString($key, $json);
        }
    }
}
