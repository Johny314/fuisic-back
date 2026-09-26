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
use App\Services\TaskEditor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Store extends Controller
{
    #[Post(
        path: Uri::task,
        tag: Tag::task,
        summary: 'Новый вопрос',
        description: 'Вопрос целиком с вариантами. Без `type` и `settings` — старый формат: `text` с одним ответом из `answer`.',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    public function __invoke(Request $request, TaskEditor $editor): Data
    {
        $testId = $request->validate(['test_id' => ['required', 'integer']])['test_id'];
        $test = Test::query()->findOrFail($testId);
        Gate::authorize('create', [Task::class, $test]);

        return Data::from($editor->create($test, $request->all()));
    }
}
