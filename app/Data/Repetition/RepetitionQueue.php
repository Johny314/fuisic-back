<?php

namespace App\Data\Repetition;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Schema;

/** Очередь «На сегодня»: пачка карточек для предзагрузки и счётчики. */
#[Schema(required: ['day', 'day_ends_at', 'due_count', 'new_count', 'new_limit', 'new_started_today', 'next_due_at', 'cards'])]
class RepetitionQueue extends Data
{
    #[Property(format: 'date', description: 'Локальная дата «дня» повторений (день начинается в 04:00 по часовому поясу пользователя)', example: '2026-09-26')]
    public string $day;

    #[Property(format: 'date-time', description: 'Конец дня (04:00 следующего дня), UTC', example: '2026-09-26T23:00:00.000000Z')]
    public string $day_ends_at;

    #[Property(description: 'Всего повторений к сроку (не только в этой пачке)', example: 14)]
    public int $due_count;

    #[Property(description: 'Новых, доступных сегодня: min(остаток дневного лимита, не начатых карточек)', example: 20)]
    public int $new_count;

    #[Property(description: 'Дневной лимит новых карточек', example: 20)]
    public int $new_limit;

    #[Property(description: 'Новых начато сегодня (по журналу оценок)', example: 0)]
    public int $new_started_today;

    #[Property(format: 'date-time', description: 'Когда вернётся ближайшая карточка на коротком шаге, не попавшая в очередь; null — таких нет', example: null)]
    public ?string $next_due_at;

    /** @var list<QueueCard> */
    #[Property(type: 'array', items: new Items(ref: '#/components/schemas/QueueCard'), description: 'Порядок показа: просроченные шаги изучения, повторения, новые, шаги изучения на ближайшие минуты')]
    public array $cards;
}
