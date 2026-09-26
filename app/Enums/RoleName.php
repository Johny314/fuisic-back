<?php

namespace App\Enums;

/**
 * Стартовые роли. Состав прав задаётся здесь только при первом создании роли,
 * дальше его меняют в админке.
 */
enum RoleName: string
{
    case admin = 'admin';
    case moderator = 'moderator';
    case teacher = 'teacher';
    case student = 'student';
    case parent = 'parent';

    /** Роли, доступные при регистрации. */
    public const array REGISTRABLE = [self::student, self::teacher, self::parent];

    /**
     * Права роли по умолчанию. Admin прав не хранит — он суперадмин через Gate::before.
     *
     * @return list<PermissionName>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::admin, self::teacher, self::student => [],
            self::moderator => [
                PermissionName::adminAccess,
                PermissionName::catalogManage,
                PermissionName::catalogReview,
                PermissionName::formulasManage,
                PermissionName::usersView,
                PermissionName::usersBlock,
            ],
            self::parent => [
                PermissionName::childrenManage,
                PermissionName::childrenView,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::admin => 'Администратор',
            self::moderator => 'Модератор',
            self::teacher => 'Учитель',
            self::student => 'Ученик',
            self::parent => 'Родитель',
        };
    }

    /** Подпись роли по имени; свои роли из админки — как есть. */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
