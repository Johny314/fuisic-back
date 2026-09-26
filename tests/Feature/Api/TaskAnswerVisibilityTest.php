<?php

namespace Tests\Feature\Api;

use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Правильный ответ задачи видит только тот, кто может её редактировать (fuisic-back#59).
 */
class TaskAnswerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Task $catalogTask;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->admin()->create();
        $this->catalogTask = Task::factory()->for(Test::factory()->for($admin))->answer('42')->create();
    }

    public function test_guest_and_student_do_not_see_the_answer_of_a_catalog_task(): void
    {
        $this->getJson("/task/{$this->catalogTask->id}")
            ->assertOk()
            ->assertJsonPath('id', $this->catalogTask->id)
            ->assertJsonMissingPath('answer');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/task/{$this->catalogTask->id}")->assertOk()->assertJsonMissingPath('answer');
    }

    public function test_catalog_editors_see_the_answer(): void
    {
        Sanctum::actingAs(User::factory()->moderator()->create());
        $this->getJson("/task/{$this->catalogTask->id}")->assertOk()->assertJsonPath('answer', '42');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson("/task/{$this->catalogTask->id}")->assertOk()->assertJsonPath('answer', '42');
    }

    public function test_owner_sees_the_answer_of_own_task(): void
    {
        $owner = User::factory()->teacher()->create();
        $task = Task::factory()->for(Test::factory()->for($owner))->answer('секрет')->create();
        Sanctum::actingAs($owner);

        $this->getJson("/task/{$task->id}")->assertOk()->assertJsonPath('answer', 'секрет');
    }
}
