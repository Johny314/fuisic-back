<?php

namespace App\Data\User;

use App\Data\Data;
use App\Enums\TeacherVerificationStatus;
use App\Enums\WorkplaceType;
use App\Models\TeacherVerification as Model;
use App\OpenApi\Property;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Url;

/**
 * Заявка на «Проверенного учителя» — для самого учителя. Кто принял решение, не отдаётся.
 */
#[Schema(required: ['full_name', 'workplace_type', 'workplace_name', 'subjects'])]
class TeacherVerification extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id = null;

    #[Max(255)]
    #[Property(example: 'Иванова Мария Петровна')]
    public string $full_name;

    #[Property(type: 'string', enum: WorkplaceType::class, example: WorkplaceType::school)]
    public WorkplaceType $workplace_type;

    #[Max(255)]
    #[Property(description: 'Название школы или центра; для частной практики — город или формат', example: 'Школа № 57, Москва')]
    public string $workplace_name;

    /** @var list<string> */
    #[Min(1), Max(10)]
    #[Property(type: 'array', items: new Items(type: 'string', maxLength: 100), example: ['Физика', 'Астрономия'])]
    public array $subjects;

    #[Url('http', 'https'), Max(2048)]
    #[Property(description: 'Сайт или профиль (необязательно)', example: 'https://school57.ru/teachers/ivanova')]
    public ?string $link = null;

    #[Max(2000)]
    #[Property(example: 'Веду физику в 7–11 классах, готовлю к олимпиадам')]
    public ?string $comment = null;

    #[Property(type: 'string', enum: TeacherVerificationStatus::class, readOnly: true, example: TeacherVerificationStatus::pending)]
    public ?TeacherVerificationStatus $status = null;

    #[Property(readOnly: true, description: 'Комментарий проверяющего', example: 'Добавьте ссылку на страницу учителя на сайте школы')]
    public ?string $reviewer_comment = null;

    #[Property(readOnly: true, example: '2026-09-26T12:00:00+00:00')]
    public ?string $submitted_at = null;

    #[Property(readOnly: true, example: '2026-09-27T09:30:00+00:00')]
    public ?string $reviewed_at = null;

    public static function rules(): array
    {
        return [
            'subjects.*' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    public static function fromModel(Model $model): TeacherVerification
    {
        return static::from([
            'id' => $model->id,
            'full_name' => $model->full_name,
            'workplace_type' => $model->workplace_type,
            'workplace_name' => $model->workplace_name,
            'subjects' => $model->subjects,
            'link' => $model->link,
            'comment' => $model->comment,
            'status' => $model->status,
            'reviewer_comment' => $model->reviewer_comment,
            'submitted_at' => $model->created_at?->toIso8601String(),
            'reviewed_at' => $model->reviewed_at?->toIso8601String(),
        ]);
    }

    public function persistAttributes(): array
    {
        return [
            'full_name' => $this->full_name,
            'workplace_type' => $this->workplace_type,
            'workplace_name' => $this->workplace_name,
            'subjects' => array_values($this->subjects),
            'link' => $this->link,
            'comment' => $this->comment,
        ];
    }
}
