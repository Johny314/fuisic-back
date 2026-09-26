<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\ShortTask as Data;
use App\Enums\Uri;
use App\Models\Test\Test;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ShowTasks extends Controller
{
    #[Get(
        path: Uri::test_tasks,
        tag: Tag::test,
        summary: 'Вывести задачи теста по его id',
        description: 'Для прохождения: без правильных ответов, настроек проверки и разбора.',
    )]
    #[ModelId('test', 'id теста')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Test $test): Collection
    {
        Gate::forUser(ContentAccess::user())->authorize('view', $test);

        return Data::collect($test->tasks()->with('options')->orderBy('id')->get());
    }
}
