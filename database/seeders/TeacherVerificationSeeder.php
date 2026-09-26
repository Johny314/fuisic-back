<?php

namespace Database\Seeders;

use App\Enums\WorkplaceType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Демо-заявка в очереди «Проверенный учитель» (teacher2@fuisic.local).
 */
class TeacherVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->where('email', 'teacher2@fuisic.local')->first();

        $teacher?->teacherVerifications()->create([
            'full_name' => 'Смирнова Анна Сергеевна',
            'workplace_type' => WorkplaceType::school,
            'workplace_name' => 'Лицей № 239, Санкт-Петербург',
            'subjects' => ['Физика', 'Астрономия'],
            'link' => 'https://example.com/teachers/smirnova',
            'comment' => 'Готовлю к олимпиадам, хочу публиковать наборы задач.',
        ]);
    }
}
