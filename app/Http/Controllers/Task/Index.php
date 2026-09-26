<?php

namespace App\Http\Controllers\Task;

use App\Data\Test\Task as Data;
use App\Enums\Uri;
use App\Models\Test\Task as TaskModel;
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
        path: Uri::task,
        tag: Tag::task,
        summary: 'Список задач, с пагинацией',
        description: 'Вопросы целиком (с ответами) из тестов, которые пользователь может редактировать: свои, каталог — с правом catalog.manage, admin — все.',
    )]
    #[Sort(['id', 'test_id'])]
    #[Filter(name: 'test_id', example: 1)]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(Data::class, description: 'Список задач')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        $user = ContentAccess::requireUser();
        // список — для редактора (с ответами): только тесты, которые пользователь может менять
        $query = TaskModel::query()->with('options')
            ->whereHas('test', fn ($testQuery) => ContentAccess::applyEditableScope($testQuery, $user));

        $models = QueryBuilder::for($query)
            ->allowedSorts(...['id', 'test_id'])
            ->allowedFilters(...[
                AllowedFilter::exact('test_id'),
            ])
            ->orderBy('id')
            ->paginate(
                perPage: $request->per_page,
                page: $request->page
            );

        return Data::collect($models, PaginatedDataCollection::class);
    }
}
