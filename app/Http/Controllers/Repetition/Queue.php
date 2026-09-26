<?php

namespace App\Http\Controllers\Repetition;

use App\Data\Repetition\RepetitionQueue;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Get;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\Repetitions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class Queue extends Controller
{
    #[Get(
        path: Uri::repetitions_queue,
        tag: Tag::repetitions,
        summary: 'Очередь «На сегодня»',
        description: <<<'MD'
            Пачка карточек для предзагрузки по всем наборам в повторениях (или по одному — `card_set_id`) с подписями интервалов для 4 кнопок.
            Порядок: шаги изучения/переучивания с due ≤ сейчас → повторения (review) с due до конца текущего «дня» (04:00 по часовому поясу пользователя) →
            новые в пределах дневного лимита минус начатые сегодня → шаги изучения, до которых ≤ 20 мин.
            После оценки карточка на коротком шаге (1/10 мин) возвращается в следующей пачке — когда пачка заканчивается, запросите очередь снова.
            MD,
    )]
    #[OA\Parameter(name: 'card_set_id', description: 'Только этот набор (должен быть в повторениях, иначе 404)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Parameter(name: 'limit', description: 'Размер пачки, 1–100', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 100, minimum: 1), example: 50)]

    #[Response(200, RepetitionQueue::class)]
    #[OA\Response(response: 404, description: 'Набора нет в повторениях или он недоступен')]
    #[OA\Response(response: 422, description: 'Ошибка валидации')]
    public function __invoke(Request $request, Repetitions $repetitions): RepetitionQueue
    {
        $params = $request->validate([
            'card_set_id' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer', 'between:1,'.Repetitions::MAX_BATCH],
        ]);
        $user = $request->user();

        $set = null;
        if (isset($params['card_set_id'])) {
            $set = CardSet::query()->find($params['card_set_id']);
            abort_unless($set && $repetitions->contains($user, $set), 404, 'Набора нет в ваших повторениях');
        }

        return $repetitions->queue($user, $set, (int) ($params['limit'] ?? Repetitions::DEFAULT_BATCH));
    }
}
