<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заблокированного выкидывает из админки на первом же запросе — так закрываются
 * и сессии, которые не удалось удалить при блокировке (другой драйвер, нет user_id).
 */
class LogoutBlockedBackpackUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = backpack_user();
        $block = $user instanceof User ? $user->activeBlock() : null;

        if ($block === null) {
            return $next($request);
        }

        backpack_auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Аккаунт заблокирован'
            .($block->until ? ' до '.$block->until->timezone(config('app.timezone'))->format('d.m.Y H:i') : '')
            .': '.$block->reason;

        if ($request->ajax() || $request->wantsJson()) {
            return response($message, 403);
        }

        return redirect()->guest(backpack_url('login'))
            ->withErrors([config('backpack.base.authentication_column', 'email') => $message]);
    }
}
