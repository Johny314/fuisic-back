<?php

namespace App\Data\Card;

use App\Data\Data;
use App\Data\User\User;
use App\Models\Card\CardSetHistory as Model;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;

#[Schema(required: ['card_set_id', 'user_id', 'completion_time'])]
class CardSetHistory extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(writeOnly: true, example: '1')]
    public ?string $card_set_id;

    #[Property(writeOnly: true, example: '1')]
    public ?string $user_id;

    #[Property(minimum: 0, example: 300)]
    public int $completion_time;

    #[Property(readOnly: true)]
    public ?CardSet $cardSet;

    #[Property(readOnly: true)]
    public ?User $user;

    public static function fromRequest(Request $request): CardSetHistory
    {
        return static::from($request->toArray());
    }

    public static function fromModel(Model $model): CardSetHistory
    {
        return static::from([
            'cardSet' => CardSet::from($model->cardSet),
            'user' => User::from($model->user),
        ] + $model->toArray());
    }
}
