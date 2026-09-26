<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Истёкшая блокировка перестаёт действовать сразу (проверка срока на лету), команда закрывает её в истории
Schedule::command('users:unblock-expired')->everyFiveMinutes()->withoutOverlapping();

// Журнал действий хранится 12 месяцев (activitylog.clean_after_days = 365)
Schedule::command('activitylog:clean --force')->daily()->withoutOverlapping();
