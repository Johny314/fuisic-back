<?php

namespace App\Http\Controllers\Filters;

use App\Data\Filters\Difficulty as Data;
use App\Enums\Difficulty as filter;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class Difficulties extends Controller
{
    #[Get(
        path: Uri::difficulty,
        tag: Tag::filters,
        summary: 'Список доступных уровней сложности',
    )]
    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(): Collection
    {
        return Data::collect(
            collect(filter::cases())->map(fn (filter $case) => ['name' => $case->value])
        );
    }
}
