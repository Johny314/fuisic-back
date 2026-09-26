<?php

namespace App\Http\Controllers\Section;

use App\Enums\Uri;
use App\Models\Section;
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
        path: Uri::section_id,
        tag: Tag::section,
        summary: 'Удалить раздел',
    )]
    #[ModelId('section', 'id раздела')]

    #[Response(404, NotFound::class)]
    #[Response(200, Ok::class)]
    public function __invoke(Section $section): JsonResponse
    {
        Gate::authorize('delete', $section);

        $section->delete();

        return new JsonResponse;
    }
}
