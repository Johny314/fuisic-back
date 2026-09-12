<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\Test as Data;
use App\Enums\Uri;
use App\Models\Test\Test;
use App\OpenApi\Get;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Response\NotFound;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;

class Show extends Controller
{
    #[Get(
        path: Uri::test_id,
        tag: Tag::test,
        summary: 'Вывести тест по его id',
    )]
    #[ModelId('test', 'id теста')]

    #[Response(200, Data::class)]
    #[Response(404, NotFound::class)]
    public function __invoke(Test $test): Data
    {
        ContentAccess::abortUnlessCanViewTest($test);
        $test->load(['section', 'user']);

        return Data::from($test);
    }
}
