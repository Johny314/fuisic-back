<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // История блокировок: действующая — последняя неснятая с неистёкшим сроком
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->text('comment')->nullable();
            $table->timestamp('until')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'unblocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
