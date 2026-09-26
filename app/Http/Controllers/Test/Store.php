<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\Test as Data;
use App\Enums\Uri;
use App\Models\Test\Test;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Store extends Controller
{
    #[Post(
        path: Uri::test,
        tag: Tag::test,
        summary: 'Новый тест',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    public function __invoke(Data $data): Data
    {
        Gate::authorize('create', Test::class);
        $user = ContentAccess::requireUser();
        $test = Test::query()->create($data->persistAttributes($user->id));
        $test->load(['section', 'user']);

        return Data::from($test);
    }
}
