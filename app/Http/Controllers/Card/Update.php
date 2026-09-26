<?php

namespace App\Http\Controllers\Card;

use App\Data\Card\Card as Data;
use App\Enums\Uri;
use App\Models\Card\Card;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Update extends Controller
{
    #[Put(
        path: Uri::card_id,
        tag: Tag::card,
        summary: 'Обновить данные карточки',
    )]
    #[ModelId('card', 'id карточки')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(Card $card, Data $data): Data
    {
        $card->loadMissing('cardSet');
        Gate::authorize('update', $card);

        $payload = $data->persistAttributes();
        unset($payload['card_set_id']);
        $card->update($payload);

        return Data::from($card);
    }
}
