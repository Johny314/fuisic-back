<?php

namespace App\Data\Repetition;

use App\Data\Card\Card;
use App\Data\Data;
use App\Models\Card\Card as CardModel;
use App\Models\Card\CardReviewState;
use App\OpenApi\Property;
use App\Services\Fsrs\ReviewOutcome;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Schema;

/** Карточка очереди с её состоянием и подписями четырёх кнопок оценки. */
#[Schema(required: ['card', 'card_set_id', 'state', 'due', 'reps', 'lapses', 'intervals'])]
class QueueCard extends Data
{
    #[Property(readOnly: true)]
    public Card $card;

    #[Property(example: 1)]
    public int $card_set_id;

    #[Property(type: 'string', enum: ['new', 'learning', 'review', 'relearning'], description: 'Текущее состояние: new — ещё не изучалась', example: 'review')]
    public string $state;

    #[Property(format: 'date-time', description: 'Срок показа, UTC; null у новой', example: '2026-09-26T10:00:00.000000Z')]
    public ?string $due;

    #[Property(description: 'Сколько раз оценивалась', example: 3)]
    public int $reps;

    #[Property(description: 'Сколько раз забыта после выучивания', example: 0)]
    public int $lapses;

    /** @var list<IntervalPreview> */
    #[Property(type: 'array', items: new Items(ref: '#/components/schemas/IntervalPreview'), description: 'По одной на оценку 1–4')]
    public array $intervals;

    /** @param array<int, ReviewOutcome> $preview */
    public static function fromState(CardModel $card, CardReviewState $state, array $preview): self
    {
        return static::from([
            'card' => Card::from($card),
            'card_set_id' => $card->card_set_id,
            'state' => $state->state->name,
            'due' => $state->due?->toJSON(),
            'reps' => $state->reps,
            'lapses' => $state->lapses,
            'intervals' => array_values(array_map(IntervalPreview::fromOutcome(...), $preview)),
        ]);
    }
}
