<?php

namespace App\OpenApi;

use App\Enums\Arrayable;

enum Tag: string
{
    use Arrayable;

    case card = 'card';
    case test = 'test';
    case card_set = 'card_set';
    case task = 'task';
    case section = 'section';
    case user = 'user';
    case children = 'children';
    case auth = 'auth';
    case filters = 'filters';
    case files = 'files';
    case teacher_verification = 'teacher_verification';
    case settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::card => 'Карточки',
            self::test => 'Тесты',
            self::card_set => 'Наборы карточек',
            self::task => 'Задачи',
            self::section => 'Разделы',
            self::user => 'Пользователи',
            self::children => 'Дети',
            self::auth => 'Авторизация',
            self::filters => 'Доступные фильтры',
            self::files => 'Файлы',
            self::teacher_verification => 'Проверенный учитель',
            self::settings => 'Настройки',
        };
    }
}
