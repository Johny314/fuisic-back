<?php

namespace App\Http\Controllers\Repetition;

use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Delete;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\Repetitions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Destroy extends Controller
{
    #[Delete(
        path: Uri::repetitions_set,
        tag: Tag::repetitions,
        summary: 'Убрать набор из повторений',
        description: 'Прогресс по карточкам сохраняется и вернётся при повторном добавлении. Работает и для удалённого или ставшего недоступным набора; набора нет в повторениях — тоже 200.',
    )]
    #[ModelId('card_set', 'id набора карточек')]

    #[Response(200, Ok::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, CardSet $card_set, Repetitions $repetitions): JsonResponse
    {
        // своя строка «Моих повторений» — видимость набора не проверяем, иначе скрытый набор не убрать
        $repetitions->remove($request->user(), $card_set);

        return new JsonResponse;
    }
}
