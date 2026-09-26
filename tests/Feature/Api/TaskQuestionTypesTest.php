<?php

namespace Tests\Feature\Api;

use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use App\Services\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Типы вопросов (fuisic-back#60): CRUD через API, валидация по типу, старый формат студии.
 */
class TaskQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->teacher()->create();
        $this->test = Test::factory()->for($this->owner)->create();
        Sanctum::actingAs($this->owner);
    }

    public static function validQuestions(): array
    {
        return [
            'single' => [[
                'type' => 'single',
                'problem_statement' => 'Единица силы? $F = ma$',
                'options' => [
                    ['text' => 'Ньютон', 'is_correct' => true],
                    ['text' => 'Джоуль', 'is_correct' => false],
                ],
            ]],
            'multiple' => [[
                'type' => 'multiple',
                'problem_statement' => 'Векторные величины?',
                'points' => 3,
                'options' => [
                    ['text' => 'Скорость', 'is_correct' => true],
                    ['text' => 'Масса'],
                    ['text' => 'Сила', 'is_correct' => true],
                ],
            ]],
            'text' => [[
                'type' => 'text',
                'problem_statement' => 'Единица мощности?',
                'settings' => ['answers' => ['ватт', 'Вт']],
            ]],
            'number' => [[
                'type' => 'number',
                'problem_statement' => 'Ускорение свободного падения?',
                'settings' => ['value' => '9,8', 'tolerance' => 2, 'tolerance_type' => 'percent', 'units' => ['м/с²']],
            ]],
        ];
    }

    #[DataProvider('validQuestions')]
    public function test_each_type_is_created_updated_and_deleted(array $payload): void
    {
        $created = $this->postJson('/task', ['test_id' => $this->test->id] + $payload)
            ->assertCreated()
            ->assertJsonPath('type', $payload['type'])
            ->assertJsonPath('points', $payload['points'] ?? 1)
            ->assertJsonPath('shuffle_options', false);
        $id = $created->json('id');

        $this->putJson("/task/{$id}", ['problem_statement' => 'Новое условие', 'explanation' => 'Разбор', 'points' => 5])
            ->assertOk()
            ->assertJsonPath('problem_statement', 'Новое условие')
            ->assertJsonPath('explanation', 'Разбор')
            ->assertJsonPath('points', 5)
            // настройки и варианты без изменений
            ->assertJsonPath('settings', $created->json('settings'))
            ->assertJsonCount(count($payload['options'] ?? []), 'options');

        $this->getJson("/task/{$id}")->assertOk()->assertJsonPath('type', $payload['type']);

        $this->deleteJson("/task/{$id}")->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $id]);
    }

    public function test_settings_are_normalized(): void
    {
        $this->postJson('/task', ['test_id' => $this->test->id] + self::validQuestions()['number'][0])
            ->assertCreated()
            ->assertJsonPath('settings', ['value' => 9.8, 'tolerance' => 2, 'tolerance_type' => 'percent', 'units' => ['м/с²']])
            ->assertJsonPath('answer', '9.8')
            ->assertJsonPath('options', []);

        $this->postJson('/task', ['test_id' => $this->test->id, 'type' => 'number', 'problem_statement' => '?', 'settings' => ['value' => 5, 'extra' => 'x']])
            ->assertCreated()
            ->assertJsonPath('settings', ['value' => 5, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]);
    }

    public static function invalidQuestions(): array
    {
        $options = fn (bool ...$correct) => array_map(fn ($isCorrect, $i) => ['text' => "Вариант {$i}", 'is_correct' => $isCorrect], $correct, array_keys($correct));

        return [
            'single without correct' => [['type' => 'single', 'options' => $options(false, false)], 'options'],
            'single with two correct' => [['type' => 'single', 'options' => $options(true, true, false)], 'options'],
            'multiple without correct' => [['type' => 'multiple', 'options' => $options(false, false, false)], 'options'],
            'choice with one option' => [['type' => 'single', 'options' => $options(true)], 'options'],
            'choice without options' => [['type' => 'multiple'], 'options'],
            'option without text and image' => [['type' => 'single', 'options' => [['is_correct' => true], ['text' => 'б']]], 'options.0.text'],
            'text without answers' => [['type' => 'text', 'settings' => ['answers' => []]], 'settings.answers'],
            'text without settings' => [['type' => 'text'], 'settings'],
            'number without value' => [['type' => 'number', 'settings' => ['tolerance' => 1]], 'settings.value'],
            'number not numeric' => [['type' => 'number', 'settings' => ['value' => 'десять']], 'settings.value'],
            'negative tolerance' => [['type' => 'number', 'settings' => ['value' => 1, 'tolerance' => -1]], 'settings.tolerance'],
            'unknown tolerance type' => [['type' => 'number', 'settings' => ['value' => 1, 'tolerance_type' => 'relative']], 'settings.tolerance_type'],
            'options for text' => [['type' => 'text', 'settings' => ['answers' => ['а']], 'options' => $options(true, false)], 'options'],
            'unknown type' => [['type' => 'essay'], 'type'],
            'points out of range' => [['type' => 'text', 'settings' => ['answers' => ['а']], 'points' => 101], 'points'],
            'foreign image path' => [['type' => 'text', 'settings' => ['answers' => ['а']], 'image_path' => 'avatars/../x.png'], 'image_path'],
            'no statement' => [['type' => 'text', 'problem_statement' => '', 'settings' => ['answers' => ['а']]], 'problem_statement'],
        ];
    }

    #[DataProvider('invalidQuestions')]
    public function test_validation_depends_on_type(array $payload, string $error): void
    {
        $this->postJson('/task', $payload + ['test_id' => $this->test->id, 'problem_statement' => 'Вопрос'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_image_only_question_is_allowed(): void
    {
        $this->postJson('/task', [
            'test_id' => $this->test->id,
            'type' => 'single',
            'image_path' => 'task-images/scheme.png',
            'options' => [
                ['image_path' => 'task-images/a.png', 'is_correct' => true],
                ['image_path' => 'task-images/b.png'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('problem_statement', null)
            ->assertJsonPath('image_path', 'task-images/scheme.png')
            ->assertJsonPath('options.0.image_path', 'task-images/a.png')
            ->assertJsonPath('options.0.text', null);

        $this->assertNotNull(Task::query()->first()->image_url);
    }

    public function test_task_image_can_be_uploaded(): void
    {
        Storage::fake(app(MediaStorage::class)->disk());

        $path = $this->post('/files', ['file' => UploadedFile::fake()->image('scheme.png'), 'purpose' => 'task_image'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('path');

        $this->assertStringStartsWith('task-images/', $path);
    }

    public function test_options_are_synced_by_id(): void
    {
        $task = Task::factory()->for($this->test)->single(['А' => true, 'Б' => false, 'В' => false])->create();
        [$a, $b, $c] = $task->options->all();
        $b->update(['image_path' => 'task-images/b.png']);

        $this->putJson("/task/{$task->id}", [
            'options' => [
                ['id' => $b->id, 'text' => 'Б2', 'is_correct' => true],
                ['text' => 'Г'],
                ['id' => $a->id, 'text' => 'А', 'is_correct' => false, 'image_path' => null],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(3, 'options')
            ->assertJsonPath('options.0.id', $b->id)
            ->assertJsonPath('options.0.text', 'Б2')
            ->assertJsonPath('options.0.is_correct', true)
            // без image_path у существующего варианта картинка сохраняется
            ->assertJsonPath('options.0.image_path', 'task-images/b.png')
            ->assertJsonPath('options.1.text', 'Г')
            ->assertJsonPath('options.2.id', $a->id)
            ->assertJsonPath('answer', 'Б2');

        $this->assertDatabaseMissing('task_options', ['id' => $c->id]);
    }

    public function test_option_of_another_task_cannot_be_hijacked(): void
    {
        $task = Task::factory()->for($this->test)->single()->create();
        $foreign = Task::factory()->single()->create()->options->first();

        $this->putJson("/task/{$task->id}", [
            'options' => [['id' => $foreign->id, 'text' => 'Моё', 'is_correct' => true], ['text' => 'Б']],
        ])->assertUnprocessable()->assertJsonValidationErrors('options.0.id');

        $this->assertSame($foreign->text, $foreign->fresh()->text);
    }

    public function test_changing_type(): void
    {
        $task = Task::factory()->for($this->test)->single()->create();

        // single → multiple: варианты остаются, проверяются правила нового типа
        $this->putJson("/task/{$task->id}", ['type' => 'multiple'])->assertOk()->assertJsonCount(3, 'options');

        // → text: нужны настройки, варианты удаляются
        $this->putJson("/task/{$task->id}", ['type' => 'text'])->assertUnprocessable()->assertJsonValidationErrors('settings');
        $this->putJson("/task/{$task->id}", ['type' => 'text', 'settings' => ['answers' => ['да']]])
            ->assertOk()
            ->assertJsonPath('options', [])
            ->assertJsonPath('settings', ['answers' => ['да']]);
        $this->assertDatabaseMissing('task_options', ['task_id' => $task->id]);

        // → single без вариантов нельзя
        $this->putJson("/task/{$task->id}", ['type' => 'single'])->assertUnprocessable()->assertJsonValidationErrors('options');
    }

    public function test_legacy_studio_payload_still_works(): void
    {
        // формат fuisic-front до #30: {test_id, problem_statement, answer}
        $id = $this->postJson('/task', ['test_id' => (string) $this->test->id, 'problem_statement' => '2 + 2?', 'answer' => '4'])
            ->assertCreated()
            ->assertJsonPath('type', 'text')
            ->assertJsonPath('answer', '4')
            ->assertJsonPath('settings', ['answers' => ['4']])
            ->json('id');

        $this->putJson("/task/{$id}", ['problem_statement' => '2 + 3?', 'answer' => '5'])
            ->assertOk()
            ->assertJsonPath('problem_statement', '2 + 3?')
            ->assertJsonPath('answer', '5');

        $this->getJson("/task?filter[test_id]={$this->test->id}")
            ->assertOk()
            ->assertJsonPath('data.0.problem_statement', '2 + 3?')
            ->assertJsonPath('data.0.answer', '5');
    }

    public function test_legacy_payload_keeps_new_settings(): void
    {
        $number = Task::factory()->for($this->test)->number(9.8, 0.1, 'absolute', ['м/с²'])->create();
        $text = Task::factory()->for($this->test)->text(['ватт', 'Вт'])->create();
        $choice = Task::factory()->for($this->test)->single()->create();

        // старая студия пересохраняет вопрос с тем же ответом — ничего не теряется
        $this->putJson("/task/{$number->id}", ['problem_statement' => 'g?', 'answer' => '9.8'])
            ->assertOk()->assertJsonPath('settings.tolerance', 0.1)->assertJsonPath('settings.units', ['м/с²']);
        $this->putJson("/task/{$text->id}", ['problem_statement' => 'P?', 'answer' => 'ватт'])
            ->assertOk()->assertJsonPath('settings.answers', ['ватт', 'Вт']);
        $this->putJson("/task/{$choice->id}", ['problem_statement' => '?', 'answer' => 'Верный'])
            ->assertOk()->assertJsonCount(3, 'options');

        // новое значение числа — допуск и единицы остаются
        $this->putJson("/task/{$number->id}", ['answer' => '9,81'])
            ->assertOk()->assertJsonPath('settings.value', 9.81)->assertJsonPath('settings.tolerance', 0.1);
        $this->putJson("/task/{$number->id}", ['answer' => 'много'])
            ->assertUnprocessable()->assertJsonValidationErrors('answer');
        $this->putJson("/task/{$choice->id}", ['answer' => 'Другой'])
            ->assertUnprocessable()->assertJsonValidationErrors('answer');
    }

    public function test_strangers_cannot_edit_questions(): void
    {
        $task = Task::factory()->for($this->test)->single()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/task', ['test_id' => $this->test->id] + self::validQuestions()['text'][0])->assertForbidden();
        $this->putJson("/task/{$task->id}", ['points' => 10])->assertForbidden();
        $this->deleteJson("/task/{$task->id}")->assertForbidden();
        $this->assertSame(1, $task->fresh()->points);
    }

    public function test_check_answers_accepts_legacy_answers_for_every_type(): void
    {
        $text = Task::factory()->for($this->test)->text(['ватт', 'Вт'])->create();
        $number = Task::factory()->for($this->test)->number(1.25)->create();
        $single = Task::factory()->for($this->test)->single()->create();
        $multiple = Task::factory()->for($this->test)->multiple()->create();
        $correct = fn (Task $task) => $task->options->where('is_correct', true)->pluck('id')->implode(',');

        $this->postJson("/test/{$this->test->id}/answers", [
            'time' => 10,
            'answers' => [
                ['task_id' => $text->id, 'answer' => 'Вт'],
                ['task_id' => $number->id, 'answer' => '1.25'],
                ['task_id' => $single->id, 'answer' => $correct($single)],
                ['task_id' => $multiple->id, 'answer' => $correct($multiple)],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('total_score', 4)
            ->assertJsonPath('results.0.correct_answer', 'ватт')
            ->assertJsonPath('results.1.correct_answer', '1.25')
            ->assertJsonPath('results.2.correct_answer', 'Верный')
            ->assertJsonPath('results.3.correct_answer', 'Верный; Второй верный')
            ->assertJsonPath('results.2.task.type', 'single')
            ->assertJsonMissingPath('results.2.task.options.0.is_correct');
    }
}
