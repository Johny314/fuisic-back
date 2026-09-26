<?php

namespace App\Http\Controllers\Card;

use App\Enums\Uri;
use App\Models\Card\Card;
use App\OpenApi\Delete;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Destroy extends Controller
{
    #[Delete(
        path: Uri::card_id,
        tag: Tag::card,
        summary: 'Удалить карточку',
    )]
    #[ModelId('card', 'id карточки')]

    #[Response(404, NotFound::class)]
    #[Response(200, Ok::class)]
    public function __invoke(Card $card): JsonResponse
    {
        $card->loadMissing('cardSet');
        Gate::authorize('delete', $card);
        $card->delete();

        return new JsonResponse;
    }
}
