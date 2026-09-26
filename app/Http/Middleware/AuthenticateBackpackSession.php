<?php

namespace App\Http\Middleware;

use Backpack\CRUD\app\Http\Middleware\AuthenticateSession;
use Closure;

/**
 * AuthenticateSession Backpack с проверкой хэша пароля как в Laravel 13: SessionGuard кладёт
 * в сессию HMAC хэша (hashPasswordForCookie), а Backpack 7 сравнивает с сырым хэшем
 * и разлогинивает на каждом запросе после входа. Смена пароля по-прежнему завершает сессии.
 * Guard — backpack (у родителя guard() отдаёт фабрику, то есть default guard).
 */
class AuthenticateBackpackSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if (! $request->hasSession() || ! $this->user || ! $this->user->getAuthPassword()) {
            return $next($request);
        }

        $key = 'password_hash_'.backpack_guard_name();
        $password = $this->user->getAuthPassword();

        if ($this->guard()->viaRemember()) {
            $fromCookie = explode('|', (string) $request->cookies->get($this->guard()->getRecallerName()))[2] ?? null;

            if (! $fromCookie || ! $this->validatePasswordHash($password, $fromCookie)) {
                $this->logout($request);
            }
        }

        if (! $request->session()->has($key)) {
            $this->storePasswordHashInSession($request);
        }

        if (! $this->validatePasswordHash($password, (string) $request->session()->get($key))) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request) {
            if (! is_null($this->guard()->user())) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    protected function storePasswordHashInSession($request)
    {
        if (! $this->user) {
            return;
        }

        $request->session()->put([
            'password_hash_'.backpack_guard_name() => $this->guard()->hashPasswordForCookie($this->user->getAuthPassword()),
        ]);
    }

    // Сначала формат Laravel 13 (HMAC), затем сырой хэш — для сессий, записанных до исправления
    protected function validatePasswordHash($passwordHash, $storedValue)
    {
        return hash_equals($this->guard()->hashPasswordForCookie($passwordHash), $storedValue)
            || hash_equals($passwordHash, $storedValue);
    }

    protected function guard()
    {
        return backpack_auth();
    }
}
