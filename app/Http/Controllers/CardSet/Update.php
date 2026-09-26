<?php

namespace App\Http\Controllers\CardSet;

use App\Data\Card\CardSet as Data;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\MediaStorage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Update extends Controller
{
    #[Put(
        path: Uri::card_set_id,
        tag: Tag::card_set,
        summary: 'Обновить данные набора карточек',
    )]
    #[ModelId('card_set', 'id набора карточек')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(CardSet $card_set, Data $data, MediaStorage $media): Data
    {
        Gate::authorize('update', $card_set);

        $payload = $data->persistAttributes();
        if (array_key_exists('logo_path', $payload) && $payload['logo_path'] !== $card_set->logo_path) {
            $media->delete($card_set->logo_path);
        }

        $card_set->update($payload);
        $card_set->load(['section', 'user']);

        return Data::from($card_set);
    }
}
