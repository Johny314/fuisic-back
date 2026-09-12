<?php

namespace App\Http\Controllers\CardSet;

use App\Data\Card\CardSet as Data;
use App\Enums\Uri;
use App\Models\Card\CardSet;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;

class Store extends Controller
{
    #[Post(
        path: Uri::card_set,
        tag: Tag::card_set,
        summary: 'Новый набор карточек',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    public function __invoke(Data $data): Data
    {
        $user = ContentAccess::requireUser();
        $card_set = CardSet::query()->create($data->persistAttributes($user->id));
        $card_set->load(['section', 'user']);

        return Data::from($card_set);
    }
}
