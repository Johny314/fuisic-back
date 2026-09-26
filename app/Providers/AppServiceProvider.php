<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // admin — суперадмин: любые права и политики, без записи прав в роль
        Gate::before(fn ($user) => $user instanceof User && $user->isAdmin() ? true : null);
    }
}
