<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Services\AuditLog;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Запись журнала действий (config activitylog.activity_model): IP автора и подписи для админки.
 * attribute_changes = {attributes: стало, old: было}.
 */
class Activity extends SpatieActivity
{
    use CrudTrait;

    protected static function booted(): void
    {
        // в консоли (планировщик) IP нет
        static::creating(function (Activity $activity) {
            $activity->ip_address ??= app()->bound('request') ? request()->ip() : null;
        });
    }

    /** Автор, в том числе удалённый. */
    public function causer(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function eventLabel(): string
    {
        return AuditEvent::labelFor($this->event);
    }

    public function causerLabel(): string
    {
        if ($this->causer_id === null) {
            return 'система';
        }

        $causer = $this->causer;

        return $causer instanceof User
            ? $causer->name.' ('.($causer->email ?? $causer->username ?? '#'.$causer->id).')'
            : 'пользователь #'.$this->causer_id;
    }

    public function subjectLabel(): string
    {
        if ($this->subject_type === null) {
            return '—';
        }

        $name = $this->subject?->getAttribute('name')
            ?? $this->subject?->getAttribute('full_name')
            ?? data_get($this->attribute_changes, 'old.name');

        return AuditLog::subjectLabel($this->subject_type).' #'.$this->subject_id.($name ? ' «'.$name.'»' : '');
    }

    /**
     * Строки «поле: было → стало».
     *
     * @return Collection<int, array{field: string, old: string, new: string}>
     */
    public function changeRows(): Collection
    {
        $new = (array) ($this->attribute_changes?->get('attributes') ?? []);
        $old = (array) ($this->attribute_changes?->get('old') ?? []);

        return collect(array_keys($old + $new))->map(fn (string $field) => [
            'field' => $field,
            'old' => array_key_exists($field, $old) ? self::formatValue($old[$field]) : '',
            'new' => array_key_exists($field, $new) ? self::formatValue($new[$field]) : '',
        ]);
    }

    private static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'да' : 'нет',
            is_array($value) => array_is_list($value)
                ? (implode(', ', array_map(self::formatValue(...), $value)) ?: '—')
                : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
