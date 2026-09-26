<?php

use App\Enums\UserType;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    // Роли и права нужны сразу после деплоя, поэтому создаются миграцией, а не только сидером
    public function up(): void
    {
        RoleCatalog::install();

        $morphType = (new User)->getMorphClass();
        $roleIds = DB::table('roles')->where('guard_name', RoleCatalog::GUARD)->pluck('id', 'name');

        foreach (UserType::cases() as $type) {
            DB::table('users')
                ->where('user_type', $type->value)
                ->whereNotExists(fn ($query) => $query->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', $morphType))
                ->orderBy('id')
                ->chunkById(500, function ($users) use ($roleIds, $type, $morphType) {
                    DB::table('model_has_roles')->insert($users->map(fn ($user) => [
                        'role_id' => $roleIds[$type->value],
                        'model_type' => $morphType,
                        'model_id' => $user->id,
                    ])->all());
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->delete();
    }
};
