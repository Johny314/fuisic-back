<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\Test as Data;
use App\Enums\Uri;
use App\Models\Test\Test as TestModel;
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
        path: Uri::test,
        tag: Tag::test,
        summary: 'Список тестов, с пагинацией',
    )]
    #[Sort(['id', 'creator_id', 'name'])]
    #[Filter(name: 'name', example: 'Тест по арифметике')]
    #[Filter(name: 'section_id', example: 1)]
    #[Filter(name: 'subject', example: 'Физика')]
    #[Filter(name: 'class', example: '10-11')]
    #[Filter(name: 'difficulty', example: 'easy')]
    #[Filter(name: 'creator_id', example: 1)]
    #[Filter(name: 'scope', example: 'studio')]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(Data::class, description: 'Список тестов')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        $query = TestModel::query()->with(['section', 'user']);
        ContentAccess::applyIndexScope($query, $request->input('filter.scope'));

        $models = QueryBuilder::for($query)
            ->allowedSorts(['id', 'creator_id', 'name'])
            ->allowedFilters([
                'creator_id',
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
