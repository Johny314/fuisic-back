<?php

namespace App\Enums;

/**
 * Стартовый набор прав; какие права у какой роли — редактируется в админке.
 */
enum PermissionName: string
{
    case adminAccess = 'admin.access';
    case catalogManage = 'catalog.manage';
    case catalogReview = 'catalog.review';
    case catalogSubmit = 'catalog.submit';
    case formulasManage = 'formulas.manage';
    case usersView = 'users.view';
    case usersBlock = 'users.block';
    case usersManage = 'users.manage';
    case rolesManage = 'roles.manage';
    case teachersVerify = 'teachers.verify';
    case auditView = 'audit.view';
    case childrenManage = 'children.manage';
    case childrenView = 'children.view';

    /** Подпись для админки. */
    public function label(): string
    {
        return match ($this) {
            self::adminAccess => 'Вход в админку',
            self::catalogManage => 'Управление каталогом и разделами',
            self::catalogReview => 'Проверка публикаций в каталог',
            self::catalogSubmit => 'Публикация в каталог',
            self::formulasManage => 'Справочник формул',
            self::usersView => 'Просмотр пользователей',
            self::usersBlock => 'Блокировка пользователей',
            self::usersManage => 'Управление пользователями',
            self::rolesManage => 'Управление ролями и их назначение',
            self::teachersVerify => 'Проверка заявок учителей',
            self::auditView => 'Просмотр журнала действий',
            self::childrenManage => 'Управление аккаунтами детей',
            self::childrenView => 'Просмотр аккаунтов детей',
        };
    }

    /** Подпись права по имени; неизвестное (не из стартового набора) — как есть. */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
