<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\Answers;
use App\Data\Test\Result;
use App\Data\Test\Results;
use App\Enums\Uri;
use App\Models\Test\Test;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class CheckAnswers extends Controller
{
    #[Post(
        path: Uri::check_answers,
        tag: Tag::test,
        summary: 'Проверить ответы теста по его id',
    )]
    #[ModelId('test', 'id теста')]
    #[RequestBody(Answers::class)]

    #[Response(200, Results::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Test $test, Answers $answers): Results
    {
        Gate::forUser(ContentAccess::user())->authorize('view', $test);

        $tasks = $test->tasks()->get()->keyBy('id');

        $results = [];

        foreach ($answers->answers as $answer) {
            $task = $tasks->get($answer['task_id']);
            $isCorrect = $task && $task->answer === $answer['answer'];

            $results[] = Result::from([
                'task' => $task,
                'answer' => $answer['answer'],
                'correct_answer' => $task?->answer,
                'is_correct' => $isCorrect,
            ]);
        }

        return Results::from([
            'time' => $answers->time,
            'results' => $results,
            'total_score' => count(array_filter($results, fn ($result) => $result->is_correct)),
        ]);
    }
}
