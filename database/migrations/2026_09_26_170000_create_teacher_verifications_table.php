<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('workplace_type');
            $table->string('workplace_name');
            $table->json('subjects');
            $table->string('link', 2048)->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        // Не больше одной заявки на рассмотрении у пользователя — даже при гонке запросов
        DB::statement("CREATE UNIQUE INDEX teacher_verifications_one_pending ON teacher_verifications (user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_verifications');
    }
};
