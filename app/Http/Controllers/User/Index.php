<?php

namespace App\Http\Controllers\User;

use App\Data\User\User as Data;
use App\Enums\Uri;
use App\Models\User;
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
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\QueryBuilder;

class Index extends Controller
{
    #[Get(
        path: Uri::user,
        tag: Tag::user,
        summary: 'Список пользователей',
    )]
    #[Sort(['id', 'name'])]
    #[Filter(name: 'name', example: 'Иван Иванов')]
    #[Page]
    #[PerPage]

    #[IndexPaginatedResponse(User::class, description: 'Список пользователей')]
    public function __invoke(Request $request): PaginatedDataCollection
    {
        Gate::forUser(ContentAccess::requireUser())->authorize('viewAny', User::class);

        $models = QueryBuilder::for(User::query())
            ->allowedSorts(...['id', 'name'])
            ->allowedFilters(...['name'])
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $request->per_page,
                page: $request->page
            );

        return Data::collect($models, PaginatedDataCollection::class);
    }
}
