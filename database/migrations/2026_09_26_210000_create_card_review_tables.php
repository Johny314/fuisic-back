<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Состояние FSRS по паре «пользователь — карточка». Мягко удалённая карточка остаётся здесь,
        // из очереди её убирает выборка; строки уходят только с полным удалением пользователя или карточки.
        Schema::create('card_review_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('state')->default(0);
            $table->unsignedSmallInteger('step')->nullable();
            $table->double('stability')->nullable();
            $table->double('difficulty')->nullable();
            $table->timestamp('due')->nullable();
            $table->timestamp('last_review_at')->nullable();
            $table->unsignedSmallInteger('last_rating')->nullable();
            $table->unsignedInteger('reps')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->unsignedInteger('elapsed_days')->default(0);
            $table->unsignedInteger('scheduled_days')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'card_id']);
            $table->index(['user_id', 'due']);
            $table->index('card_id');
        });

        // Журнал оценок: основа статистики и будущей подстройки весов FSRS
        Schema::create('card_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->timestamp('reviewed_at');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('state_before');
            $table->unsignedSmallInteger('state_after');
            $table->double('stability_before')->nullable();
            $table->double('stability_after');
            $table->double('difficulty_before')->nullable();
            $table->double('difficulty_after');
            $table->timestamp('due_before')->nullable();
            $table->timestamp('due_after');
            $table->unsignedInteger('elapsed_days');
            $table->unsignedInteger('scheduled_days');
            $table->unsignedBigInteger('interval_seconds');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'reviewed_at']);
            $table->index(['card_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_review_logs');
        Schema::dropIfExists('card_review_states');
    }
};
