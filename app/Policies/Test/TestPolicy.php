<?php

namespace App\Policies\Test;

use App\Models\Test\Test;
use App\Models\User;
use App\Policies\Concerns\AuthorizesContent;
use Illuminate\Auth\Access\Response;

class TestPolicy
{
    use AuthorizesContent;

    public function view(?User $user, Test $test): Response
    {
        return $this->canView($user, $test) ? Response::allow() : Response::deny('Тест недоступен');
    }

    // Свой тест может завести любой пользователь
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Test $test): bool
    {
        return $this->canManage($user, $test);
    }

    public function delete(User $user, Test $test): bool
    {
        return $this->canManage($user, $test);
    }
}
