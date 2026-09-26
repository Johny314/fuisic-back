<?php

namespace Tests\Feature\Auth;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\RoleCatalog;
use Fuisic\Auth\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const string PASSWORD = 'Secret-password-123';

    public function test_migration_creates_starting_roles_and_permissions(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(RoleName::cases(), 'value'),
            Role::query()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['admin.access', 'catalog.manage', 'catalog.review', 'formulas.manage', 'users.view', 'users.block'],
            Role::findByName('moderator')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['children.manage', 'children.view'],
            Role::findByName('parent')->permissions->pluck('name')->all(),
        );
    }

    public function test_reinstalling_the_catalog_keeps_permissions_edited_by_admin(): void
    {
        Role::findByName('moderator')->revokePermissionTo(PermissionName::usersBlock->value);
        Role::findByName('teacher')->givePermissionTo(PermissionName::formulasManage->value);

        RoleCatalog::install();

        $this->assertFalse(Role::findByName('moderator')->hasPermissionTo(PermissionName::usersBlock->value));
        $this->assertTrue(Role::findByName('teacher')->hasPermissionTo(PermissionName::formulasManage->value));
    }

    public function test_permission_changes_apply_without_code_changes(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->assertFalse($teacher->can(PermissionName::formulasManage->value));

        Role::findByName('teacher')->givePermissionTo(PermissionName::formulasManage->value);

        $this->assertTrue($teacher->fresh()->can(PermissionName::formulasManage->value));
    }

    public function test_admin_is_super_admin_and_moderator_is_limited(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }

        $this->assertTrue($moderator->can(PermissionName::adminAccess->value));
        $this->assertFalse($moderator->can(PermissionName::rolesManage->value));
        $this->assertFalse($moderator->can(PermissionName::auditView->value));
    }

    public function test_data_migration_assigns_roles_by_user_type(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $deleted = User::factory()->teacher()->create();
        $deleted->delete();
        DB::table('model_has_roles')->delete();

        $migration = require database_path('migrations/2026_09_26_090648_assign_roles_from_user_type.php');
        $migration->up();
        $migration->up(); // повторный запуск не дублирует роли

        $this->assertSame(['admin'], $admin->fresh()->getRoleNames()->all());
        $this->assertSame(['teacher'], $teacher->fresh()->getRoleNames()->all());
        $this->assertSame(['student'], $student->fresh()->getRoleNames()->all());
        $this->assertSame(['teacher'], User::withTrashed()->find($deleted->id)->getRoleNames()->all());
        $this->assertSame(4, DB::table('model_has_roles')->count());
    }

    public function test_changing_user_type_swaps_only_the_legacy_role(): void
    {
        $user = User::factory()->parent()->create();
        $user->assignRole(RoleName::student->value);

        $user->update(['user_type' => 'teacher']);

        $this->assertEqualsCanonicalizing(['parent', 'teacher'], $user->fresh()->getRoleNames()->all());
    }

    public function test_user_created_without_role_gets_one_from_user_type(): void
    {
        $user = User::query()->create(['name' => 'Без роли', 'email' => 'plain@example.com', 'password' => 'x']);

        $this->assertSame(['student'], $user->getRoleNames()->all());
    }

    #[DataProvider('registrableRoles')]
    public function test_registration_assigns_chosen_role(string $role, string $userType): void
    {
        $this->register(['email' => "{$role}@example.com", 'role' => $role])->assertCreated();

        $user = User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $this->assertSame([$role], $user->getRoleNames()->all());
        $this->assertSame($userType, $user->user_type->value);
    }

    public static function registrableRoles(): array
    {
        return [
            'student' => ['student', 'student'],
            'teacher' => ['teacher', 'teacher'],
            'parent' => ['parent', 'student'],
        ];
    }

    public function test_registration_without_role_makes_a_student(): void
    {
        $this->register(['email' => 'default@example.com'])->assertCreated();

        $this->assertSame(
            ['student'],
            User::query()->where('email', 'default@example.com')->firstOrFail()->getRoleNames()->all(),
        );
    }

    #[DataProvider('staffRoles')]
    public function test_registration_cannot_grant_staff_role(string $role): void
    {
        $this->register(['email' => 'staff@example.com', 'role' => $role])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'staff@example.com']);
    }

    public static function staffRoles(): array
    {
        return ['admin' => ['admin'], 'moderator' => ['moderator']];
    }

    public function test_legacy_user_type_on_registration_is_ignored(): void
    {
        $this->register(['email' => 'legacy@example.com', 'user_type' => 'admin'])->assertCreated();

        $user = User::query()->where('email', 'legacy@example.com')->firstOrFail();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertSame('student', $user->user_type->value);
    }

    public function test_new_oauth_user_gets_default_role(): void
    {
        config(['fuisic-auth.oauth.providers.yandex.enabled' => true]);
        $state = app(AuthTokenService::class)->createOAuthState([
            'provider' => 'yandex', 'intent' => 'login', 'user_id' => null, 'nonce' => 'n',
        ]);
        $socialiteUser = (new SocialiteUser)->map([
            'id' => '42', 'name' => 'Яндекс Пользователь', 'email' => 'oauth@example.com', 'avatar' => null,
        ]);
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('yandex')->andReturn($driver);

        $this->getJson('/oauth/yandex/callback?state='.urlencode($state))->assertOk();

        $this->assertSame(
            ['student'],
            User::query()->where('email', 'oauth@example.com')->firstOrFail()->getRoleNames()->all(),
        );
    }

    public function test_me_returns_roles_and_permissions(): void
    {
        Sanctum::actingAs(User::factory()->parent()->create());

        $this->getJson('/me')
            ->assertOk()
            ->assertJsonPath('roles', ['parent'])
            ->assertJsonPath('permissions', ['children.manage', 'children.view'])
            ->assertJsonPath('teacher_verified', false);
    }

    public function test_me_lists_all_permissions_for_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $permissions = collect(PermissionName::cases())->pluck('value')->sort()->values()->all();

        $this->getJson('/me')
            ->assertOk()
            ->assertJsonPath('roles', ['admin'])
            ->assertJsonPath('permissions', $permissions);
    }

    public function test_me_shows_teacher_verification_status(): void
    {
        $teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/me')->assertJsonPath('teacher_verified', false)->assertJsonPath('permissions', []);

        $teacher->givePermissionTo(PermissionName::catalogSubmit->value);

        $this->getJson('/me')
            ->assertJsonPath('teacher_verified', true)
            ->assertJsonPath('permissions', ['catalog.submit']);
    }

    private function register(array $overrides): TestResponse
    {
        return $this->postJson('/register', array_merge([
            'name' => 'Новый пользователь',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides));
    }
}
