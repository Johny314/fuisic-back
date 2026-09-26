<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Роли spatie — единственный источник правды (fuisic-back#29)
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }

    // Колонка восстанавливается по ролям: admin, иначе teacher, иначе student
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('student');
        });

        $morphType = (new User)->getMorphClass();

        foreach (['teacher', 'admin'] as $role) {
            DB::table('users')
                ->whereExists(fn (Builder $query) => $query->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', $morphType)
                    ->where('roles.name', $role))
                ->update(['user_type' => $role]);
        }
    }
};
