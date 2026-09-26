<?php

namespace App\Http\Controllers\Task;

use App\Data\Test\ShortTask;
use App\Data\Test\Task as Data;
use App\Enums\Uri;
use App\Models\Test\Task;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Show extends Controller
{
    #[Get(
        path: Uri::task_id,
        tag: Tag::task,
        summary: 'Вывести задачу по ее id',
        description: 'Тем, кто может редактировать задачу, — вопрос целиком (`Task`, с ответами); остальным — как при прохождении (`ShortTask`).',
    )]
    #[ModelId('task', 'id задачи')]

    #[Response(200, [Data::class, ShortTask::class])]
    #[Response(404, NotFound::class)]
    public function __invoke(Task $task): Data|ShortTask
    {
        $gate = Gate::forUser(ContentAccess::user());
        $gate->authorize('view', $task);

        // ответы видит только редактор — иначе их можно подсмотреть, не проходя тест
        return $gate->allows('update', $task) ? Data::from($task) : ShortTask::from($task);
    }
}
