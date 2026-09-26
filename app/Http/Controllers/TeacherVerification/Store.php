<?php

namespace App\Http\Controllers\TeacherVerification;

use App\Data\User\TeacherVerification as Data;
use App\Enums\Uri;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\TeacherVerificationService;
use App\Support\ContentAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class Store extends Controller
{
    #[Post(
        path: Uri::teacher_verification,
        tag: Tag::teacher_verification,
        summary: 'Подать заявку на статус «Проверенный учитель»',
        description: 'Только для роли teacher. Новая заявка — если заявок не было или последняя отклонена либо статус отозван.',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Data::class)]
    #[OA\Response(response: 403, description: 'Пользователь не учитель')]
    #[OA\Response(response: 409, description: 'Заявка уже на рассмотрении или статус уже подтверждён')]
    #[OA\Response(response: 422, description: 'Ошибка валидации')]
    public function __invoke(Request $request, TeacherVerificationService $service): Data
    {
        $user = ContentAccess::requireUser();
        $service->abortUnlessTeacher($user);

        // Валидация после проверки роли: не-учителю — 403, а не ошибки полей
        $data = Data::validateAndCreate($request->all());
        $verification = $service->submit($user, $data->persistAttributes());

        return Data::from($verification->refresh());
    }
}
