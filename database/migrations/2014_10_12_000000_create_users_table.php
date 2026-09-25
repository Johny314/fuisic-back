<?php

use App\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('user_type')->default(UserType::student->value);
            $table->string('provider')->nullable();
            $table->string('provider_id')->unique()->nullable();
            $table->string('avatar')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
