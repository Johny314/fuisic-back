<?php

namespace App\Http\Controllers\Settings;

use App\Data\Settings\UserSettings;
use App\Data\Settings\UserSettingsUpdate as Data;
use App\Enums\Uri;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class Update extends Controller
{
    #[Put(
        path: Uri::settings,
        tag: Tag::settings,
        summary: 'Изменить мои настройки',
        description: 'Частичное обновление: меняются только переданные поля.',
    )]
    #[RequestBody(Data::class)]

    #[Response(200, UserSettings::class)]
    #[OA\Response(response: 422, description: 'Ошибка валидации')]
    public function __invoke(Request $request): UserSettings
    {
        $user = $request->user();
        $data = Data::validateAndCreate($request->all());

        // строка создаётся при первом сохранении; createOrFirst переживает гонку двух запросов
        $settings = $user->settings()->createOrFirst();
        $settings->update($data->toArray());

        return UserSettings::fromUser($user->setRelation('settings', $settings));
    }
}
