<?php

namespace App\Http\Controllers\CardSet;

use App\Data\Card\CardSet as Data;
use App\Enums\Uri;
use App\Models\Card\CardSet as CardSetModel;
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
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class Index extends Controller
{
    #[Get(
        path: Uri::card_set,
        tag: Tag::card_set,
        summary: 'Список наборов карточек, с пагинацией',
    )]
    #[Sort(['id', 'name'])]
    #[Filter(name: 'name', example: 'Основы алгебры')]
    #[Filter(name: 'subject', example: 'Математика')]
    #[Filter(name: 'section_id', example: 1)]
    #[Filter(name: 'class', example: '10')]
    #[Filter(name: 'difficulty', example: 'hard')]
    #[Filter(name: 'scope', example: 'studio')]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(Data::class, description: 'Список наборов карточек')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        $query = CardSetModel::query()->with(['section', 'user']);
        ContentAccess::applyIndexScope($query, $request->input('filter.scope'));

        $models = QueryBuilder::for($query)
            ->allowedSorts(...['id', 'name'])
            ->allowedFilters(...[
                'name',
                AllowedFilter::exact('section_id'),
                AllowedFilter::exact('subject'),
                AllowedFilter::exact('class'),
                AllowedFilter::exact('difficulty'),
                AllowedFilter::callback('scope', static fn () => null),
            ])
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $request->per_page,
                page: $request->page
            );

        return Data::collect($models, PaginatedDataCollection::class);
    }
}
