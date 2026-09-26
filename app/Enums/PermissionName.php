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
}
