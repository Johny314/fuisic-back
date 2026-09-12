<?php

namespace App\Http\Controllers\CardSet;

use App\Data\Card\Card as Data;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class ShowCards extends Controller
{
    #[Get(
        path: Uri::card_set_cards,
        tag: Tag::card_set,
        summary: 'Вывести карточки  из набора по его id',
    )]
    #[ModelId('card_set', 'id набора карточек')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, CardSet $card_set): Collection
    {
        ContentAccess::abortUnlessCanViewCardSet($card_set);

        $cards = $card_set->cards();
        if ($request->boolean('ordered')) {
            $cards->orderBy('id');
        } else {
            $cards->inRandomOrder();
        }

        return Data::collect($cards->get());
    }
}
