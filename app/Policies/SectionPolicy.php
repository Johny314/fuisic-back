<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Section;
use App\Models\User;

/**
 * Разделы видят все; меняет тот, кто ведёт каталог.
 */
class SectionPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::catalogManage->value);
    }

    public function update(User $user, Section $section): bool
    {
        return $user->can(PermissionName::catalogManage->value);
    }

    public function delete(User $user, Section $section): bool
    {
        return $user->can(PermissionName::catalogManage->value);
    }
}
