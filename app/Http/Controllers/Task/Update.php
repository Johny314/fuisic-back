<?php

namespace App\Http\Controllers\Task;

use App\Data\Test\Task as Data;
use App\Enums\Uri;
use App\Models\Test\Task;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Update extends Controller
{
    #[Put(
        path: Uri::task_id,
        tag: Tag::task,
        summary: 'Обновить данные задачи',
    )]
    #[ModelId('task', 'id задачи')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(Task $task, Data $data): Data
    {
        $task->loadMissing('test');
        Gate::authorize('update', $task);

        $payload = $data->persistAttributes();
        unset($payload['test_id']);
        $task->update($payload);

        return Data::from($task);
    }
}
