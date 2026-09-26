<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Мои повторения»: наборы, из которых пользователь получает очередь. Прогресс по карточкам
        // хранится отдельно (card_review_states) и при удалении набора из повторений не теряется.
        Schema::create('card_set_repetitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_set_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'card_set_id']);
            $table->index('card_set_id');
        });

        // Клиентский id оценки (UUID): повторная отправка при обрыве связи не создаёт вторую оценку
        Schema::table('card_review_logs', function (Blueprint $table) {
            $table->uuid('review_id')->nullable()->after('card_id');

            $table->unique(['user_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::table('card_review_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'review_id']);
            $table->dropColumn('review_id');
        });

        Schema::dropIfExists('card_set_repetitions');
    }
};
