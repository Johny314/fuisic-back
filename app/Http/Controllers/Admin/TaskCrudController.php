<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Admin\Concerns\AuthorizesCrud;
use App\Http\Requests\TaskRequest;
use App\Models\Test\Task;
use App\Models\Test\TaskOption;
use App\Models\Test\Test;
use App\Support\ContentAccess;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Вопросы тестов: просмотр, правка общих полей, удаление. Тип, настройки проверки и варианты
 * меняются через API (студия), здесь — только просмотр: у каждого типа своя схема и валидация.
 *
 * @property-read CrudPanel $crud
 */
class TaskCrudController extends CrudController
{
    use AuthorizesCrud;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Task::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/task');
        CRUD::setEntityNameStrings('Вопрос', 'Вопросы тестов');

        $this->authorizeCrud(PermissionName::catalogManage);
        if ($user = backpack_user()) {
            CRUD::addBaseClause(fn ($query) => $query->whereHas('test', fn ($test) => ContentAccess::applyEditableScope($test, $user)));
        }
        if ($testId = (int) request()->query('test_id')) {
            CRUD::addClause('where', 'test_id', $testId);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('test_id')->type('select')->label('Тест')->entity('test')->attribute('name')->model(Test::class);
        $this->typeColumn();
        CRUD::column('problem_statement')->label('Условие')->type('text')->limit(80);
        CRUD::column('points')->label('Баллы')->type('number');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('problem_statement')->label('Условие')->type('textarea')->limit(10000);
        CRUD::column('image_path')->label('Картинка')->type('text');
        CRUD::column('explanation')->label('Разбор')->type('textarea')->limit(10000);
        CRUD::column('shuffle_options')->label('Перемешивать варианты')->type('boolean');
        CRUD::column('settings')->label('Настройки проверки')->type('closure')
            ->function(fn (Task $task) => $task->settings === null ? null : json_encode($task->settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        CRUD::column('options')->label('Варианты')->type('closure')->escaped(false)
            ->function(fn (Task $task) => $task->options->map(fn (TaskOption $option) => ($option->is_correct ? '✔ ' : '✘ ')
                .e($option->text ?? '').($option->image_path ? ' ['.e($option->image_path).']' : ''))->implode('<br>'));
    }

    protected function setupUpdateOperation()
    {
        CRUD::setValidation(TaskRequest::class);
        CRUD::field('problem_statement')->label('Условие')->type('textarea')->hint('Формулы — в $…$');
        CRUD::field('points')->label('Баллы')->type('number')->attributes(['min' => 0, 'max' => 100]);
        CRUD::field('explanation')->label('Разбор (после сдачи)')->type('textarea');
        CRUD::field('shuffle_options')->label('Перемешивать варианты')->type('checkbox');
    }

    private function typeColumn(): void
    {
        CRUD::column('type')->label('Тип')->type('closure')->function(fn (Task $task) => $task->type?->label());
    }
}
