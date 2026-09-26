<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Администратор',
            'email' => 'admin@fuisic.local',
        ]);

        User::factory()->moderator()->create([
            'name' => 'Модератор',
            'email' => 'moderator@fuisic.local',
        ]);

        $parent = User::factory()->parent()->create([
            'name' => 'Ольга Петрова',
            'email' => 'parent@fuisic.local',
        ]);

        // ребёнок входит по логину без email
        User::factory()->child($parent)->create([
            'name' => 'Маша Петрова',
            'username' => 'masha',
            'grade' => 7,
        ]);

        User::factory()->teacher()->create([
            'name' => 'Пётр Иванов',
            'email' => 'teacher@fuisic.local',
        ]);

        User::factory()->teacher()->create([
            'name' => 'Анна Смирнова',
            'email' => 'teacher2@fuisic.local',
        ]);

        $students = [
            ['name' => 'Иван Петров', 'email' => 'ivan@student.ru'],
            ['name' => 'Мария Козлова', 'email' => 'maria@student.ru'],
            ['name' => 'Алексей Сидоров', 'email' => 'alex@student.ru'],
            ['name' => 'Елена Волкова', 'email' => 'elena@student.ru'],
            ['name' => 'Дмитрий Орлов', 'email' => 'dmitry@student.ru'],
        ];

        foreach ($students as $student) {
            User::factory()->create([
                'name' => $student['name'],
                'email' => $student['email'],
            ]);
        }
    }
}
