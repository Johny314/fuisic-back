<?php

namespace App\Http\Controllers\Child;

use App\Enums\Uri;
use App\OpenApi\Delete;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Fuisic\Auth\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Destroy extends Controller
{
    #[Delete(
        path: Uri::children_id,
        tag: Tag::children,
        summary: 'Удалить аккаунт ребёнка',
        description: 'Право `children.manage`; удалить может только родитель, создавший аккаунт (иначе 403). Чужой ребёнок — 404.',
    )]
    #[ModelId('child', 'id ребёнка')]

    #[Response(200, Ok::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, int $child, AuthTokenService $tokens): JsonResponse
    {
        $parent = $request->user();
        $model = $parent->children()->findOrFail($child);

        abort_unless((int) $model->created_by_id === (int) $parent->id, 403, 'Удалить аккаунт может только родитель, который его создал');

        $tokens->revokeAll($model);
        $model->delete();

        return new JsonResponse;
    }
}
