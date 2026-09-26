<?php

namespace App\Http\Controllers\Card;

use App\Data\Card\Card as Data;
use App\Enums\Uri;
use App\Models\Card\Card;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Show extends Controller
{
    #[Get(
        path: Uri::card_id,
        tag: Tag::card,
        summary: 'Вывести карточку по ее id',
    )]
    #[ModelId('card', 'id карточки')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Card $card): Data
    {
        Gate::forUser(ContentAccess::user())->authorize('view', $card);

        return Data::from($card);
    }
}
