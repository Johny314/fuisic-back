<?php

namespace App\Policies\Card;

use App\Models\Card\CardSet;
use App\Models\User;
use App\Policies\Concerns\AuthorizesContent;
use Illuminate\Auth\Access\Response;

class CardSetPolicy
{
    use AuthorizesContent;

    public function view(?User $user, CardSet $set): Response
    {
        return $this->canView($user, $set) ? Response::allow() : Response::deny('Набор недоступен');
    }

    // Свой набор может завести любой пользователь
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CardSet $set): bool
    {
        return $this->canManage($user, $set);
    }

    public function delete(User $user, CardSet $set): bool
    {
        return $this->canManage($user, $set);
    }
}
