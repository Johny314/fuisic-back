<?php

namespace App\Http\Controllers\Settings;

use App\Data\Settings\UserSettings;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Show extends Controller
{
    #[Get(
        path: Uri::settings,
        tag: Tag::settings,
        summary: 'Мои настройки',
        description: 'Только свои. У нового пользователя — значения по умолчанию. То же отдаётся в `/me` полем `settings`.',
    )]

    #[Response(200, UserSettings::class)]
    public function __invoke(Request $request): UserSettings
    {
        return UserSettings::fromUser($request->user());
    }
}
