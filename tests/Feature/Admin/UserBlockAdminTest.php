<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\UserBlocking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Действия «заблокировать / разблокировать» в CRUD пользователей и выход заблокированного из админки.
 */
class UserBlockAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_block_form_renders_and_list_shows_block_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'backpack')
            ->get("/admin/user/{$user->id}/block")
            ->assertOk()
            ->assertSee('name="reason"', false)
            ->assertSee('name="until"', false);

        $rows = collect($this->actingAs($this->admin, 'backpack')
            ->post('/admin/user/search', ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->json('data'))->flatten()->implode('');

        $this->assertStringContainsString("/admin/user/{$user->id}/block", $rows);
        // себя заблокировать нельзя — кнопки нет
        $this->assertStringNotContainsString("/admin/user/{$this->admin->id}/block", $rows);
    }

    public function test_admin_blocks_user_through_the_form(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $user->createToken('api-token');

        $this->actingAs($this->admin, 'backpack')
            ->post("/admin/user/{$user->id}/block", [
                'reason' => 'Спам',
                'comment' => 'Жалобы от трёх учеников',
                'until' => now()->addWeek()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect('/admin/user');

        $block = $user->activeBlock();
        $this->assertNotNull($block);
        $this->assertSame('Спам', $block->reason);
        $this->assertSame('Жалобы от трёх учеников', $block->comment);
        $this->assertSame($this->admin->id, $block->blocked_by_id);
        $this->assertEquals(now()->addWeek()->startOfMinute(), $block->until);
        $this->assertSame(0, $user->tokens()->count());

        $this->actingAs($this->admin, 'backpack')
            ->get("/admin/user/{$user->id}/show")
            ->assertOk()
            ->assertSee('Жалобы от трёх учеников');
    }

    public function test_block_without_term_is_permanent_and_reason_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'backpack')
            ->from("/admin/user/{$user->id}/block")
            ->post("/admin/user/{$user->id}/block", ['reason' => ''])
            ->assertRedirect("/admin/user/{$user->id}/block")
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin, 'backpack')
            ->post("/admin/user/{$user->id}/block", ['reason' => 'Навсегда', 'until' => ''])
            ->assertRedirect();

        $this->assertNull($user->activeBlock()->until);
    }

    public function test_admin_unblocks_user(): void
    {
        $user = User::factory()->create();
        app(UserBlocking::class)->block($user, $this->admin, 'Спам');

        $this->actingAs($this->admin, 'backpack')
            ->post("/admin/user/{$user->id}/unblock")
            ->assertRedirect();

        $this->assertFalse($user->isBlocked());
        $this->assertSame(1, $user->blocks()->count());
    }

    public function test_block_actions_require_users_block_permission(): void
    {
        // в админку пускает, но блокировать без users.block нельзя
        $withoutPermission = User::factory()->create();
        $withoutPermission->syncRoles([]);
        $withoutPermission->givePermissionTo([PermissionName::adminAccess->value, PermissionName::usersView->value]);
        $user = User::factory()->create();

        $this->actingAs($withoutPermission, 'backpack')->get("/admin/user/{$user->id}/block")->assertForbidden();
        $this->actingAs($withoutPermission, 'backpack')
            ->post("/admin/user/{$user->id}/block", ['reason' => 'Спам'])
            ->assertForbidden();
        $this->actingAs($withoutPermission, 'backpack')->post("/admin/user/{$user->id}/unblock")->assertForbidden();

        $this->assertFalse($user->isBlocked());
    }

    public function test_admin_cannot_block_themselves(): void
    {
        $this->actingAs($this->admin, 'backpack')
            ->post("/admin/user/{$this->admin->id}/block", ['reason' => 'Сам себя'])
            ->assertForbidden();

        $this->assertFalse($this->admin->isBlocked());
    }

    public function test_blocked_user_is_logged_out_of_admin_panel(): void
    {
        $other = User::factory()->admin()->create();
        $this->actingAs($other, 'backpack')->get('/admin/dashboard')->assertOk();

        app(UserBlocking::class)->block($other, $this->admin, 'Утечка данных');

        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest('backpack');
    }
}
