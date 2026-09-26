<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\PermissionName;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Доступ к CRUD Backpack: раздел — по праву, операции над записью — по политике модели.
 */
trait AuthorizesCrud
{
    protected function authorizeCrud(PermissionName $permission): void
    {
        $model = $this->crud->getModel()::class;
        $section = fn (): bool => (bool) backpack_user()?->can($permission->value);
        // без записи (кнопки в шапке списка) — только право на раздел
        $entry = fn (string $ability) => fn ($entry = null): bool => $section()
            && (! $entry || backpack_user()->can($ability, $entry));

        CRUD::setAccessCondition('list', $section);
        CRUD::setAccessCondition('create', fn (): bool => $section() && backpack_user()->can('create', $model));
        CRUD::setAccessCondition('show', $entry('view'));
        CRUD::setAccessCondition('update', $entry('update'));
        CRUD::setAccessCondition('delete', $entry('delete'));
    }
}
