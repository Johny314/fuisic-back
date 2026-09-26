<?php

namespace App\Enums;

/** Тип места работы учителя в заявке — значения показываются как подписи. */
enum WorkplaceType: string
{
    use Arrayable;

    case school = 'Школа';
    case center = 'Учебный центр';
    case privatePractice = 'Частная практика';
}
