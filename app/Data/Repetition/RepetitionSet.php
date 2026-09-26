<?php

namespace App\Data\Repetition;

use App\Data\Card\CardSet;
use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/** Набор в «Моих повторениях» со счётчиками на текущий момент. */
#[Schema(required: ['card_set', 'added_at', 'cards_count', 'due_count', 'new_count'])]
class RepetitionSet extends Data
{
    #[Property(readOnly: true)]
    public CardSet $card_set;

    #[Property(format: 'date-time', description: 'Когда добавлен в повторения, UTC', example: '2026-09-26T10:00:00.000000Z')]
    public string $added_at;

    #[Property(description: 'Карточек в наборе', example: 30)]
    public int $cards_count;

    #[Property(description: 'К повторению сейчас (правило — как в очереди)', example: 5)]
    public int $due_count;

    #[Property(description: 'Ещё не изучавшихся карточек (без учёта дневного лимита)', example: 12)]
    public int $new_count;
}
