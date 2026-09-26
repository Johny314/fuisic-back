<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Типы вопросов (fuisic-back#60): ответ из `tasks.answer` переезжает в `settings`.
 * Число в каноничной записи («60», «-1.25») → `number` без допуска, остальное → `text` с одним ответом.
 * Неканоничные числа («0,5», «0.50», «007») остаются текстом, чтобы down() вернул их байт в байт.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->after('test_id');
            $table->text('problem_statement')->nullable()->change();
            $table->string('image_path')->nullable()->after('problem_statement');
            $table->unsignedSmallInteger('points')->default(1)->after('image_path');
            $table->text('explanation')->nullable()->after('points');
            $table->boolean('shuffle_options')->default(false)->after('explanation');
            $table->jsonb('settings')->nullable()->after('shuffle_options');
        });

        Schema::create('task_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        DB::table('tasks')->orderBy('id')->select(['id', 'answer'])->chunkById(500, function ($tasks) {
            foreach ($tasks as $task) {
                DB::table('tasks')->where('id', $task->id)->update($this->convert($task->answer));
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('answer');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('answer')->nullable()->after('problem_statement');
        });

        DB::table('tasks')->orderBy('id')->select(['id', 'type', 'settings'])->chunkById(500, function ($tasks) {
            foreach ($tasks as $task) {
                DB::table('tasks')->where('id', $task->id)->update(['answer' => $this->legacyAnswer($task)]);
            }
        });

        // новые вопросы могут быть длиннее старого varchar(255)
        DB::table('tasks')->whereRaw('char_length(problem_statement) > 255')
            ->update(['problem_statement' => DB::raw('left(problem_statement, 255)')]);

        Schema::dropIfExists('task_options');

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('problem_statement')->nullable()->change();
            $table->dropColumn(['type', 'image_path', 'points', 'explanation', 'shuffle_options', 'settings']);
        });
    }

    private function convert(?string $answer): array
    {
        $value = $answer !== null && strlen($answer) <= 15 && preg_match('/^-?\d+(\.\d+)?$/', $answer)
            ? (str_contains($answer, '.') ? (float) $answer : (int) $answer)
            : null;

        if ($value !== null && $this->formatNumber($value) === $answer) {
            return [
                'type' => 'number',
                'settings' => json_encode(['value' => $value, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]),
            ];
        }

        return [
            'type' => 'text',
            'settings' => json_encode(['answers' => $answer === null ? [] : [$answer]]),
        ];
    }

    private function legacyAnswer(object $task): ?string
    {
        $settings = json_decode((string) $task->settings, true) ?: [];

        $answer = match ($task->type) {
            'text' => $settings['answers'][0] ?? null,
            'number' => isset($settings['value']) ? $this->formatNumber($settings['value']) : null,
            default => DB::table('task_options')->where('task_id', $task->id)->where('is_correct', true)
                ->orderBy('position')->orderBy('id')->pluck('text')->filter()->implode('; ') ?: null,
        };

        return $answer === null ? null : mb_substr($answer, 0, 255);
    }

    private function formatNumber(int|float $value): string
    {
        return is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value;
    }
};
