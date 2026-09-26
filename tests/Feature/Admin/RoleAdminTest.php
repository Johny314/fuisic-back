<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * CRUD ролей и назначение ролей в карточке пользователя.
 */
class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'backpack');
    }

    private static function permissionIds(PermissionName ...$permissions): array
    {
        return Permission::query()
            ->whereIn('name', array_map(fn (PermissionName $p) => $p->value, $permissions))
            ->pluck('id')
            ->all();
    }

    private static function roleId(RoleName|string $role): int
    {
        return Role::findByName($role instanceof RoleName ? $role->value : $role, RoleCatalog::GUARD)->id;
    }

    /** Запрос в API по Bearer-токену, как у приложения (без сессии админки и кэша guard). */
    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_role_pages_render_with_russian_labels(): void
    {
        $this->asAdmin()->get('/admin/role')->assertOk();
        $rows = collect($this->asAdmin()->post('/admin/role/search', ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonPath('recordsTotal', count(RoleName::cases()))
            ->json('data'))->flatten()->implode('');

        $this->assertStringContainsString('Модератор', $rows);
        $this->assertStringContainsString(PermissionName::usersBlock->label(), $rows);
        $this->assertStringContainsString('все (суперадмин)', $rows);
        // стартовые роли удалить нельзя — кнопки нет
        $this->assertStringNotContainsString('bp-button="delete"', $rows);

        $this->asAdmin()->get('/admin/role/create')
            ->assertOk()
            ->assertSee('name="permission_ids[]"', false)
            ->assertSee(PermissionName::rolesManage->label());
    }

    public function test_admin_creates_role_assigns_it_and_permissions_work_in_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('app')->plainTextToken;

        // прогреваем кэш прав spatie: права ещё нет
        $this->api($token)->postJson('/section', ['name' => 'Астрофизика'])->assertForbidden();

        $this->asAdmin()->post('/admin/role', [
            'name' => 'catalog_editor',
            'permission_ids' => self::permissionIds(PermissionName::adminAccess, PermissionName::catalogManage),
            '_save_action' => 'save_and_back',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $role = Role::findByName('catalog_editor', RoleCatalog::GUARD);
        $this->assertEqualsCanonicalizing(
            [PermissionName::adminAccess->value, PermissionName::catalogManage->value],
            $role->permissions->pluck('name')->all(),
        );

        $this->asAdmin()->put("/admin/user/{$user->id}", [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => [self::roleId(RoleName::student), $role->id],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['student', 'catalog_editor'], $user->fresh()->getRoleNames()->all());
        $this->api($token)->postJson('/section', ['name' => 'Астрофизика'])->assertCreated();
        $this->api($token)->getJson('/me')->assertJsonFragment(['catalog.manage']);

        // и в админку с новой ролью пускает
        // другой пользователь — новая сессия (AuthenticateSession Backpack сверяет хэш пароля)
        $this->flushSession();
        $this->actingAs($user->fresh(), 'backpack')->get('/admin/section')->assertOk();
    }

    public function test_editing_role_permissions_takes_effect_in_api_immediately(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('app')->plainTextToken;
        $role = Role::findByName(RoleName::student->value, RoleCatalog::GUARD);

        $this->api($token)->getJson('/children')->assertForbidden();

        $this->asAdmin()->put("/admin/role/{$role->id}", [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => self::permissionIds(PermissionName::childrenView),
        ])->assertSessionHasNoErrors();

        $this->api($token)->getJson('/children')->assertOk();

        // снятие всех галочек забирает право
        $this->asAdmin()->put("/admin/role/{$role->id}", ['id' => $role->id, 'name' => $role->name, 'permission_ids' => ''])
            ->assertSessionHasNoErrors();

        $this->api($token)->getJson('/children')->assertForbidden();
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public static function starterRoles(): array
    {
        return array_map(fn (RoleName $role) => [$role], RoleName::cases());
    }

    #[DataProvider('starterRoles')]
    public function test_starter_roles_cannot_be_deleted_or_renamed(RoleName $name): void
    {
        $role = Role::findByName($name->value, RoleCatalog::GUARD);

        $this->asAdmin()->delete("/admin/role/{$role->id}")->assertForbidden();
        $this->asAdmin()->put("/admin/role/{$role->id}", ['id' => $role->id, 'name' => 'renamed'])
            ->assertSessionHasErrors('name');

        $this->assertSame($name->value, $role->fresh()->name);
    }

    public function test_custom_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'assistant', 'guard_name' => RoleCatalog::GUARD]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->asAdmin()->delete("/admin/role/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertSame([RoleName::student->value], $user->fresh()->getRoleNames()->all());
    }

    public function test_role_name_is_validated(): void
    {
        $this->asAdmin()->post('/admin/role', ['name' => 'Модератор 2'])->assertSessionHasErrors('name');
        $this->asAdmin()->post('/admin/role', ['name' => RoleName::teacher->value])->assertSessionHasErrors('name');
    }

    public function test_admin_role_is_super_admin_without_stored_permissions(): void
    {
        $role = Role::findByName(RoleName::admin->value, RoleCatalog::GUARD);

        $this->asAdmin()->get("/admin/role/{$role->id}/edit")
            ->assertOk()
            ->assertSee('суперадмин')
            ->assertDontSee('name="permission_ids[]"', false);

        $this->asAdmin()->put("/admin/role/{$role->id}", [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => self::permissionIds(PermissionName::usersView),
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_admin_assigns_several_roles(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->get("/admin/user/{$user->id}/edit")
            ->assertOk()
            ->assertSee('name="role_ids[]"', false);

        $assign = fn (array $roles) => $this->asAdmin()->put("/admin/user/{$user->id}", [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => array_map(self::roleId(...), $roles),
        ])->assertSessionHasNoErrors();

        $assign([RoleName::teacher, RoleName::parent]);
        $this->assertEqualsCanonicalizing(['teacher', 'parent'], $user->fresh()->getRoleNames()->all());

        $assign([RoleName::parent]);
        $this->assertSame(['parent'], $user->fresh()->getRoleNames()->all());

        $assign([RoleName::admin, RoleName::teacher]);
        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_user_needs_at_least_one_role(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->put("/admin/user/{$user->id}", [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => '',
        ])->assertSessionHasErrors('role_ids');

        $this->assertSame(['student'], $user->fresh()->getRoleNames()->all());
    }

    public function test_admin_creates_user_with_roles(): void
    {
        $this->asAdmin()->post('/admin/user', [
            'name' => 'Модератор',
            'email' => 'new-moderator@example.com',
            'password' => 'Moderator-pass-123',
            'role_ids' => [self::roleId(RoleName::moderator)],
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new-moderator@example.com')->firstOrFail();
        $this->assertSame(['moderator'], $user->getRoleNames()->all());
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->asAdmin()->put("/admin/user/{$this->admin->id}", [
            'id' => $this->admin->id,
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role_ids' => [self::roleId(RoleName::teacher)],
        ])->assertSessionHasErrors('role_ids');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_only_admin_grants_admin_role(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo([
            PermissionName::adminAccess->value,
            PermissionName::usersView->value,
            PermissionName::usersManage->value,
            PermissionName::rolesManage->value,
        ]);
        $student = User::factory()->create();

        // roles.manage открывает раздел ролей, но admin в карточке пользователя не предлагается
        $this->actingAs($manager, 'backpack')->get('/admin/role')->assertOk();
        $this->actingAs($manager, 'backpack')->get("/admin/user/{$student->id}/edit")
            ->assertOk()
            ->assertSee('value="'.self::roleId(RoleName::teacher).'"', false)
            ->assertDontSee('value="'.self::roleId(RoleName::admin).'"', false);

        $this->actingAs($manager, 'backpack')->put("/admin/user/{$student->id}", [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'role_ids' => [self::roleId(RoleName::admin)],
        ])->assertSessionHasErrors('role_ids.0');
        $this->assertFalse($student->fresh()->isAdmin());

        // остальные роли — можно
        $this->actingAs($manager, 'backpack')->put("/admin/user/{$student->id}", [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'role_ids' => [self::roleId(RoleName::teacher)],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['teacher'], $student->fresh()->getRoleNames()->all());

        // администратора не-админ не редактирует вовсе
        $this->actingAs($manager, 'backpack')->get("/admin/user/{$this->admin->id}/edit")->assertForbidden();
    }

    public function test_users_manage_without_roles_manage_cannot_change_roles(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo([PermissionName::adminAccess->value, PermissionName::usersView->value, PermissionName::usersManage->value]);
        $student = User::factory()->create();

        $this->actingAs($manager, 'backpack')->get('/admin/role')->assertForbidden();
        $this->actingAs($manager, 'backpack')->put("/admin/user/{$student->id}", [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'role_ids' => [self::roleId(RoleName::teacher)],
        ])->assertSessionHasErrors('role_ids');

        $this->assertSame(['student'], $student->fresh()->getRoleNames()->all());
    }

    public function test_child_account_is_saved_without_email(): void
    {
        $parent = User::factory()->parent()->create();
        $child = User::factory()->child($parent)->create();

        $this->asAdmin()->put("/admin/user/{$child->id}", [
            'id' => $child->id,
            'name' => 'Маша',
            'email' => '',
            'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Маша', $child->fresh()->name);
        $this->assertNull($child->fresh()->email);

        // без логина email по-прежнему обязателен
        $student = User::factory()->create();
        $this->asAdmin()->put("/admin/user/{$student->id}", ['id' => $student->id, 'name' => 'Без почты', 'email' => ''])
            ->assertSessionHasErrors('email');
    }
}
