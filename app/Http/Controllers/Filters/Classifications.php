<?php

namespace App\Http\Controllers\Filters;

use App\Data\Filters\Classification as Data;
use App\Enums\Classifications as filter;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class Classifications extends Controller
{
    #[Get(
        path: Uri::classification,
        tag: Tag::filters,
        summary: 'Список доступных классов (1-4, 5-9, 10-11) ',
    )]
    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(): Collection
    {
        return Data::collect(
            collect(Filter::cases())->map(fn (Filter $case) => ['name' => $case->value])
        );
    }
}
