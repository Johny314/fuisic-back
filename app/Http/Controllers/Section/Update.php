<?php

namespace App\Http\Controllers\Section;

use App\Data\Section as Data;
use App\Enums\Uri;
use App\Models\Section;
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
        path: Uri::section_id,
        tag: Tag::section,
        summary: 'Обновить данные раздела',
    )]
    #[ModelId('section', 'id раздела')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(Section $section, Data $data): Data
    {
        Gate::authorize('update', $section);

        $section->update($data->toArray());

        return Data::from($section);
    }
}
