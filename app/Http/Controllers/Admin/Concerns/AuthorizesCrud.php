<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\PermissionName;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Gate;

/**
 * Доступ к CRUD Backpack: раздел — по праву, операции над записью — по политике модели.
 * Модель без политики — только право на раздел. Прямой URL без права — 403.
 */
trait AuthorizesCrud
{
    /**
     * @param  list<string>  $sectionOperations  свои операции контроллера, которым хватает права на раздел
     */
    protected function authorizeCrud(PermissionName $permission, array $sectionOperations = []): void
    {
        $model = $this->crud->getModel()::class;
        $hasPolicy = Gate::getPolicyFor($model) !== null;
        $section = fn (): bool => (bool) backpack_user()?->can($permission->value);
        $allows = fn (string $ability, $subject): bool => ! $hasPolicy || backpack_user()->can($ability, $subject);
        // без записи (кнопки в шапке списка) — только право на раздел
        $entry = fn (string $ability) => fn ($entry = null): bool => $section()
            && (! $entry || $allows($ability, $entry));

        CRUD::setAccessCondition(['list', ...$sectionOperations], $section);
        CRUD::setAccessCondition('create', fn (): bool => $section() && $allows('create', $model));
        CRUD::setAccessCondition('show', $entry('view'));
        CRUD::setAccessCondition('update', $entry('update'));
        CRUD::setAccessCondition('delete', $entry('delete'));
    }
}
