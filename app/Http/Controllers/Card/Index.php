<?php

namespace App\Http\Controllers\Card;

use App\Data\Card\Card as Data;
use App\Enums\Uri;
use App\Models\Card\Card as CardModel;
use App\OpenApi\Get;
use App\OpenApi\Parameter\Filter;
use App\OpenApi\Parameter\Page;
use App\OpenApi\Parameter\PerPage;
use App\OpenApi\Parameter\Sort;
use App\OpenApi\Response\IndexPaginatedResponse;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\QueryBuilder;

class Index extends Controller
{
    #[Get(
        path: Uri::card,
        tag: Tag::card,
        summary: 'Список карточек, с пагинацией',
    )]
    #[Sort(['id', 'card_set_id'])]
    #[Filter(name: 'card_set_id', example: 1)]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(Data::class, description: 'Список карточек')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        $models = QueryBuilder::for(
            CardModel::query()->whereHas('cardSet', fn ($sets) => ContentAccess::applyVisibleScope($sets))
        )
            ->allowedSorts(['id', 'card_set_id'])
            ->allowedFilters(['card_set_id'])
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $request->per_page,
                page: $request->page
            );

        return Data::collect($models, PaginatedDataCollection::class);
    }
}
