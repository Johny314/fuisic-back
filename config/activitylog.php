<?php

use App\Models\Activity;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

/*
 * Журнал действий персонала (App\Services\AuditLog): что пишется — docs/AUTH.md, раздел «Журнал действий».
 */
return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * Хранение 12 месяцев: activitylog:clean по расписанию (routes/console.php)
     * удаляет записи старше этого числа дней.
     */
    'clean_after_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'audit',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     * Автора в админке задаёт middleware RecordAdminActivity (guard backpack), в сервисах — явно.
     */
    'default_auth_driver' => null,

    /*
     * Удалённые (soft delete) разделы, наборы, пользователи остаются видны в журнале.
     */
    'include_soft_deleted_subjects' => true,

    /*
     * Модель журнала: IP автора и CRUD Backpack.
     */
    'activity_model' => Activity::class,

    /*
     * Секреты не пишутся ни для одной модели; изменение отмечается как «скрыто»
     * (App\Models\Concerns\AuditsAdminChanges).
     */
    'default_except_attributes' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
    ],

    /*
     * When enabled, activities are buffered in memory and inserted in a
     * single bulk query after the response has been sent to the client.
     * This can significantly reduce the number of database queries when
     * many activities are logged during a single request.
     *
     * Only enable this if your application logs a high volume of activities
     * per request. Buffered activities will not have an ID until the
     * buffer is flushed.
     */
    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    /*
     * These action classes can be overridden to customize how activities
     * are logged and cleaned. Your custom classes must extend the originals.
     */
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
