<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // флаг «пишем изменения админки» живёт в пределах запроса
        $this->app->scoped(AuditLog::class);
    }

    public function boot(): void
    {
        // admin — суперадмин: любые права и политики, без записи прав в роль
        Gate::before(fn ($user) => $user instanceof User && $user->isAdmin() ? true : null);

        // Отказ политики без своего сообщения
        Gate::defaultDenialResponse(Response::deny('Недостаточно прав'));
    }
}
