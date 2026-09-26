<?php

namespace App\Http\Controllers\User;

use App\Data\User\User as Data;
use App\Enums\Uri;
use App\Models\User;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Store extends Controller
{
    #[Post(
        path: Uri::user,
        tag: Tag::user,
        summary: 'Новый пользователь',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    public function __invoke(Data $data): Data
    {
        Gate::authorize('create', User::class);

        $user = User::query()->create($data->toArray());

        return Data::from($user);
    }
}
