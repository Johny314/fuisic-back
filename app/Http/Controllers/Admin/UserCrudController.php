<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Admin\Concerns\AuthorizesCrud;
use App\Http\Controllers\Admin\Operations\BlockOperation;
use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\AuditLog;
use App\Support\RoleCatalog;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Arr;

/**
 * Class UserCrudController
 */
class UserCrudController extends CrudController
{
    use AuthorizesCrud;
    use BlockOperation;
    use CreateOperation {
        store as traitStore;
    }
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as traitUpdate;
    }

    public function setup()
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/user');
        CRUD::setEntityNameStrings('Пользователь', 'Пользователи');

        $this->authorizeCrud(PermissionName::usersView);
    }

    protected function setupListOperation()
    {
        CRUD::addClause('with', 'roles');

        CRUD::column('name')->label('Имя');
        CRUD::column('email')->label('Email');
        CRUD::column('username')->label('Логин');
        $this->addRolesColumn();
        $this->addBlockStatusColumn();
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(UserRequest::class);
        // роли сохраняет store()/update() через spatie, а не Backpack
        CRUD::setOperationSetting('strippedRequest', fn ($request) => Arr::except(
            $request->only(CRUD::getAllFieldNames()), [UserRequest::ROLES]
        ));

        CRUD::field('name')->label('Имя')->type('text');
        CRUD::field('email')->label('Email')->type('email')
            ->hint('Необязателен для аккаунта ребёнка с логином.');
        CRUD::field('password')->label('Пароль')->type('password');

        // user_type не редактируется: он выводится из ролей (User::syncRolesWithUserType)
        if (self::managesRoles()) {
            $student = Role::findByName(RoleName::student->value, RoleCatalog::GUARD);

            CRUD::addField([
                'name' => UserRequest::ROLES,
                'label' => 'Роли',
                'type' => 'checkbox_list',
                'options' => UserRequest::assignableRoles(),
                'default' => [$student->id],
                'number_of_columns' => 3,
            ]);
        }
    }

    protected function setupShowOperation()
    {
        CRUD::column('name')->label('Имя');
        CRUD::column('email')->label('Email');
        CRUD::column('username')->label('Логин');
        $this->addRolesColumn();
        $this->addBlockStatusColumn();

        CRUD::addColumn([
            'name' => 'block_history',
            'label' => 'История блокировок',
            'type' => 'custom_html',
            'value' => fn (User $entry) => $entry->blocks()->with(['blockedBy', 'unblockedBy'])->latest('id')->get()
                ->map(fn (UserBlock $block) => e(sprintf(
                    '%s — %s (%s)%s%s%s',
                    $block->created_at->format('d.m.Y H:i'),
                    $block->reason,
                    $block->blockedBy?->name ?? '—',
                    $block->until ? ', до '.$block->until->format('d.m.Y H:i') : ', бессрочно',
                    $block->unblocked_at ? ', снята '.$block->unblocked_at->format('d.m.Y H:i').($block->unblockedBy ? ' ('.$block->unblockedBy->name.')' : '') : '',
                    $block->comment ? '. Комментарий: '.$block->comment : '',
                )))
                ->implode('<br>') ?: '—',
        ]);
    }

    private function addRolesColumn(): void
    {
        CRUD::addColumn([
            'name' => 'role_labels',
            'label' => 'Роли',
            'type' => 'closure',
            'function' => fn (User $entry) => $entry->roles->map(fn (Role $role) => $role->displayName())->implode(', ') ?: '—',
            'searchLogic' => false,
            'orderable' => false,
        ]);
    }

    private function addBlockStatusColumn(): void
    {
        CRUD::addColumn([
            'name' => 'block_status',
            'label' => 'Блокировка',
            'type' => 'text',
            'value' => function (User $entry) {
                $block = $entry->activeBlock();

                if (! $block) {
                    return '—';
                }

                return ($block->until ? 'до '.$block->until->format('d.m.Y H:i') : 'бессрочно').': '.$block->reason;
            },
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
        CRUD::field('password')->hint('Оставьте пустым, чтобы не менять пароль');

        $user = $this->crud->getCurrentEntry();

        if ($user instanceof User && self::managesRoles()) {
            CRUD::field(UserRequest::ROLES)->value($user->roles()->pluck('id')->all());
        }
    }

    public function store()
    {
        $response = $this->traitStore();
        $this->saveRoles($this->crud->entry);

        return $response;
    }

    public function update()
    {
        // пустое поле пароля не должно затирать текущий
        if (blank($this->crud->getRequest()->input('password'))) {
            $this->crud->getRequest()->request->remove('password');
        }

        $response = $this->traitUpdate();
        $this->saveRoles($this->crud->entry);

        return $response;
    }

    /** Назначать роли может только roles.manage (UserRequest запрещает поле остальным). */
    private static function managesRoles(): bool
    {
        return (bool) backpack_user()?->can(PermissionName::rolesManage->value);
    }

    private function saveRoles(User $user): void
    {
        $request = $this->crud->getRequest();

        if (! self::managesRoles() || ! $request->has(UserRequest::ROLES)) {
            return;
        }

        $before = $user->roles()->pluck('name');
        $user->syncRolesWithUserType(Role::query()
            ->where('guard_name', RoleCatalog::GUARD)
            ->whereKey((array) $request->input(UserRequest::ROLES))
            ->get());

        app(AuditLog::class)->recordSetChange(AuditEvent::userRoles, $user, backpack_user(),
            'roles', $before, $user->roles()->pluck('name'));
    }
}
