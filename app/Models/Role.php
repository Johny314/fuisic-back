<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Models\Concerns\AuditsAdminChanges;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Роль spatie с CRUD Backpack (config permission.models.role).
 */
class Role extends SpatieRole
{
    use AuditsAdminChanges;
    use CrudTrait;

    /** Стартовая роль (RoleName): не удаляется и не переименовывается. */
    public function isStarter(): bool
    {
        return RoleName::tryFrom($this->name) !== null;
    }

    /** Admin — суперадмин через Gate::before, набор прав не хранит. */
    public function isSuperAdmin(): bool
    {
        return $this->name === RoleName::admin->value;
    }

    /** Русская подпись стартовой роли, своя роль — по названию. */
    public function displayName(): string
    {
        return RoleName::labelFor($this->name);
    }
}
