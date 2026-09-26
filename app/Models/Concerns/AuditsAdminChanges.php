<?php

namespace App\Models\Concerns;

use App\Services\AuditLog;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Создание / изменение / удаление записи — в журнал с разницей полей, но только
 * из админки (AuditLog::asStaff). Секреты (activitylog.default_except_attributes)
 * не пишутся: их изменение отмечается как AuditLog::HIDDEN.
 */
trait AuditsAdminChanges
{
    use LogsActivity {
        LogsActivity::shouldLogEvent as activitylogShouldLogEvent;
        LogsActivity::buildChanges as activitylogBuildChanges;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(AuditLog::LOG_NAME)
            ->logAll()
            // объект записи и так указан в журнале, метки времени — служебные
            ->logExcept(['id', 'created_at', 'updated_at', 'deleted_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return app(AuditLog::class)->recordsModelChanges() && $this->activitylogShouldLogEvent($eventName);
    }

    protected function buildChanges(string $processingEvent): array
    {
        $changes = $this->activitylogBuildChanges($processingEvent);

        foreach ((array) config('activitylog.default_except_attributes', []) as $secret) {
            $changed = match ($processingEvent) {
                'created' => $this->getAttributeFromArray($secret) !== null,
                'updated' => $this->wasChanged($secret),
                default => false,
            };

            if ($changed) {
                $changes['attributes'][$secret] = AuditLog::HIDDEN;

                if ($processingEvent === 'updated') {
                    $changes['old'][$secret] = AuditLog::HIDDEN;
                }
            }
        }

        return $changes;
    }
}
