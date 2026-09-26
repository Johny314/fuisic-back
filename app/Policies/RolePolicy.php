<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;

/**
 * Роли и их права — roles.manage. Стартовые роли не удаляет никто, даже admin:
 * это проверяет ещё и RoleCrudController, потому что admin проходит политики через Gate::before.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::rolesManage->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->viewAny($user) && ! $role->isStarter();
    }
}
