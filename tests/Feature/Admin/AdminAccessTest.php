<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Http\Middleware\CheckIfAdmin;
use App\Models\Card\CardSet;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Права внутри CRUD админки. Вход пока только для admin (CheckIfAdmin, откроется в #24),
 * поэтому middleware входа здесь отключён — проверяются сами CRUD.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckIfAdmin::class);
        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    public function test_moderator_manages_sections(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->moderator, 'backpack')->get('/admin/section')->assertOk();
        $this->actingAs($this->moderator, 'backpack')
            ->post('/admin/section', ['name' => 'Астрофизика', '_save_action' => 'save_and_back'])
            ->assertRedirect();
        $this->actingAs($this->moderator, 'backpack')->delete("/admin/section/{$section->id}")->assertOk();

        $this->assertDatabaseHas('sections', ['name' => 'Астрофизика']);
        $this->assertSoftDeleted($section);
    }

    public function test_moderator_sees_only_editable_card_sets(): void
    {
        $catalog = CardSet::factory()->for($this->admin)->create();
        $personal = CardSet::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->moderator, 'backpack')
            ->post('/admin/card-set/search', ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('recordsTotal', 1);

        $this->actingAs($this->moderator, 'backpack')->get("/admin/card-set/{$catalog->id}/edit")->assertOk();
        $this->actingAs($this->moderator, 'backpack')->get("/admin/card-set/{$personal->id}/edit")->assertNotFound();
        $this->actingAs($this->moderator, 'backpack')->delete("/admin/card-set/{$personal->id}")->assertNotFound();

        $this->assertNotSoftDeleted($personal);
    }

    public function test_moderator_views_but_does_not_manage_users(): void
    {
        $student = User::factory()->create();

        $this->actingAs($this->moderator, 'backpack')->get('/admin/user')->assertOk();
        $this->actingAs($this->moderator, 'backpack')->get("/admin/user/{$student->id}/show")->assertOk();
        $this->actingAs($this->moderator, 'backpack')->get('/admin/user/create')->assertForbidden();
        $this->actingAs($this->moderator, 'backpack')->get("/admin/user/{$student->id}/edit")->assertForbidden();
        $this->actingAs($this->moderator, 'backpack')->delete("/admin/user/{$student->id}")->assertForbidden();

        $this->assertNotSoftDeleted($student);
    }

    public function test_user_without_permissions_is_denied_everywhere(): void
    {
        $teacher = User::factory()->teacher()->create();

        foreach (['/admin/section', '/admin/card-set', '/admin/test', '/admin/user'] as $url) {
            $this->actingAs($teacher, 'backpack')->get($url)->assertForbidden();
        }
    }

    public function test_only_admin_grants_admin_type(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo([PermissionName::usersView->value, PermissionName::usersManage->value]);
        $student = User::factory()->create();

        $this->actingAs($manager, 'backpack')
            ->put("/admin/user/{$student->id}", [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'user_type' => 'admin',
            ])
            ->assertSessionHasErrors('user_type');
        $this->actingAs($manager, 'backpack')->get("/admin/user/{$this->admin->id}/edit")->assertForbidden();

        $this->assertFalse($student->fresh()->isAdmin());
    }
}
