<?php

namespace App\Http\Controllers\Task;

use App\Enums\Uri;
use App\Models\Test\Task;
use App\OpenApi\Delete;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Destroy extends Controller
{
    #[Delete(
        path: Uri::task_id,
        tag: Tag::task,
        summary: 'Удалить задачу',
    )]
    #[ModelId('Task', 'id задачи')]

    #[Response(404, NotFound::class)]
    #[Response(200, Ok::class)]
    public function __invoke(Task $task): JsonResponse
    {
        $task->loadMissing('test');
        Gate::authorize('delete', $task);
        $task->delete();

        return new JsonResponse;
    }
}
