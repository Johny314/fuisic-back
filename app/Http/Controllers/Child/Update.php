<?php

namespace App\Http\Controllers\Child;

use App\Data\Child\Child;
use App\Data\Child\ChildUpdate as Data;
use App\Enums\Uri;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Update extends Controller
{
    #[Put(
        path: Uri::children_id,
        tag: Tag::children,
        summary: 'Изменить профиль ребёнка',
        description: 'Право `children.manage`; логин меняет только родитель. Чужой ребёнок — 404.',
    )]
    #[ModelId('child', 'id ребёнка')]
    #[RequestBody(Data::class)]

    #[Response(200, Child::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, int $child): Child
    {
        // сначала 404 для чужого ребёнка, потом валидация
        $model = $request->user()->children()->findOrFail($child);
        $data = Data::validateAndCreate($request->all());

        $model->forceFill([
            'name' => $data->name,
            'username' => $data->username,
            'grade' => $data->grade,
        ])->save();

        return Child::from($model);
    }
}
