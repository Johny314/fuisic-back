<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * Список и карточки пользователей — users.view, изменение — users.manage.
 * Администраторов меняет только admin (через Gate::before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([PermissionName::usersView->value, PermissionName::usersManage->value]);
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::usersManage->value);
    }

    // Свой профиль правит каждый
    public function update(User $user, User $target): bool
    {
        return (int) $target->id === (int) $user->id || $this->manages($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->manages($user, $target);
    }

    private function manages(User $user, User $target): bool
    {
        return $user->can(PermissionName::usersManage->value) && ! $target->isAdmin();
    }
}
