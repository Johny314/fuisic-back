<?php

namespace Tests\Feature\Admin;

use App\Models\Card\CardSet;
use App\Models\Section;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'users' => ['/admin/user'],
            'user create' => ['/admin/user/create'],
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

        foreach (['card-set', 'section', 'test', 'user'] as $entity) {
            $this->actingAs($admin, 'backpack')
                ->post("/admin/{$entity}/search", ['draw' => 1, 'start' => 0, 'length' => 10])
                ->assertOk()
                ->assertJsonStructure(['data', 'recordsTotal']);
        }
    }

    public function test_student_cannot_open_admin(): void
    {
        // CheckIfAdmin уводит не-админа на форму входа
        $this->actingAs(User::factory()->create(), 'backpack')
            ->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }
}
