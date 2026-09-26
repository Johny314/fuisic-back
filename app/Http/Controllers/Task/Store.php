<?php

namespace App\Http\Controllers\Task;

use App\Data\Test\Task as Data;
use App\Enums\Uri;
use App\Models\Test\Task;
use App\Models\Test\Test;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Store extends Controller
{
    #[Post(
        path: Uri::task,
        tag: Tag::task,
        summary: 'Новая задача',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    public function __invoke(Data $data): Data
    {
        $test = Test::query()->findOrFail($data->test_id);
        Gate::authorize('create', [Task::class, $test]);

        $task = Task::query()->create($data->persistAttributes());

        return Data::from($task);
    }
}
