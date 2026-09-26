<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Блокировка пользователя. reason видна пользователю, comment — только персоналу;
 * until = null — бессрочно. Снятые и истёкшие записи остаются историей.
 */
class UserBlock extends Model
{
    protected $fillable = [
        'user_id',
        'blocked_by_id',
        'reason',
        'comment',
        'until',
        'unblocked_at',
        'unblocked_by_id',
    ];

    protected function casts(): array
    {
        return [
            'until' => 'datetime',
            'unblocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_id')->withTrashed();
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by_id')->withTrashed();
    }

    /** Действует сейчас: не снята и срок не истёк (истёкшая не действует и до запуска планировщика). */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unblocked_at')
            ->where(fn (Builder $until) => $until->whereNull('until')->orWhere('until', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->unblocked_at === null && ($this->until === null || $this->until->isFuture());
    }
}
