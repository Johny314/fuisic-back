<?php

namespace App\Http\Controllers\User;

use App\Data\User\User as Data;
use App\Enums\Uri;
use App\Models\User;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Show extends Controller
{
    #[Get(
        path: Uri::user_id,
        tag: Tag::user,
        summary: 'Вывести пользователя по его id',
    )]
    #[ModelId('user', 'id пользователя')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(User $user): Data
    {
        Gate::forUser(ContentAccess::requireUser())->authorize('view', $user);

        return Data::from($user);
    }
}
