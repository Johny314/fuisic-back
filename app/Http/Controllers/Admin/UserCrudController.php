<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Admin\Operations\BlockOperation;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\UserBlock;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class UserCrudController
 */
class UserCrudController extends CrudController
{
    use BlockOperation;
    use CreateOperation;
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
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Имя');
        CRUD::column('email')->label('Email');

        CRUD::addColumn([
            'name' => 'user_type',
            'label' => 'Тип пользователя',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->user_type->name ?? '-';
            },
        ]);

        $this->addBlockStatusColumn();
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(UserRequest::class);

        CRUD::field('name')->label('Имя')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('password')->label('Пароль')->type('password');

        CRUD::addField([
            'name' => 'user_type',
            'label' => 'Тип пользователя',
            'type' => 'select_from_array',
            'options' => collect(UserType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])->toArray(),
            'allows_null' => false,
            'default' => UserType::student->value,
        ]);
    }

    protected function setupShowOperation()
    {
        CRUD::column('name')->label('Имя');
        CRUD::column('email')->label('Email');

        CRUD::addColumn([
            'name' => 'user_type',
            'label' => 'Тип пользователя',
            'type' => 'text',
            'value' => function ($entry) {
                return $entry->user_type->name ?? '-';
            },
        ]);

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
    }

    public function update()
    {
        // пустое поле пароля не должно затирать текущий
        if (blank($this->crud->getRequest()->input('password'))) {
            $this->crud->getRequest()->request->remove('password');
        }

        return $this->traitUpdate();
    }
}
