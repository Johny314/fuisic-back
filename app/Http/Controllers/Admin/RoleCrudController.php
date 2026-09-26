<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Admin\Concerns\AuthorizesCrud;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Support\RoleCatalog;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Роли и их права (roles.manage). Стартовые роли не удаляются и не переименовываются;
 * admin прав не хранит — он суперадмин через Gate::before.
 */
class RoleCrudController extends CrudController
{
    use AuthorizesCrud;
    use CreateOperation {
        store as traitStore;
    }
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation {
        update as traitUpdate;
    }

    private const string PERMISSIONS = 'permission_ids';

    public function setup()
    {
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/role');
        CRUD::setEntityNameStrings('роль', 'Роли');
        CRUD::addBaseClause('where', 'guard_name', RoleCatalog::GUARD);

        $this->authorizeCrud(PermissionName::rolesManage);
        // admin проходит политики через Gate::before, поэтому запрет на удаление стартовых — здесь
        $delete = CRUD::getAccessCondition('delete');
        CRUD::setAccessCondition('delete', fn ($entry = null): bool => $delete($entry)
            && ! ($entry instanceof Role && $entry->isStarter()));
    }

    protected function setupListOperation()
    {
        CRUD::addClause('with', 'permissions');
        CRUD::orderBy('id');

        CRUD::column('name')->label('Название');
        CRUD::addColumn([
            'name' => 'display_name',
            'label' => 'Роль',
            'type' => 'closure',
            'function' => fn (Role $entry) => $entry->displayName(),
            'searchLogic' => false,
            'orderable' => false,
        ]);
        CRUD::addColumn([
            'name' => 'permission_labels',
            'label' => 'Права',
            'type' => 'closure',
            'function' => fn (Role $entry) => $entry->isSuperAdmin()
                ? 'все (суперадмин)'
                : ($entry->permissions->map(fn (Permission $p) => PermissionName::labelFor($p->name))->sort()->implode(', ') ?: '—'),
            'searchLogic' => false,
            'orderable' => false,
            'limit' => 500,
        ]);
        CRUD::addColumn([
            'name' => 'users_count',
            'label' => 'Пользователей',
            'type' => 'closure',
            'function' => fn (Role $entry) => $entry->users()->count(),
            'default' => '0',
            'searchLogic' => false,
            'orderable' => false,
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(RoleRequest::class);
        // права сохраняет syncPermissions(); guard — явно, иначе spatie возьмёт guard админки (backpack)
        CRUD::setOperationSetting('strippedRequest', fn ($request) => [
            ...Arr::except($request->only(CRUD::getAllFieldNames()), [self::PERMISSIONS, 'super_admin_note']),
            'guard_name' => RoleCatalog::GUARD,
        ]);

        CRUD::field('name')->label('Название')->type('text')
            ->hint('Идентификатор роли в API: латиница в нижнем регистре, цифры, «_» и «-».');
        $this->addPermissionsField();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        $role = $this->crud->getCurrentEntry();

        if (! $role instanceof Role) {
            return;
        }

        if ($role->isStarter()) {
            CRUD::field('name')->attributes(['readonly' => 'readonly'])
                ->hint('Стартовая роль «'.$role->displayName().'»: название не меняется, роль нельзя удалить.');
        }

        if ($role->isSuperAdmin()) {
            CRUD::removeField(self::PERMISSIONS);
            CRUD::addField([
                'name' => 'super_admin_note',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-info mb-0">Администратор — суперадмин: ему доступно всё, '
                    .'набор прав не хранится и не настраивается.</div>',
            ]);

            return;
        }

        CRUD::field(self::PERMISSIONS)->value($role->permissions()->pluck('id')->all());
    }

    public function store()
    {
        $response = $this->traitStore();
        $this->syncPermissions($this->crud->entry);

        return $response;
    }

    public function update()
    {
        $response = $this->traitUpdate();
        $this->syncPermissions($this->crud->entry);

        return $response;
    }

    private function addPermissionsField(): void
    {
        CRUD::addField([
            'name' => self::PERMISSIONS,
            'label' => 'Права',
            'type' => 'checkbox_list',
            'options' => Permission::query()
                ->where('guard_name', RoleCatalog::GUARD)
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (Permission $p) => [$p->id => PermissionName::labelFor($p->name).' ('.$p->name.')'])
                ->all(),
            'default' => [],
        ]);
    }

    private function syncPermissions(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            return;
        }

        $ids = array_filter((array) $this->crud->getRequest()->input(self::PERMISSIONS, []));
        $role->syncPermissions(Permission::query()->where('guard_name', RoleCatalog::GUARD)->whereKey($ids)->get());
        // изменения прав сразу действуют в API
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
