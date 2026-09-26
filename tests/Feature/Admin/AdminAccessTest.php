<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Middleware\CheckIfAdmin;
use App\Models\Card\CardSet;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\UserBlocking;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Вход в админку по admin.access, меню и CRUD — по правам.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    private function asModerator(): static
    {
        return $this->actingAs($this->moderator, 'backpack');
    }

    public function test_moderator_enters_admin_and_sees_only_allowed_sections_in_menu(): void
    {
        $this->asModerator()->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(backpack_url('user'), false)
            ->assertSee(backpack_url('section'), false)
            ->assertSee(backpack_url('card-set'), false)
            ->assertSee(backpack_url('test'), false)
            ->assertDontSee(backpack_url('role'), false)
            ->assertDontSee(backpack_url('teacher-verification'), false)
            ->assertDontSee(backpack_url('audit-log'), false);
    }

    public function test_admin_sees_every_section_in_menu(): void
    {
        $response = $this->actingAs($this->admin, 'backpack')->get('/admin/dashboard')->assertOk();

        foreach (['user', 'role', 'section', 'card-set', 'test', 'teacher-verification', 'audit-log'] as $segment) {
            $response->assertSee(backpack_url($segment), false);
        }
    }

    public function test_user_without_admin_access_is_logged_out_to_login_form(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher, 'backpack')->get('/admin/dashboard')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => CheckIfAdmin::DENIED]);
        $this->assertGuest('backpack');

        $this->actingAs($teacher, 'backpack')->getJson('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_access_alone_opens_only_the_dashboard(): void
    {
        $staff = User::factory()->create();
        $staff->givePermissionTo(PermissionName::adminAccess->value);

        $this->actingAs($staff, 'backpack')->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee(backpack_url('user'), false)
            ->assertDontSee(backpack_url('section'), false);

        foreach (['/admin/section', '/admin/card-set', '/admin/test', '/admin/user', '/admin/role', '/admin/teacher-verification', '/admin/audit-log'] as $url) {
            $this->actingAs($staff, 'backpack')->get($url)->assertForbidden();
        }
    }

    public function test_blocked_moderator_is_logged_out_of_admin(): void
    {
        app(UserBlocking::class)->block($this->moderator, $this->admin, 'Нарушение правил');

        $this->asModerator()->get('/admin/dashboard')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest('backpack');
    }

    public function test_moderator_manages_sections(): void
    {
        $section = Section::factory()->create();

        $this->asModerator()->get('/admin/section')->assertOk();
        $this->asModerator()
            ->post('/admin/section', ['name' => 'Астрофизика', '_save_action' => 'save_and_back'])
            ->assertRedirect();
        $this->asModerator()->delete("/admin/section/{$section->id}")->assertOk();

        $this->assertDatabaseHas('sections', ['name' => 'Астрофизика']);
        $this->assertSoftDeleted($section);
    }

    public function test_moderator_sees_only_editable_card_sets(): void
    {
        $catalog = CardSet::factory()->for($this->admin)->create();
        $personal = CardSet::factory()->for(User::factory()->create())->create();

        $this->asModerator()
            ->post('/admin/card-set/search', ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('recordsTotal', 1);

        $this->asModerator()->get("/admin/card-set/{$catalog->id}/edit")->assertOk();
        $this->asModerator()->get("/admin/card-set/{$personal->id}/edit")->assertNotFound();
        $this->asModerator()->delete("/admin/card-set/{$personal->id}")->assertNotFound();

        $this->assertNotSoftDeleted($personal);
    }

    public function test_moderator_views_but_does_not_manage_users(): void
    {
        $student = User::factory()->create();

        $this->asModerator()->get('/admin/user')->assertOk();
        $this->asModerator()->get("/admin/user/{$student->id}/show")->assertOk();
        $this->asModerator()->get('/admin/user/create')->assertForbidden();
        $this->asModerator()->get("/admin/user/{$student->id}/edit")->assertForbidden();
        $this->asModerator()->delete("/admin/user/{$student->id}")->assertForbidden();

        $this->assertNotSoftDeleted($student);
    }

    public function test_moderator_blocks_students_but_not_staff(): void
    {
        $student = User::factory()->create();
        $otherModerator = User::factory()->moderator()->create();

        $this->asModerator()->get("/admin/user/{$student->id}/block")->assertOk();
        $this->asModerator()->post("/admin/user/{$student->id}/block", ['reason' => 'Спам'])->assertRedirect('/admin/user');
        $this->asModerator()->get("/admin/user/{$otherModerator->id}/block")->assertForbidden();
        $this->asModerator()->get("/admin/user/{$this->admin->id}/block")->assertForbidden();

        $this->assertTrue($student->isBlocked());
        $this->assertFalse($otherModerator->isBlocked());
    }

    public function test_moderator_without_teachers_verify_has_no_teacher_queue(): void
    {
        $this->asModerator()->get('/admin/teacher-verification')->assertForbidden();
    }

    public function test_moderator_cannot_open_or_change_roles(): void
    {
        $role = Role::findByName(RoleName::student->value, RoleCatalog::GUARD);
        $custom = Role::create(['name' => 'assistant', 'guard_name' => RoleCatalog::GUARD]);

        $this->asModerator()->get('/admin/role')->assertForbidden();
        $this->asModerator()->post('/admin/role/search', ['draw' => 1, 'start' => 0, 'length' => 10])->assertForbidden();
        $this->asModerator()->get('/admin/role/create')->assertForbidden();
        $this->asModerator()->post('/admin/role', ['name' => 'hacker'])->assertForbidden();
        $this->asModerator()->get("/admin/role/{$role->id}/edit")->assertForbidden();
        $this->asModerator()->put("/admin/role/{$role->id}", [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => [PermissionName::rolesManage->value],
        ])->assertForbidden();
        $this->asModerator()->delete("/admin/role/{$custom->id}")->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'hacker']);
        $this->assertDatabaseHas('roles', ['id' => $custom->id]);
        $this->assertFalse($role->fresh()->hasPermissionTo(PermissionName::rolesManage->value));
    }

    public function test_moderator_cannot_assign_roles_even_with_users_manage(): void
    {
        $this->moderator->givePermissionTo(PermissionName::usersManage->value);
        $student = User::factory()->create();
        $moderatorRole = Role::findByName(RoleName::moderator->value, RoleCatalog::GUARD);

        $this->asModerator()->get("/admin/user/{$student->id}/edit")
            ->assertOk()
            ->assertDontSee('name="role_ids[]"', false);

        $this->asModerator()
            ->put("/admin/user/{$student->id}", [
                'id' => $student->id,
                'name' => 'Переименован',
                'email' => $student->email,
                'role_ids' => [$moderatorRole->id],
            ])
            ->assertSessionHasErrors('role_ids');

        // без поля ролей пользователь меняется, роли — нет
        $this->asModerator()
            ->put("/admin/user/{$student->id}", ['id' => $student->id, 'name' => 'Переименован', 'email' => $student->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Переименован', $student->fresh()->name);
        $this->assertSame([RoleName::student->value], $student->fresh()->getRoleNames()->all());
        // и себе роли не выдаст
        $this->asModerator()
            ->put("/admin/user/{$this->moderator->id}", [
                'id' => $this->moderator->id,
                'name' => $this->moderator->name,
                'email' => $this->moderator->email,
                'role_ids' => [Role::findByName(RoleName::admin->value, RoleCatalog::GUARD)->id],
            ])
            ->assertSessionHasErrors('role_ids');
        $this->assertFalse($this->moderator->fresh()->isAdmin());
    }

    public function test_user_without_permissions_is_denied_everywhere(): void
    {
        // вход в админку есть, прав на разделы нет
        $teacher = User::factory()->teacher()->create();
        $teacher->givePermissionTo(PermissionName::adminAccess->value);

        foreach (['/admin/section', '/admin/card-set', '/admin/test', '/admin/user'] as $url) {
            $this->actingAs($teacher, 'backpack')->get($url)->assertForbidden();
        }
    }
}
