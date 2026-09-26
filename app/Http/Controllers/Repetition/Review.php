<?php

namespace App\Http\Controllers\Repetition;

use App\Data\Repetition\ReviewResult;
use App\Data\Repetition\ReviewSubmit;
use App\Enums\Uri;
use App\Models\Card\Card;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\CardReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class Review extends Controller
{
    #[Post(
        path: Uri::card_review,
        tag: Tag::repetitions,
        summary: 'Оценить карточку',
        description: 'Любая видимая пользователю карточка (набор не обязан быть в повторениях — оценка, отправленная повторно после удаления набора из повторений, не теряется). '
            .'Идемпотентно по `review_id`: повтор с тем же id не создаёт вторую оценку и возвращает тот же ответ; тот же id с другой карточкой или оценкой — 409.',
    )]
    #[ModelId('card', 'id карточки')]
    #[RequestBody(ReviewSubmit::class)]

    #[Response(201, ReviewResult::class, description: 'Оценка принята')]
    #[Response(200, ReviewResult::class, description: 'Повтор с тем же review_id: оценка уже была, ответ тот же')]
    #[OA\Response(response: 403, description: 'Набор недоступен')]
    #[Response(404, NotFound::class)]
    #[OA\Response(response: 409, description: 'review_id уже использован для другой оценки')]
    #[OA\Response(response: 422, description: 'Ошибка валидации')]
    public function __invoke(Request $request, Card $card, CardReviews $reviews): JsonResponse
    {
        // карточка удалённого набора — как удалённая
        abort_if($card->cardSet === null, 404);
        Gate::authorize('view', $card);

        $data = ReviewSubmit::validateAndCreate($request->all());
        $log = $reviews->submit($request->user(), $card, $data->ratingEnum(), $data->review_id, durationMs: $data->duration());

        return new JsonResponse(ReviewResult::fromModel($log), $log->wasRecentlyCreated ? 201 : 200);
    }
}
