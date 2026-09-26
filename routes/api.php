<?php

use App\Enums\PermissionName;
use App\Enums\Uri;
use App\Http\Controllers\Card;
use App\Http\Controllers\CardSet;
use App\Http\Controllers\Child;
use App\Http\Controllers\File\Store;
use App\Http\Controllers\Filters;
use App\Http\Controllers\Section;
use App\Http\Controllers\Task;
use App\Http\Controllers\Test;
use App\Http\Controllers\User;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post(Uri::files->value, Store::class);

    Route::post(Uri::card_set->value, CardSet\Store::class);
    Route::put(Uri::card_set_id->value, CardSet\Update::class);
    Route::delete(Uri::card_set_id->value, CardSet\Destroy::class);

    Route::post(Uri::section->value, Section\Store::class);
    Route::put(Uri::section_id->value, Section\Update::class);
    Route::delete(Uri::section_id->value, Section\Destroy::class);

    Route::post(Uri::user->value, User\Store::class);
    Route::put(Uri::user_id->value, User\Update::class);
    Route::delete(Uri::user_id->value, User\Destroy::class);

    Route::post(Uri::card->value, Card\Store::class);
    Route::put(Uri::card_id->value, Card\Update::class);
    Route::delete(Uri::card_id->value, Card\Destroy::class);

    Route::post(Uri::task->value, Task\Store::class);
    Route::put(Uri::task_id->value, Task\Update::class);
    Route::delete(Uri::task_id->value, Task\Destroy::class);

    Route::post(Uri::test->value, Test\Store::class);
    Route::put(Uri::test_id->value, Test\Update::class);
    Route::delete(Uri::test_id->value, Test\Destroy::class);

    Route::get(Uri::task->value, Task\Index::class);

    // Аккаунты детей родителя; чужой ребёнок — 404
    Route::middleware('can:'.PermissionName::childrenView->value)->group(function () {
        Route::get(Uri::children->value, Child\Index::class);
        Route::get(Uri::children_id->value, Child\Show::class)->whereNumber('child');
    });

    Route::middleware('can:'.PermissionName::childrenManage->value)->group(function () {
        Route::post(Uri::children->value, Child\Store::class);
        Route::put(Uri::children_id->value, Child\Update::class)->whereNumber('child');
        Route::put(Uri::children_password->value, Child\ResetPassword::class)->whereNumber('child');
        Route::delete(Uri::children_id->value, Child\Destroy::class)->whereNumber('child');
    });
});

Route::get(Uri::card_set->value, CardSet\Index::class);
Route::get(Uri::card_set_id->value, CardSet\Show::class);
Route::get(Uri::card_set_cards->value, CardSet\ShowCards::class);

Route::get(Uri::section->value, Section\Index::class);
Route::get(Uri::section_id->value, Section\Show::class);

Route::get(Uri::user->value, User\Index::class);
Route::get(Uri::user_id->value, User\Show::class);

Route::get(Uri::card->value, Card\Index::class);
Route::get(Uri::card_id->value, Card\Show::class);

Route::get(Uri::task_id->value, Task\Show::class);

Route::get(Uri::test->value, Test\Index::class);
Route::get(Uri::test_id->value, Test\Show::class);
Route::get(Uri::test_tasks->value, Test\ShowTasks::class);
Route::post(Uri::check_answers->value, Test\CheckAnswers::class);

Route::get(Uri::classification->value, Filters\Classifications::class);
Route::get(Uri::difficulty->value, Filters\Difficulties::class);
