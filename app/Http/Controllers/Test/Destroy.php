<?php

namespace App\Http\Controllers\Test;

use App\Enums\Uri;
use App\Models\Test\Test;
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
        path: Uri::test_id,
        tag: Tag::test,
        summary: 'Удалить тест',
    )]
    #[ModelId('Test', 'id теста')]

    #[Response(404, NotFound::class)]
    #[Response(200, Ok::class)]
    public function __invoke(Test $test): JsonResponse
    {
        Gate::authorize('delete', $test);
        $test->delete();

        return new JsonResponse;
    }
}
