<?php

namespace App\Models\Test;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Вариант ответа вопроса `single` / `multiple`.
 */
class TaskOption extends Model
{
    protected $fillable = [
        'task_id',
        'text',
        'image_path',
        'is_correct',
        'position',
    ];

    protected $attributes = [
        'is_correct' => false,
        'position' => 0,
    ];

    // признак верности не должен попасть наружу через toArray()
    protected $hidden = [
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => app(MediaStorage::class)->url($this->image_path));
    }
}
