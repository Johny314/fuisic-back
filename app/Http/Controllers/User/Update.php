<?php

namespace App\Http\Controllers\User;

use App\Data\User\User as UserData;
use App\Data\User\UserUpdate as Data;
use App\Enums\Uri;
use App\Models\User;
use App\OpenApi\Parameter\ModelId;
use App\OpenApi\Put;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\MediaStorage;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class Update extends Controller
{
    #[Put(
        path: Uri::user_id,
        tag: Tag::user,
        summary: 'Обновить данные пользователя',
    )]
    #[ModelId('user', 'id пользователя')]
    #[RequestBody(Data::class)]

    #[Response(200, Data::class)]
    public function __invoke(User $user, Data $data, MediaStorage $media): UserData
    {
        ContentAccess::abortUnlessCanManageUser($user);

        $payload = [
            'name' => $data->name,
            'email' => $data->email,
        ];

        if ($data->password) {
            $payload['password'] = Hash::make($data->password);
        }

        if ($data->avatar_path) {
            if ($data->avatar_path !== $user->avatar_path) {
                $media->delete($user->avatar_path);
            }
            $payload['avatar_path'] = $data->avatar_path;
        }

        $user->update($payload);

        return UserData::from($user);
    }
}
