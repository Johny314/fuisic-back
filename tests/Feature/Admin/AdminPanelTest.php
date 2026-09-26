<?php

namespace Tests\Feature\Admin;

use App\Models\Card\CardSet;
use App\Models\Section;
use App\Models\Test\Task;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke-тесты Backpack: страницы админки рендерятся (ловит поломки при обновлении Backpack/Laravel).
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'dashboard' => ['/admin/dashboard'],
            'sections' => ['/admin/section'],
            'section create' => ['/admin/section/create'],
            'card sets' => ['/admin/card-set'],
            'card set create' => ['/admin/card-set/create'],
            'tests' => ['/admin/test'],
            'test create' => ['/admin/test/create'],
            'tasks' => ['/admin/task'],
            'users' => ['/admin/user'],
            'user create' => ['/admin/user/create'],
            'roles' => ['/admin/role'],
            'role create' => ['/admin/role/create'],
            'teacher verifications' => ['/admin/teacher-verification'],
            'account' => ['/admin/edit-account-info'],
        ];
    }

    #[DataProvider('pages')]
    public function test_admin_page_renders(string $url): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'backpack')
            ->get($url)
            ->assertOk();
    }

    public function test_list_operation_returns_rows(): void
    {
        $admin = User::factory()->admin()->create();
        CardSet::factory()->for($admin)->count(2)->create();
        Section::factory()->create();
        Test::factory()->for($admin)->create();

        Task::factory()->for(Test::factory()->for($admin))->single()->create();

        foreach (['card-set', 'section', 'test', 'task', 'user', 'role'] as $entity) {
            $this->actingAs($admin, 'backpack')
                ->post("/admin/{$entity}/search", ['draw' => 1, 'start' => 0, 'length' => 10])
                ->assertOk()
                ->assertJsonStructure(['data', 'recordsTotal']);
        }
    }

    public function test_validation_errors_survive_the_session_round_trip(): void
    {
        // Flash-ошибки (ViewErrorBag) и old input проходят через сессию (serialization: json)
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'backpack')
            ->from('/admin/section/create')
            ->post('/admin/section', ['name' => ''])
            ->assertRedirect('/admin/section/create')
            ->assertSessionHasErrors('name');

        $this->actingAs($admin, 'backpack')
            ->get('/admin/section/create')
            ->assertOk()
            ->assertSee('name', false);
    }

    public function test_admin_can_create_section_through_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'backpack')
            ->post('/admin/section', ['name' => 'Астрофизика', '_save_action' => 'save_and_back'])
            ->assertRedirect();

        $this->assertDatabaseHas('sections', ['name' => 'Астрофизика']);
    }

    public function test_admin_creates_user_with_hashed_password(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'backpack')
            ->post('/admin/user', [
                'name' => 'Новый учитель',
                'email' => 'new-teacher@example.com',
                'password' => 'Teacher-pass-123',
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new-teacher@example.com')->firstOrFail();
        $this->assertNotSame('Teacher-pass-123', $user->getAuthPassword());
        $this->assertTrue(Hash::check('Teacher-pass-123', $user->getAuthPassword()));
    }

    public function test_editing_user_without_password_keeps_it(): void
    {
        $user = User::factory()->create();
        $hash = $user->getAuthPassword();

        $this->actingAs(User::factory()->admin()->create(), 'backpack')
            ->put("/admin/user/{$user->id}", [
                'id' => $user->id,
                'name' => 'Переименован',
                'email' => $user->email,
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Переименован', $user->fresh()->name);
        $this->assertSame($hash, $user->fresh()->getAuthPassword());
    }

    public function test_card_set_form_validates_enums(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'backpack')
            ->post('/admin/card-set', [
                'name' => 'Набор',
                'subject' => 'не предмет',
                'section_id' => Section::factory()->create()->id,
                'user_id' => $admin->id,
            ])
            ->assertSessionHasErrors('subject');
    }

    public function test_admin_views_and_edits_question_without_touching_answers(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->for(Test::factory()->for($admin))->single(['Ньютон' => true, 'Джоуль' => false])->create();
        $number = Task::factory()->for(Test::factory()->for($admin))->number(9.8, 0.1, 'absolute', ['м/с²'])->create();

        $this->actingAs($admin, 'backpack')->get("/admin/task/{$task->id}/show")
            ->assertOk()->assertSee('Один вариант')->assertSee('Ньютон')->assertSee('Джоуль');
        $this->actingAs($admin, 'backpack')->get("/admin/task/{$number->id}/show")->assertOk()->assertSee('м/с²');
        $this->actingAs($admin, 'backpack')->get("/admin/task/{$task->id}/edit")->assertOk();

        $this->actingAs($admin, 'backpack')
            ->put("/admin/task/{$number->id}", [
                'id' => $number->id,
                'problem_statement' => 'Ускорение $g$?',
                'points' => 3,
                'explanation' => 'Разбор',
                'shuffle_options' => 0,
            ])
            ->assertSessionHasNoErrors();

        $number->refresh();
        $this->assertSame('Ускорение $g$?', $number->problem_statement);
        $this->assertSame(3, $number->points);
        $this->assertSame(9.8, $number->settings['value']);
        $this->assertSame(0.1, $number->settings['tolerance']);
        $this->assertSame(['м/с²'], $number->settings['units']);
    }

    public function test_student_cannot_open_admin(): void
    {
        // CheckIfAdmin уводит пользователя без admin.access на форму входа
        $this->actingAs(User::factory()->create(), 'backpack')
            ->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }
}
