<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEvent;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\TeacherVerificationStatus as Status;
use App\Models\Activity;
use App\Models\Card\CardSet;
use App\Models\Role;
use App\Models\Section;
use App\Models\TeacherVerification;
use App\Models\Test\Test;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\UserBlocking;
use App\Support\RoleCatalog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Журнал действий персонала: что пишется (с автором, IP и «было / стало»), чего нет, экран и очистка.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/admin/audit-log';

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

    private static function roleId(RoleName $role): int
    {
        return Role::findByName($role->value, RoleCatalog::GUARD)->id;
    }

    private static function permissionIds(PermissionName ...$permissions): array
    {
        return Permission::query()
            ->whereIn('name', array_map(fn (PermissionName $p) => $p->value, $permissions))
            ->pluck('id')
            ->all();
    }

    /** Последняя запись журнала с этим действием над объектом. */
    private function entry(AuditEvent $event, ?object $subject = null): Activity
    {
        $query = Activity::query()->where('event', $event->value)->latest('id');

        if ($subject) {
            $query->where('subject_type', $subject::class)->where('subject_id', $subject->getKey());
        }

        $entry = $query->first();
        $this->assertNotNull($entry, "Нет записи «{$event->label()}» в журнале");

        return $entry;
    }

    private function assertCausedByAdmin(Activity $entry): void
    {
        $this->assertSame($this->admin->id, (int) $entry->causer_id);
        $this->assertSame(User::class, $entry->causer_type);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertSame(AuditLog::LOG_NAME, $entry->log_name);
    }

    private function search(string $query = ''): string
    {
        return json_encode($this->asAdmin()
            ->post(self::URL.'/search'.$query, ['draw' => 1, 'start' => 0, 'length' => 100])
            ->assertOk()
            ->json('data'), JSON_UNESCAPED_UNICODE);
    }

    public function test_role_create_update_permissions_and_delete_are_logged(): void
    {
        $this->asAdmin()->post('/admin/role', [
            'name' => 'assistant',
            'permission_ids' => self::permissionIds(PermissionName::adminAccess, PermissionName::usersView),
        ])->assertSessionHasNoErrors();
        $role = Role::findByName('assistant', RoleCatalog::GUARD);

        $created = $this->entry(AuditEvent::created, $role);
        $this->assertCausedByAdmin($created);
        $this->assertSame('assistant', $created->attribute_changes['attributes']['name']);

        $granted = $this->entry(AuditEvent::rolePermissions, $role);
        $this->assertCausedByAdmin($granted);
        $this->assertSame([], $granted->attribute_changes['old']['permissions']);
        $this->assertSame(['admin.access', 'users.view'], $granted->attribute_changes['attributes']['permissions']);

        $this->asAdmin()->put("/admin/role/{$role->id}", [
            'id' => $role->id,
            'name' => 'helper',
            'permission_ids' => self::permissionIds(PermissionName::adminAccess, PermissionName::catalogManage),
        ])->assertSessionHasNoErrors();

        $renamed = $this->entry(AuditEvent::updated, $role);
        $this->assertSame(['name' => 'assistant'], $renamed->attribute_changes['old']);
        $this->assertSame(['name' => 'helper'], $renamed->attribute_changes['attributes']);

        $changed = $this->entry(AuditEvent::rolePermissions, $role);
        $this->assertSame(['admin.access', 'users.view'], $changed->attribute_changes['old']['permissions']);
        $this->assertSame(['admin.access', 'catalog.manage'], $changed->attribute_changes['attributes']['permissions']);
        $this->assertSame(['catalog.manage'], $changed->properties['added']);
        $this->assertSame(['users.view'], $changed->properties['removed']);

        // без изменений прав — новой записи нет
        $count = Activity::query()->count();
        $this->asAdmin()->put("/admin/role/{$role->id}", [
            'id' => $role->id,
            'name' => 'helper',
            'permission_ids' => self::permissionIds(PermissionName::adminAccess, PermissionName::catalogManage),
        ])->assertSessionHasNoErrors();
        $this->assertSame($count, Activity::query()->count());

        $this->asAdmin()->delete("/admin/role/{$role->id}")->assertOk();
        $deleted = $this->entry(AuditEvent::deleted, $role);
        $this->assertCausedByAdmin($deleted);
        $this->assertSame('helper', $deleted->attribute_changes['old']['name']);
    }

    public function test_role_assignment_is_logged(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->put("/admin/user/{$user->id}", [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => [self::roleId(RoleName::teacher), self::roleId(RoleName::parent)],
        ])->assertSessionHasNoErrors();

        $entry = $this->entry(AuditEvent::userRoles, $user);
        $this->assertCausedByAdmin($entry);
        $this->assertSame(['student'], $entry->attribute_changes['old']['roles']);
        $this->assertSame(['parent', 'teacher'], $entry->attribute_changes['attributes']['roles']);
    }

    public function test_block_and_unblock_are_logged(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->post("/admin/user/{$user->id}/block", [
            'reason' => 'Спам',
            'comment' => 'Жалобы',
        ])->assertRedirect();

        $blocked = $this->entry(AuditEvent::blocked, $user);
        $this->assertCausedByAdmin($blocked);
        $this->assertSame(['reason' => 'Спам', 'comment' => 'Жалобы', 'until' => null], $blocked->attribute_changes['attributes']);
        $this->assertSame($user->activeBlock()->id, $blocked->properties['block_id']);

        $this->asAdmin()->post("/admin/user/{$user->id}/unblock")->assertRedirect();

        $unblocked = $this->entry(AuditEvent::unblocked, $user);
        $this->assertCausedByAdmin($unblocked);
        $this->assertSame('Спам', $unblocked->attribute_changes['old']['reason']);
        $this->assertNotNull($unblocked->attribute_changes['attributes']['unblocked_at']);
    }

    public function test_automatic_unblock_is_logged_as_system(): void
    {
        $user = User::factory()->create();
        app(UserBlocking::class)->block($user, $this->admin, 'На час', until: now()->addHour());

        $this->travel(2)->hours();
        $this->artisan('users:unblock-expired')->assertSuccessful();

        $entry = $this->entry(AuditEvent::unblocked, $user);
        $this->assertNull($entry->causer_id);
        $this->assertSame('Блокировка снята по сроку', $entry->description);
        $this->assertTrue($entry->properties['expired']);
        $this->assertSame('система', $entry->causerLabel());

        // повторный запуск ничего не пишет
        $this->artisan('users:unblock-expired')->assertSuccessful();
        $this->assertSame(1, Activity::query()->where('event', AuditEvent::unblocked->value)->count());
    }

    public function test_teacher_verification_decisions_are_logged(): void
    {
        Notification::fake();
        $approved = TeacherVerification::factory()->create();
        $rejected = TeacherVerification::factory()->create();

        $this->asAdmin()->post("/admin/teacher-verification/{$approved->id}/approve", ['reviewer_comment' => 'Добро пожаловать'])
            ->assertRedirect();
        $this->asAdmin()->post("/admin/teacher-verification/{$rejected->id}/reject", ['reviewer_comment' => 'Нет ссылки'])
            ->assertRedirect();
        $this->asAdmin()->post("/admin/teacher-verification/{$approved->id}/revoke", ['reviewer_comment' => 'Жалобы'])
            ->assertRedirect();

        $approval = $this->entry(AuditEvent::teacherApproved, $approved);
        $this->assertCausedByAdmin($approval);
        $this->assertSame(['status' => 'pending', 'reviewer_comment' => null], $approval->attribute_changes['old']);
        $this->assertSame(['status' => 'approved', 'reviewer_comment' => 'Добро пожаловать'], $approval->attribute_changes['attributes']);
        $this->assertSame($approved->user_id, $approval->properties['user_id']);

        $rejection = $this->entry(AuditEvent::teacherRejected, $rejected);
        $this->assertSame(Status::rejected->value, $rejection->attribute_changes['attributes']['status']);

        $revocation = $this->entry(AuditEvent::teacherRevoked, $approved);
        $this->assertSame('approved', $revocation->attribute_changes['old']['status']);
        $this->assertSame('revoked', $revocation->attribute_changes['attributes']['status']);
    }

    public function test_admin_crud_changes_are_logged_with_diff(): void
    {
        $this->asAdmin()->post('/admin/section', ['name' => 'Астрофизика', '_save_action' => 'save_and_back'])
            ->assertSessionHasNoErrors();
        $section = Section::query()->where('name', 'Астрофизика')->firstOrFail();
        $this->assertCausedByAdmin($this->entry(AuditEvent::created, $section));

        $this->asAdmin()->put("/admin/section/{$section->id}", ['id' => $section->id, 'name' => 'Космос'])
            ->assertSessionHasNoErrors();
        $updated = $this->entry(AuditEvent::updated, $section);
        $this->assertSame(['name' => 'Астрофизика'], $updated->attribute_changes['old']);
        $this->assertSame(['name' => 'Космос'], $updated->attribute_changes['attributes']);

        $set = CardSet::factory()->create(['user_id' => $this->admin->id, 'name' => 'Старое']);
        $this->asAdmin()->put("/admin/card-set/{$set->id}", [
            'id' => $set->id,
            'name' => 'Новое',
            'user_id' => $set->user_id,
            'section_id' => $set->section_id,
        ] + $set->only(['subject', 'class', 'difficulty']))->assertSessionHasNoErrors();
        $setUpdate = $this->entry(AuditEvent::updated, $set);
        $this->assertSame(['name' => 'Старое'], $setUpdate->attribute_changes['old']);
        $this->assertSame(['name' => 'Новое'], $setUpdate->attribute_changes['attributes']);

        $test = Test::factory()->create(['user_id' => $this->admin->id]);
        $this->asAdmin()->delete("/admin/test/{$test->id}")->assertOk();
        $this->assertSame($test->name, $this->entry(AuditEvent::deleted, $test)->attribute_changes['old']['name']);

        $this->asAdmin()->delete("/admin/section/{$section->id}")->assertOk();
        $this->assertCausedByAdmin($this->entry(AuditEvent::deleted, $section));
    }

    public function test_user_changes_in_admin_are_logged_without_secrets(): void
    {
        $this->asAdmin()->post('/admin/user', [
            'name' => 'Новый',
            'email' => 'new-user@example.com',
            'password' => 'Secret-pass-123',
            'role_ids' => [self::roleId(RoleName::student)],
        ])->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

        $created = $this->entry(AuditEvent::created, $user);
        $this->assertCausedByAdmin($created);
        $this->assertSame('new-user@example.com', $created->attribute_changes['attributes']['email']);
        $this->assertSame(AuditLog::HIDDEN, $created->attribute_changes['attributes']['password']);

        $this->asAdmin()->put("/admin/user/{$user->id}", [
            'id' => $user->id,
            'name' => 'Переименован',
            'email' => $user->email,
            'password' => 'Another-pass-456',
        ])->assertSessionHasNoErrors();

        $updated = $this->entry(AuditEvent::updated, $user);
        $this->assertSame('Новый', $updated->attribute_changes['old']['name']);
        $this->assertSame('Переименован', $updated->attribute_changes['attributes']['name']);
        $this->assertSame(AuditLog::HIDDEN, $updated->attribute_changes['old']['password']);
        $this->assertSame(AuditLog::HIDDEN, $updated->attribute_changes['attributes']['password']);

        $log = DB::table('activity_log')->get()->toJson(JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Secret-pass-123', $log);
        $this->assertStringNotContainsString('Another-pass-456', $log);
        $this->assertStringNotContainsString($user->fresh()->getAuthPassword(), $log);
        $this->assertStringNotContainsString('remember_token', $log);
    }

    public function test_api_changes_of_regular_users_are_not_logged(): void
    {
        $teacher = User::factory()->teacher()->create();
        $set = CardSet::factory()->create(['user_id' => $teacher->id]);
        $token = $teacher->createToken('app')->plainTextToken;

        $this->withToken($token)->putJson("/card_set/{$set->id}", [
            'name' => 'Моё', 'section_id' => (string) $set->section_id,
        ] + $set->only(['subject', 'class', 'difficulty']))->assertOk();
        $this->withToken($token)->putJson("/user/{$teacher->id}", ['name' => 'Новое имя', 'email' => $teacher->email])
            ->assertOk();
        $this->withToken($token)->deleteJson("/card_set/{$set->id}")->assertOk();

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_admin_sees_log_and_filters_it(): void
    {
        $user = User::factory()->create(['name' => 'Нарушитель']);
        $this->asAdmin()->post("/admin/user/{$user->id}/block", ['reason' => 'Спам'])->assertRedirect();
        $this->asAdmin()->post('/admin/section', ['name' => 'Оптика'])->assertSessionHasNoErrors();
        $expiring = User::factory()->create(['name' => 'Временный']);
        app(UserBlocking::class)->block($expiring, $this->admin, 'Час', until: now()->addHour());
        $this->travel(2)->hours();
        $this->artisan('users:unblock-expired')->assertSuccessful();

        $this->asAdmin()->get(self::URL)
            ->assertOk()
            ->assertSee('Журнал действий')
            ->assertSee('name="event"', false)
            ->assertSee('name="causer"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="from"', false)
            ->assertSee($this->admin->name);

        $all = $this->search();
        $this->assertStringContainsString('Блокировка', $all);
        $this->assertStringContainsString('Раздел #', $all);
        $this->assertStringContainsString('Спам', $all);
        $this->assertStringContainsString('127.0.0.1', $all);

        $blocks = $this->search('?event=blocked');
        $this->assertStringContainsString('Нарушитель', $blocks);
        $this->assertStringNotContainsString('Оптика', $blocks);

        $system = $this->search('?causer=system');
        $this->assertStringContainsString('Временный', $system);
        $this->assertStringNotContainsString('Нарушитель', $system);

        $byAdmin = $this->search('?causer='.$this->admin->id);
        $this->assertStringContainsString('Оптика', $byAdmin);
        $this->assertStringNotContainsString('Разблокировка', $byAdmin);

        $sections = $this->search('?subject=section');
        $this->assertStringContainsString('Оптика', $sections);
        $this->assertStringNotContainsString('Нарушитель', $sections);

        $this->assertStringContainsString('Временный', $this->search('?subject=user&subject_id='.$expiring->id));
        $this->assertStringNotContainsString('Нарушитель', $this->search('?subject=user&subject_id='.$expiring->id));

        $this->assertStringNotContainsString('Оптика', $this->search('?from='.now()->addDay()->toDateString()));
        $this->assertStringContainsString('Оптика', $this->search('?from='.now()->subDay()->toDateString().'&to='.now()->toDateString()));

        $entry = $this->entry(AuditEvent::blocked, $user);
        $this->asAdmin()->get(self::URL."/{$entry->id}/show")
            ->assertOk()
            ->assertSee('Было / стало')
            ->assertSee('Спам')
            ->assertSee('127.0.0.1');
    }

    public function test_moderator_cannot_see_log(): void
    {
        $moderator = User::factory()->moderator()->create();
        $entry = app(AuditLog::class)->record(AuditEvent::blocked, $moderator, $this->admin);

        $this->actingAs($moderator, 'backpack')->get(self::URL)->assertForbidden();
        $this->actingAs($moderator, 'backpack')->post(self::URL.'/search', ['draw' => 1])->assertForbidden();
        $this->actingAs($moderator, 'backpack')->get(self::URL."/{$entry->id}/show")->assertForbidden();
        $this->actingAs($moderator, 'backpack')->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee(backpack_url('audit-log'), false);

        // право audit.view открывает журнал
        $auditor = User::factory()->create();
        $auditor->givePermissionTo([PermissionName::adminAccess->value, PermissionName::auditView->value]);
        $this->flushSession();
        $this->actingAs($auditor, 'backpack')->get(self::URL)->assertOk();
        $this->actingAs($auditor, 'backpack')->get('/admin/dashboard')->assertSee(backpack_url('audit-log'), false);
    }

    public function test_log_cannot_be_changed_from_admin(): void
    {
        $entry = app(AuditLog::class)->record(AuditEvent::blocked, $this->admin, $this->admin);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/audit-log'));
        $this->assertNotEmpty($routes);
        $routes->each(fn ($route) => $this->assertEmpty(
            array_diff($route->methods(), ['GET', 'HEAD', 'POST']),
            'Маршрут изменения журнала: '.$route->uri(),
        ));
        $this->assertSame(
            ['admin/audit-log/search'],
            $routes->filter(fn ($route) => in_array('POST', $route->methods()))->map->uri()->values()->all(),
        );

        $this->asAdmin()->get(self::URL.'/create')->assertNotFound();
        $this->asAdmin()->get(self::URL."/{$entry->id}/edit")->assertNotFound();
        foreach ([
            $this->asAdmin()->put(self::URL."/{$entry->id}", ['description' => 'x']),
            $this->asAdmin()->delete(self::URL."/{$entry->id}"),
            $this->asAdmin()->post(self::URL, ['description' => 'x']),
        ] as $response) {
            $this->assertContains($response->status(), [404, 405]);
        }
        $this->assertModelExists($entry);

        // кнопок изменения и удаления в таблице нет
        $rows = $this->search();
        $this->assertStringNotContainsString('bp-button="delete"', $rows);
        $this->assertStringNotContainsString('/edit', $rows);
    }

    public function test_cleanup_removes_records_older_than_a_year(): void
    {
        $old = app(AuditLog::class)->record(AuditEvent::blocked, $this->admin, $this->admin);
        $old->forceFill(['created_at' => now()->subDays(366)])->save();
        $fresh = app(AuditLog::class)->record(AuditEvent::unblocked, $this->admin, $this->admin);
        $fresh->forceFill(['created_at' => now()->subDays(300)])->save();

        $this->assertSame(365, config('activitylog.clean_after_days'));
        $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);

        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, 'activitylog:clean'));
        $this->assertTrue($scheduled, 'activitylog:clean не в расписании');
    }
}
