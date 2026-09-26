<?php

namespace App\Http\Controllers\CardSet;

use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Delete;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Destroy extends Controller
{
    #[Delete(
        path: Uri::card_set_id,
        tag: Tag::card_set,
        summary: 'Удалить набор карточек',
    )]
    #[ModelId('card_set', 'id набора карточек')]

    #[Response(404, NotFound::class)]
    #[Response(200, Ok::class)]
    public function __invoke(CardSet $card_set, MediaStorage $media): JsonResponse
    {
        Gate::authorize('delete', $card_set);
        $media->delete($card_set->logo_path);
        $card_set->delete();

        return new JsonResponse;
    }
}
