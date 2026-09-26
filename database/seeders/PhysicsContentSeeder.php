<?php

namespace Database\Seeders;

use App\Enums\Classifications;
use App\Enums\Difficulty;
use App\Enums\RoleName;
use App\Enums\Subject;
use App\Models\Card\Card;
use App\Models\Card\CardSet;
use App\Models\Section;
use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhysicsContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role(RoleName::admin->value)->orderBy('id')->first();

        if (! $admin) {
            $this->command?->warn('Admin not found. Run UserSeeder first.');

            return;
        }

        $sections = Section::query()->pluck('id', 'name');

        $this->seedCardSets($admin->id, $sections);
        $this->seedTests($admin->id, $sections);
    }

    private function seedCardSets(int $userId, $sections): void
    {
        $sets = [
            [
                'name' => 'Основы кинематики',
                'section' => 'Кинематика',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::easy->value,
                'cards' => [
                    ['front' => 'Что такое путь?', 'back' => 'Физическая величина, равная длине траектории движения тела.', 'desc' => 'Базовое определение из §1'],
                    ['front' => 'Что такое перемещение?', 'back' => 'Вектор, соединяющий начальное и конечное положение тела.', 'desc' => 'Отличается от пути при криволинейном движении'],
                    ['front' => 'Формула скорости при РПД', 'back' => 'v = s / t', 'desc' => 'Равномерное прямолинейное движение'],
                    ['front' => 'Единица СИ ускорения', 'back' => 'м/с²', 'desc' => 'Метр на секунду в квадрате'],
                    ['front' => 'Чем скорость отличается от быстроты?', 'back' => 'Скорость — вектор, быстрота — модуль скорости.', 'desc' => 'Важно для векторных задач'],
                    ['front' => 'Ускорение свободного падения g', 'back' => '≈ 9,8 м/с²', 'desc' => 'Приближённо 10 м/с² в школьных задачах'],
                    ['front' => 'Формула пути при равноускоренном движении', 'back' => 's = v₀t + at²/2', 'desc' => 'При v₀ = 0: s = at²/2'],
                    ['front' => 'График зависимости v(t) при РПД', 'back' => 'Горизонтальная прямая', 'desc' => 'Скорость постоянна'],
                ],
            ],
            [
                'name' => 'Законы Ньютона',
                'section' => 'Динамика и законы Ньютона',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::medium->value,
                'cards' => [
                    ['front' => 'Первый закон Ньютона', 'back' => 'Тело сохраняет покой или равномерное прямолинейное движение, если на него не действуют силы.', 'desc' => 'Закон инерции'],
                    ['front' => 'Второй закон Ньютона', 'back' => 'F = ma', 'desc' => 'Сила равна произведению массы на ускорение'],
                    ['front' => 'Третий закон Ньютона', 'back' => 'Силы действия и противодействия равны по модулю и противоположны по направлению.', 'desc' => 'Действуют на разные тела'],
                    ['front' => 'Единица СИ силы', 'back' => 'Ньютон (Н)', 'desc' => '1 Н = 1 кг·м/с²'],
                    ['front' => 'Сила тяжести', 'back' => 'F = mg', 'desc' => 'Направлена вертикально вниз'],
                    ['front' => 'Сила упругости (закон Гука)', 'back' => 'F = kx', 'desc' => 'x — деформация пружины'],
                ],
            ],
            [
                'name' => 'Энергия и работа',
                'section' => 'Законы сохранения',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::medium->value,
                'cards' => [
                    ['front' => 'Работа силы', 'back' => 'A = Fs cos α', 'desc' => 'α — угол между F и s'],
                    ['front' => 'Кинетическая энергия', 'back' => 'Eк = mv²/2', 'desc' => 'Энергия движения'],
                    ['front' => 'Потенциальная энергия в поле тяжести', 'back' => 'Eп = mgh', 'desc' => 'h — высота над нулевым уровнем'],
                    ['front' => 'Закон сохранения механической энергии', 'back' => 'Eк + Eп = const (без потерь)', 'desc' => 'В замкнутой системе без трения'],
                    ['front' => 'Мощность', 'back' => 'P = A/t = Fv', 'desc' => 'Единица — ватт (Вт)'],
                ],
            ],
            [
                'name' => 'Закон Ома',
                'section' => 'Электричество и магнетизм',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::hard->value,
                'cards' => [
                    ['front' => 'Закон Ома для участка цепи', 'back' => 'I = U/R', 'desc' => 'Основная формула электричества'],
                    ['front' => 'Сопротивление проводника', 'back' => 'R = ρl/S', 'desc' => 'ρ — удельное сопротивление'],
                    ['front' => 'Последовательное соединение', 'back' => 'R = R₁ + R₂ + ...', 'desc' => 'Ток одинаков'],
                    ['front' => 'Параллельное соединение', 'back' => '1/R = 1/R₁ + 1/R₂ + ...', 'desc' => 'Напряжение одинаково'],
                    ['front' => 'Работа электрического тока', 'back' => 'A = UIt', 'desc' => 'Мощность: P = UI'],
                ],
            ],
            [
                'name' => 'Оптика: отражение и преломление',
                'section' => 'Оптика',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::medium->value,
                'cards' => [
                    ['front' => 'Закон отражения света', 'back' => 'Угол падения равен углу отражения.', 'desc' => 'Относительно нормали'],
                    ['front' => 'Закон преломления (Снеллиус)', 'back' => 'n₁ sin α = n₂ sin β', 'desc' => 'n — показатель преломления'],
                    ['front' => 'Фокусное расстояние линзы', 'back' => '1/F = 1/d + 1/f', 'desc' => 'Формула тонкой линзы'],
                    ['front' => 'Увеличение линзы', 'back' => 'Γ = f/d', 'desc' => 'd — расстояние до предмета'],
                ],
            ],
        ];

        foreach ($sets as $setData) {
            $cardSet = CardSet::query()->create([
                'name' => $setData['name'],
                'subject' => Subject::physics->value,
                'section_id' => $sections[$setData['section']],
                'class' => $setData['class'],
                'difficulty' => $setData['difficulty'],
                'user_id' => $userId,
            ]);

            foreach ($setData['cards'] as $cardData) {
                Card::query()->create([
                    'card_set_id' => $cardSet->id,
                    'front_text' => $cardData['front'],
                    'back_text' => $cardData['back'],
                    'description' => $cardData['desc'],
                ]);
            }
        }
    }

    private function seedTests(int $userId, $sections): void
    {
        $tests = [
            [
                'name' => 'Равномерное движение',
                'section' => 'Кинематика',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::easy->value,
                'tasks' => [
                    ['q' => 'Автомобиль за 2 ч проехал 120 км. Найдите скорость (км/ч).', 'a' => '60'],
                    ['q' => 'Пешеход прошёл 1,5 км за 20 мин. Найдите скорость (м/с).', 'a' => '1.25'],
                    ['q' => 'Поезд движется со скоростью 72 км/ч. Сколько км он проедет за 15 мин?', 'a' => '18'],
                    ['q' => 'Велосипедист едет 36 км за 3 ч. Его скорость (км/ч)?', 'a' => '12'],
                    ['q' => 'Тело движется 500 м за 100 с. Скорость (м/с)?', 'a' => '5'],
                ],
            ],
            [
                'name' => 'Равноускоренное движение',
                'section' => 'Кинематика',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::medium->value,
                'tasks' => [
                    ['q' => 'Тело из покоя за 4 с разогналось до 20 м/с. Найдите ускорение (м/с²).', 'a' => '5'],
                    ['q' => 'Автомобиль с ускорением 2 м/с² разгоняется 10 с. Какой путь он прошёл (м)?', 'a' => '100'],
                    ['q' => 'Тело падает с высоты 45 м (g=10). Время падения (с)?', 'a' => '3'],
                    ['q' => 'Скорость тела через 5 с при a=3 м/с², v₀=0 (м/с)?', 'a' => '15'],
                ],
            ],
            [
                'name' => 'Законы Ньютона',
                'section' => 'Динамика и законы Ньютона',
                'class' => Classifications::second->value,
                'difficulty' => Difficulty::medium->value,
                'tasks' => [
                    ['q' => 'Какова сила (Н), если m=5 кг, a=4 м/с²?', 'a' => '20'],
                    ['q' => 'Сила тяжести тела массой 3 кг (g=10, Н)?', 'a' => '30'],
                    ['q' => 'Тело массой 10 кг под действием силы 50 Н. Ускорение (м/с²)?', 'a' => '5'],
                    ['q' => 'Два тела m₁=2 кг, m₂=3 кг. Сила F=10 Н. Ускорение системы (м/с²)?', 'a' => '2'],
                ],
            ],
            [
                'name' => 'Закон сохранения энергии',
                'section' => 'Законы сохранения',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::medium->value,
                'tasks' => [
                    ['q' => 'Тело массой 2 кг движется со скоростью 3 м/с. Кинетическая энергия (Дж)?', 'a' => '9'],
                    ['q' => 'Тело массой 4 кг на высоте 5 м (g=10). Потенциальная энергия (Дж)?', 'a' => '200'],
                    ['q' => 'Мяч массой 0,5 кг брошен со скоростью 6 м/с. Eк (Дж)?', 'a' => '9'],
                ],
            ],
            [
                'name' => 'Закон Ома',
                'section' => 'Электричество и магнетизм',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::hard->value,
                'tasks' => [
                    ['q' => 'R=10 Ом, U=5 В. Сила тока (А)?', 'a' => '0.5'],
                    ['q' => 'R₁=4 Ом, R₂=6 Ом последовательно. Общее R (Ом)?', 'a' => '10'],
                    ['q' => 'U=12 В, I=2 А. Сопротивление (Ом)?', 'a' => '6'],
                    ['q' => 'R=20 Ом, I=0,3 А. Напряжение (В)?', 'a' => '6'],
                    ['q' => 'Два резистора 6 Ом параллельно. R (Ом)?', 'a' => '3'],
                ],
            ],
            [
                'name' => 'Колебания и волны',
                'section' => 'Механические колебания и волны',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::medium->value,
                'tasks' => [
                    ['q' => 'Период колебаний T=0,5 с. Частота (Гц)?', 'a' => '2'],
                    ['q' => 'Волна: λ=2 м, v=4 м/с. Частота (Гц)?', 'a' => '2'],
                    ['q' => 'Маятник: T=2 с. Частота (Гц)?', 'a' => '0.5'],
                ],
            ],
            [
                'name' => 'Термодинамика',
                'section' => 'Молекулярная физика и термодинамика',
                'class' => Classifications::third->value,
                'difficulty' => Difficulty::hard->value,
                'tasks' => [
                    ['q' => 'Количество теплоты: c=4200, m=2 кг, ΔT=10. Q (кДж)?', 'a' => '84'],
                    ['q' => 'p=100 кПа, V=0,01 м³. Работа газа при изобарном расширении (Дж)?', 'a' => '1000'],
                    ['q' => 'T=27°C. Температура в Кельвинах?', 'a' => '300'],
                ],
            ],
        ];

        foreach ($tests as $testData) {
            $test = Test::query()->create([
                'name' => $testData['name'],
                'subject' => Subject::physics->value,
                'section_id' => $sections[$testData['section']],
                'class' => $testData['class'],
                'difficulty' => $testData['difficulty'],
                'user_id' => $userId,
            ]);

            foreach ($testData['tasks'] as $taskData) {
                Task::query()->create([
                    'test_id' => $test->id,
                    'problem_statement' => $taskData['q'],
                    'answer' => $taskData['a'],
                ]);
            }
        }
    }
}
