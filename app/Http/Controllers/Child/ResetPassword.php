<?php

namespace App\Http\Controllers\Child;

use App\Data\Child\ChildPassword as Data;
use App\Enums\Uri;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Ok;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Fuisic\Auth\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class ResetPassword extends Controller
{
    #[Put(
        path: Uri::children_password,
        tag: Tag::children,
        summary: 'Сбросить пароль ребёнку',
        description: 'Право `children.manage`. Все токены ребёнка отзываются — ребёнок входит заново с новым паролем. Чужой ребёнок — 404.',
    )]
    #[ModelId('child', 'id ребёнка')]
    #[RequestBody(Data::class)]

    #[Response(200, Ok::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, int $child, AuthTokenService $tokens): JsonResponse
    {
        $model = $request->user()->children()->findOrFail($child);
        $data = Data::validateAndCreate($request->all());

        $model->forceFill(['password' => Hash::make($data->password)])->save();

        $tokens->revokeAll($model);

        return new JsonResponse(['message' => 'Пароль изменён.']);
    }
}
