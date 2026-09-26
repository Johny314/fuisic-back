<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Вход в админку через форму, без actingAs: сессия должна переживать следующие запросы
 * (fuisic-back#45 — хэш пароля в сессии в формате Laravel 13).
 */
class AdminLoginSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_through_the_form_keeps_the_session(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('Admin-pass-123')]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Admin-pass-123'])
            ->assertRedirect();

        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/user')->assertOk();
        $this->assertAuthenticatedAs($admin, 'backpack');
    }

    public function test_password_change_elsewhere_ends_the_admin_session(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('Admin-pass-123')]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Admin-pass-123']);
        $this->get('/admin/dashboard')->assertOk();

        $admin->forceFill(['password' => Hash::make('Changed-pass-456')])->save();
        // в тесте guard держит пользователя из прошлого запроса; новый запрос читает его из БД
        $this->app['auth']->forgetGuards();

        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
        $this->assertGuest('backpack');
    }

    public function test_session_with_raw_password_hash_from_before_the_fix_stays_valid(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'backpack')
            ->withSession(['password_hash_backpack' => $admin->getAuthPassword()])
            ->get('/admin/dashboard')
            ->assertOk();
    }
}
