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
use App\Services\TaskEditor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Update extends Controller
{
    #[Put(
        path: Uri::task_id,
        tag: Tag::task,
        summary: 'Изменить вопрос',
        description: 'Не переданные поля не меняются; `options` заменяют все варианты. Смена типа на text/number удаляет варианты.',
    )]
    #[ModelId('task', 'id задачи')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(Task $task, Request $request, TaskEditor $editor): Data
    {
        $task->loadMissing('test');
        Gate::authorize('update', $task);

        return Data::from($editor->update($task, $request->all()));
    }
}
