<?php

namespace App\Models;

use App\Enums\TeacherVerificationStatus;
use App\Enums\WorkplaceType;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка на статус «Проверенный учитель». Поля решения (reviewer_*, reviewed_at)
 * отражают последнее решение: одобрение, отказ или отзыв.
 */
class TeacherVerification extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'full_name',
        'workplace_type',
        'workplace_name',
        'subjects',
        'link',
        'comment',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'workplace_type' => WorkplaceType::class,
            'subjects' => 'array',
            'status' => TeacherVerificationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** Статус для GET /me — без данных заявки и проверяющего. */
    public function statusSummary(): array
    {
        return [
            'status' => $this->status->value,
            'reviewer_comment' => $this->reviewer_comment,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
