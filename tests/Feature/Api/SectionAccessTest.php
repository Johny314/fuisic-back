<?php

namespace Tests\Feature\Api;

use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SectionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_can_list_sections(): void
    {
        Section::factory()->count(2)->create();

        $this->getJson('/section')->assertOk()->assertJsonCount(2);
    }

    public function test_student_cannot_manage_sections(): void
    {
        $section = Section::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/section', ['name' => 'Новый раздел'])->assertForbidden();
        $this->putJson("/section/{$section->id}", ['name' => 'Переименован'])->assertForbidden();
        $this->deleteJson("/section/{$section->id}")->assertForbidden();

        $this->assertNotSoftDeleted($section);
        $this->assertDatabaseMissing('sections', ['name' => 'Новый раздел']);
    }

    public function test_admin_can_manage_sections(): void
    {
        $section = Section::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/section', ['name' => 'Оптика'])->assertSuccessful();
        $this->putJson("/section/{$section->id}", ['name' => 'Механика'])->assertOk();
        $this->deleteJson("/section/{$section->id}")->assertOk();

        $this->assertDatabaseHas('sections', ['name' => 'Оптика']);
    }
}
