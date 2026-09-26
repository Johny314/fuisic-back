<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Вход в админку — право admin.access (admin проходит через Gate::before).
 * Разделы внутри закрыты своими правами (Admin\Concerns\AuthorizesCrud).
 */
class CheckIfAdmin
{
    public const string DENIED = 'Нет доступа к админке';

    public function handle(Request $request, Closure $next): mixed
    {
        if (backpack_auth()->guest()) {
            return $this->deny($request, trans('backpack::base.unauthorized'), 401);
        }

        if (! backpack_user()->can(PermissionName::adminAccess->value)) {
            // выходим, иначе форма входа (guest) уведёт вошедшего без права обратно
            backpack_auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->deny($request, self::DENIED, 403, withError: true);
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, int $status, bool $withError = false): Response|RedirectResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response($message, $status);
        }

        $redirect = redirect()->guest(backpack_url('login'));

        return $withError
            ? $redirect->withErrors([config('backpack.base.authentication_column', 'email') => $message])
            : $redirect;
    }
}
