<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // логин для входа без email (аккаунт ребёнка); хранится в нижнем регистре
            $table->string('username', 32)->nullable()->unique()->after('email');
            $table->unsignedTinyInteger('grade')->nullable()->after('username');
            // родитель, создавший аккаунт ребёнка
            $table->foreignId('created_by_id')->nullable()->after('grade')->constrained('users')->nullOnDelete();
        });

        Schema::create('parent_child', function (Blueprint $table) {
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['parent_id', 'child_id']);
            $table->index('child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_child');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropColumn(['username', 'grade']);
        });
    }
};
