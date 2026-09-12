<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('avatar');
        });

        Schema::table('card_sets', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('user_id');
        });

        $adminId = DB::table('users')->where('email', 'admin@fuisic.local')->value('id');
        $teacherId = DB::table('users')->where('email', 'teacher@fuisic.local')->value('id');

        if ($adminId && $teacherId) {
            DB::table('card_sets')->where('user_id', $teacherId)->update(['user_id' => $adminId]);
            DB::table('tests')->where('user_id', $teacherId)->update(['user_id' => $adminId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('card_sets', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
