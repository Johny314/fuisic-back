<?php

namespace Database\Factories;

use App\Enums\TeacherVerificationStatus;
use App\Enums\WorkplaceType;
use App\Models\TeacherVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherVerificationFactory extends Factory
{
    protected $model = TeacherVerification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'full_name' => 'Иванова Мария Петровна',
            'workplace_type' => WorkplaceType::school->value,
            'workplace_name' => 'Школа № 57',
            'subjects' => ['Физика', 'Астрономия'],
            'link' => null,
            'comment' => null,
        ];
    }

    public function status(TeacherVerificationStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->value,
            'reviewed_at' => $status === TeacherVerificationStatus::pending ? null : now(),
        ]);
    }
}
