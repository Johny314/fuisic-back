<?php

namespace App\Http\Controllers\Child;

use App\Data\Child\Child as Data;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Show extends Controller
{
    #[Get(
        path: Uri::children_id,
        tag: Tag::children,
        summary: 'Аккаунт ребёнка',
        description: 'Право `children.view`; чужой ребёнок — 404.',
    )]
    #[ModelId('child', 'id ребёнка')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Request $request, int $child): Data
    {
        return Data::from($request->user()->children()->findOrFail($child));
    }
}
