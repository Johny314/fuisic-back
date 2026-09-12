<?php

namespace App\Http\Controllers\CardSet;

use App\Data\Card\CardSet as Data;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;

class Show extends Controller
{
    #[Get(
        path: Uri::card_set_id,
        tag: Tag::card_set,
        summary: 'Вывести набор карточек по его id',
    )]
    #[ModelId('card_set', 'id набора карточек')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(CardSet $card_set): Data
    {
        ContentAccess::abortUnlessCanViewCardSet($card_set);
        $card_set->load(['section', 'user']);

        return Data::from($card_set);
    }
}
