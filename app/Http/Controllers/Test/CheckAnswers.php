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
use App\Support\Tasks\AnswerCheck;
use App\Support\Tasks\QuestionType;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class CheckAnswers extends Controller
{
    #[Post(
        path: Uri::check_answers,
        tag: Tag::test,
        summary: 'Проверить ответы теста по его id',
        description: 'Ответ на вопрос — поле по типу: single `option_id`, multiple `option_ids`, text `text`, number `value`; '
            .'устаревшее `answer` строкой работает для всех типов. multiple — частичные баллы. 422 — повтор task_id или неверный формат поля',
    )]
    #[ModelId('test', 'id теста')]
    #[RequestBody(Answers::class)]

    #[Response(200, Results::class)]
    #[Response(404, NotFound::class)]
    #[OA\Response(response: 422, description: 'Ошибка валидации')]
    public function __invoke(Test $test, Answers $answers): Results
    {
        Gate::forUser(ContentAccess::user())->authorize('view', $test);

        $tasks = $test->tasks()->with('options')->get()->keyBy('id');

        $results = [];

        foreach ($answers->answers as $answer) {
            $task = $tasks->get($answer['task_id']);
            // вопрос не из этого теста — без баллов и без раскрытия
            $definition = $task?->definition();
            $check = $task ? $definition->check($task, $answer) : AnswerCheck::incorrect(0);

            $results[] = Result::from([
                'task' => $task,
                'answer' => $definition ? $definition->answerText($answer) : QuestionType::legacyAnswer($answer),
                'correct_answer' => $task?->answer,
                'is_correct' => $check->isCorrect(),
                'score' => $check->score,
                'max_score' => $check->maxScore,
                'status' => $check->status,
            ]);
        }

        return Results::from([
            'time' => $answers->time,
            'results' => $results,
            'total_score' => round(array_sum(array_map(fn (Result $result) => $result->score, $results)), 2),
            'max_score' => (int) $tasks->sum('points'),
        ]);
    }
}
