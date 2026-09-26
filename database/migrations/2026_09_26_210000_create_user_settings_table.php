<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Строка создаётся при первом сохранении; до этого действуют значения по умолчанию из модели
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // IANA; null — устройство ещё не передало свой, считаем UTC
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('new_cards_per_day')->default(20);
            // час по часовому поясу пользователя
            $table->unsignedTinyInteger('reminder_hour')->default(19);
            $table->boolean('email_reminders')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
