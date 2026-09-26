<?php

namespace App\Models\Test;

use App\Enums\TaskType;
use App\Models\Concerns\AuditsAdminChanges;
use App\Services\MediaStorage;
use App\Support\Tasks\QuestionType;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Вопрос теста. Настройки проверки — `settings` (схема зависит от типа, см. App\Support\Tasks),
 * варианты ответа — `options`. Наружу правильные ответы отдаёт только DTO редактора.
 */
class Task extends Model
{
    use AuditsAdminChanges;
    use CrudTrait;
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected $fillable = [
        'test_id',
        'type',
        'problem_statement',
        'image_path',
        'points',
        'explanation',
        'shuffle_options',
        'settings',
    ];

    protected $attributes = [
        'type' => 'text',
        'points' => 1,
        'shuffle_options' => false,
    ];

    // правильные ответы не должны попасть наружу через toArray()
    protected $hidden = [
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'points' => 'integer',
            'shuffle_options' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(TaskOption::class)->orderBy('position')->orderBy('id');
    }

    public function definition(): QuestionType
    {
        return ($this->type ?? TaskType::text)->definition();
    }

    /** Правильный ответ одной строкой (для клиентов до fuisic-front#30). */
    protected function answer(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->definition()->answer($this));
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => app(MediaStorage::class)->url($this->image_path));
    }
}
