<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Requests\UserRequest;
use App\Models\User;
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
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

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
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
