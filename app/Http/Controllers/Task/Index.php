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
    )]
    #[Sort(['id', 'test_id'])]
    #[Filter(name: 'test_id', example: 1)]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(Data::class, description: 'Список задач')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        $user = ContentAccess::requireUser();
        $query = TaskModel::query()->whereHas('test', function ($testQuery) use ($user) {
            if (! ContentAccess::isAdmin($user)) {
                $testQuery->where('user_id', $user->id);
            }
        });

        $models = QueryBuilder::for($query)
            ->allowedSorts(['id', 'test_id'])
            ->allowedFilters([
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
