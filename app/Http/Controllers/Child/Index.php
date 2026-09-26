<?php

namespace App\Http\Controllers\Child;

use App\Data\Child\Child as Data;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\IndexResponse;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\DataCollection;

class Index extends Controller
{
    #[Get(
        path: Uri::children,
        tag: Tag::children,
        summary: 'Мои дети',
        description: 'Право `children.view`.',
    )]

    #[IndexResponse(Data::class, description: 'Аккаунты детей текущего родителя')]
    public function __invoke(Request $request): DataCollection
    {
        return Data::collect($request->user()->children()->orderBy('name')->get(), DataCollection::class);
    }
}
