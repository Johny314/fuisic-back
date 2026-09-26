<?php

namespace App\Enums;

/**
 * Действие в журнале (activity_log.event). created/updated/deleted пишет
 * AuditsAdminChanges для записей админки, остальное — App\Services\AuditLog.
 */
enum AuditEvent: string
{
    case created = 'created';
    case updated = 'updated';
    case deleted = 'deleted';
    case rolePermissions = 'role_permissions';
    case userRoles = 'user_roles';
    case blocked = 'blocked';
    case unblocked = 'unblocked';
    case teacherApproved = 'teacher_approved';
    case teacherRejected = 'teacher_rejected';
    case teacherRevoked = 'teacher_revoked';

    public function label(): string
    {
        return match ($this) {
            self::created => 'Создание',
            self::updated => 'Изменение',
            self::deleted => 'Удаление',
            self::rolePermissions => 'Права роли',
            self::userRoles => 'Назначение ролей',
            self::blocked => 'Блокировка',
            self::unblocked => 'Разблокировка',
            self::teacherApproved => 'Заявка учителя одобрена',
            self::teacherRejected => 'Заявка учителя отклонена',
            self::teacherRevoked => 'Статус учителя отозван',
        };
    }

    public static function labelFor(?string $event): string
    {
        return $event === null ? '—' : (self::tryFrom($event)?->label() ?? $event);
    }
}
