<?php

namespace App\Http\Controllers\Test;

use App\Data\Test\Test as Data;
use App\Enums\Uri;
use App\Models\Test\Test;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class Update extends Controller
{
    #[Put(
        path: Uri::test_id,
        tag: Tag::test,
        summary: 'Обновить данные теста',
    )]
    #[ModelId('test', 'id теста')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(Test $test, Data $data): Data
    {
        Gate::authorize('update', $test);
        $test->update($data->persistAttributes());
        $test->load(['section', 'user']);

        return Data::from($test);
    }
}
