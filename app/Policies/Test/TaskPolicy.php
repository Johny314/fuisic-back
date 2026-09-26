<?php

namespace App\Policies\Test;

use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Задача наследует права своего теста.
 */
class TaskPolicy
{
    public function __construct(private readonly TestPolicy $tests) {}

    public function view(?User $user, Task $task): Response
    {
        return $this->tests->view($user, $task->test);
    }

    public function create(User $user, Test $test): bool
    {
        return $this->tests->update($user, $test);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->tests->update($user, $task->test);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->tests->update($user, $task->test);
    }
}
