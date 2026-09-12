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
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;

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
        ContentAccess::abortUnlessCanManageTest($test);

        $task = Task::query()->create($data->persistAttributes());

        return Data::from($task);
    }
}
