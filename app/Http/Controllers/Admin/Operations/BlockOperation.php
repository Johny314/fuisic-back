<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Requests\BlockUserRequest;
use App\Models\User;
use App\Services\UserBlocking;
use Backpack\CRUD\app\Library\CrudPanel\Hooks\Facades\LifecycleHook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Prologue\Alerts\Facades\Alert;

/**
 * «Заблокировать / разблокировать» в CRUD пользователей: право users.block,
 * персонал блокирует только admin (UserBlocking::canBlock).
 */
trait BlockOperation
{
    protected function setupBlockRoutes(string $segment, string $routeName, string $controller): void
    {
        Route::get($segment.'/{id}/block', [
            'as' => $routeName.'.block',
            'uses' => $controller.'@blockForm',
            'operation' => 'block',
        ]);

        Route::post($segment.'/{id}/block', [
            'as' => $routeName.'.block.store',
            'uses' => $controller.'@block',
            'operation' => 'block',
        ]);

        Route::post($segment.'/{id}/unblock', [
            'as' => $routeName.'.unblock',
            'uses' => $controller.'@unblock',
            'operation' => 'block',
        ]);
    }

    protected function setupBlockDefaults(): void
    {
        $this->crud->setAccessCondition('block', function ($entry) {
            $actor = backpack_user();

            if (! $actor instanceof User) {
                return false;
            }

            return $entry instanceof User
                ? app(UserBlocking::class)->canBlock($actor, $entry)
                : $actor->can('users.block');
        });

        LifecycleHook::hookInto(['list:before_setup', 'show:before_setup'], function () {
            $this->crud->addButton('line', 'block', 'view', 'crud::buttons.block', 'end');
        });
    }

    public function blockForm(int|string $id): View
    {
        $user = $this->blockTarget($id);

        return view('admin.user-block', [
            'crud' => $this->crud,
            'entry' => $user,
            'title' => 'Блокировка: '.$user->name,
        ]);
    }

    public function block(BlockUserRequest $request, UserBlocking $blocking, int|string $id): RedirectResponse
    {
        $user = $this->blockTarget($id);

        $blocking->block(
            $user,
            backpack_user(),
            $request->string('reason')->trim()->value(),
            $request->filled('comment') ? $request->string('comment')->trim()->value() : null,
            $request->date('until'),
        );

        Alert::success('Пользователь заблокирован')->flash();

        return redirect()->to(url($this->crud->route));
    }

    public function unblock(UserBlocking $blocking, int|string $id): RedirectResponse
    {
        $user = $this->blockTarget($id);

        $blocking->unblock($user, backpack_user());

        Alert::success('Пользователь разблокирован')->flash();

        return redirect()->back(fallback: url($this->crud->route));
    }

    private function blockTarget(int|string $id): User
    {
        $user = User::query()->findOrFail($id);
        $this->crud->hasAccessOrFail('block', $user);

        return $user;
    }
}
