<?php

namespace Tests\Feature\Database;

use App\Enums\TaskType;
use App\Models\Test\Task;
use Database\Seeders\PhysicsContentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicsContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_tests_contain_every_question_type(): void
    {
        $this->seed([RoleSeeder::class, UserSeeder::class, SectionSeeder::class, PhysicsContentSeeder::class]);

        foreach (TaskType::cases() as $type) {
            $this->assertTrue(Task::query()->where('type', $type)->exists(), $type->value);
        }

        $this->assertTrue(Task::query()->where('type', TaskType::number)->whereNotNull('settings->tolerance')->exists());
        $this->assertTrue(Task::query()->where('type', TaskType::text)->get()->contains(fn (Task $task) => count($task->settings['answers']) > 1));
        $this->assertSame(3, Task::query()->where('type', TaskType::multiple)->first()->options()->where('is_correct', true)->count());
    }
}
