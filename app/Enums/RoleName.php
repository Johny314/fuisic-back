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

    /** Значение устаревшего `users.user_type` (удаляется в fuisic-back#29). */
    public function legacyUserType(): UserType
    {
        return match ($this) {
            self::admin => UserType::admin,
            self::teacher => UserType::teacher,
            default => UserType::student,
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

    /**
     * `user_type` по набору ролей: admin, иначе teacher, иначе student.
     *
     * @param  iterable<string>  $roles
     */
    public static function legacyUserTypeFor(iterable $roles): UserType
    {
        $roles = collect($roles);

        foreach ([self::admin, self::teacher] as $main) {
            if ($roles->contains($main->value)) {
                return $main->legacyUserType();
            }
        }

        return UserType::student;
    }

    public static function fromUserType(UserType $type): self
    {
        return self::from($type->value);
    }
}
