<?php

namespace App\Http\Controllers\Repetition;

use App\Data\Repetition\RepetitionSet;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\IndexResponse;
use App\OpenApi\Tag;
use App\Services\Repetitions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class Index extends Controller
{
    #[Get(
        path: Uri::repetitions,
        tag: Tag::repetitions,
        summary: 'Мои наборы в повторениях',
        description: 'В порядке добавления, со счётчиками карточек к сроку и новых. Удалённые наборы и ставшие недоступными (например, скрытые из каталога) не отдаются.',
    )]

    #[IndexResponse(RepetitionSet::class, description: 'Наборы в повторениях')]
    public function __invoke(Request $request, Repetitions $repetitions): Collection
    {
        return $repetitions->overview($request->user());
    }
}
