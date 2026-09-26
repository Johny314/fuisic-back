<?php

namespace App\Http\Controllers\Repetition;

use App\Data\Repetition\RepetitionSet;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Post;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\Repetitions;
use App\Support\RepetitionLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class Store extends Controller
{
    #[Post(
        path: Uri::repetitions_set,
        tag: Tag::repetitions,
        summary: 'Добавить набор в повторения',
        description: 'Свой или видимый набор каталога. Повторное добавление ничего не меняет (200). Прогресс по карточкам, если набор уже был в повторениях, сохраняется.',
    )]
    #[ModelId('card_set', 'id набора карточек')]

    #[Response(201, RepetitionSet::class, description: 'Добавлен')]
    #[Response(200, RepetitionSet::class, description: 'Уже был в повторениях')]
    #[OA\Response(response: 403, description: 'Набор недоступен')]
    #[Response(404, NotFound::class)]
    #[OA\Response(response: 422, description: 'Достигнут лимит наборов в повторениях: `code` = `repetition_sets_limit`, `limit`')]
    public function __invoke(Request $request, CardSet $card_set, Repetitions $repetitions, RepetitionLimits $limits): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('view', $card_set);

        $exists = $repetitions->contains($user, $card_set);
        if (! $exists && $repetitions->limitReached($user)) {
            return new JsonResponse([
                'message' => 'Достигнут лимит наборов в повторениях',
                'code' => 'repetition_sets_limit',
                'limit' => $limits->maxSetsInRepetition($user),
            ], 422);
        }

        $repetitions->add($user, $card_set);

        return new JsonResponse($repetitions->overview($user, $card_set)->sole(), $exists ? 200 : 201);
    }
}
