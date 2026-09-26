<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Стартовые роли и права. Идемпотентно: недостающее создаётся, а права уже
 * существующих ролей не трогаются — их настраивают в админке.
 */
class RoleCatalog
{
    public const string GUARD = 'web';

    public static function install(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, self::GUARD);
        }

        foreach (RoleName::cases() as $name) {
            $role = Role::query()->firstOrCreate(['name' => $name->value, 'guard_name' => self::GUARD]);

            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo(array_map(fn (PermissionName $p) => $p->value, $name->defaultPermissions()));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
