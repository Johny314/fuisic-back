<?php

namespace App\Http\Controllers\TeacherVerification;

use App\Data\User\TeacherVerification as Data;
use App\Enums\Uri;
use App\OpenApi\Get;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\TeacherVerificationService;
use App\Support\ContentAccess;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class Show extends Controller
{
    #[Get(
        path: Uri::teacher_verification,
        tag: Tag::teacher_verification,
        summary: 'Последняя заявка текущего учителя на статус «Проверенный учитель»',
    )]

    #[Response(200, Data::class)]
    #[OA\Response(response: 403, description: 'Пользователь не учитель')]
    #[OA\Response(response: 404, description: 'Заявок ещё не было')]
    public function __invoke(TeacherVerificationService $service): Data
    {
        $user = ContentAccess::requireUser();
        $service->abortUnlessTeacher($user);

        $verification = $user->latestTeacherVerification()->first();
        abort_unless($verification, 404, 'Заявок ещё не было');

        return Data::from($verification);
    }
}
