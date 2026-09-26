<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Enums\TeacherVerificationStatus as Status;
use App\Models\Card\CardSet;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Notifications\TeacherVerificationDecided;
use App\Services\TeacherVerificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Иванова Мария Петровна',
            'workplace_type' => 'Школа',
            'workplace_name' => 'Школа № 57, Москва',
            'subjects' => ['Физика', 'Астрономия'],
            'link' => 'https://school57.ru/teachers/ivanova',
            'comment' => 'Веду физику в 7–11 классах',
        ], $overrides);
    }

    private function service(): TeacherVerificationService
    {
        return app(TeacherVerificationService::class);
    }

    public function test_teacher_submits_application_and_sees_status(): void
    {
        $teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/teacher_verification', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('full_name', 'Иванова Мария Петровна')
            ->assertJsonPath('workplace_type', 'Школа')
            ->assertJsonPath('subjects', ['Физика', 'Астрономия'])
            ->assertJsonPath('reviewer_comment', null);

        $this->getJson('/teacher_verification')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonMissingPath('reviewer_id');

        $this->getJson('/me')
            ->assertJsonPath('teacher_verified', false)
            ->assertJsonPath('teacher_verification.status', 'pending')
            ->assertJsonPath('teacher_verification.reviewed_at', null);
    }

    public function test_teacher_without_applications_gets_404_and_null_in_me(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->getJson('/teacher_verification')->assertNotFound();
        $this->getJson('/me')->assertJsonPath('teacher_verification', null);
    }

    public function test_link_and_comment_are_optional(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->postJson('/teacher_verification', $this->payload(['link' => null, 'comment' => null]))
            ->assertCreated()
            ->assertJsonPath('link', null);
    }

    public static function invalidPayloads(): array
    {
        return [
            'no full name' => [['full_name' => ''], 'full_name'],
            'unknown workplace type' => [['workplace_type' => 'Завод'], 'workplace_type'],
            'no subjects' => [['subjects' => []], 'subjects'],
            'subject is not a string' => [['subjects' => [['x']]], 'subjects.0'],
            'link is not http' => [['link' => 'javascript:alert(1)'], 'link'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_application_is_validated(array $overrides, string $field): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->postJson('/teacher_verification', $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_status_fields_from_request_are_ignored(): void
    {
        $teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/teacher_verification', $this->payload([
            'status' => 'approved',
            'reviewer_comment' => 'сам себе одобрил',
        ]))->assertCreated()->assertJsonPath('status', 'pending')->assertJsonPath('reviewer_comment', null);

        $this->assertFalse($teacher->fresh()->can(PermissionName::catalogSubmit->value));
    }

    public function test_second_pending_application_is_rejected(): void
    {
        $teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/teacher_verification', $this->payload())->assertCreated();
        $this->postJson('/teacher_verification', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Заявка уже на рассмотрении');

        $this->assertSame(1, $teacher->teacherVerifications()->count());
    }

    public function test_approved_teacher_cannot_apply_again(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherVerification::factory()->for($teacher)->status(Status::approved)->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/teacher_verification', $this->payload())->assertStatus(409);
    }

    public function test_rejection_comment_is_visible_and_teacher_can_reapply(): void
    {
        $teacher = User::factory()->teacher()->create();
        $verification = TeacherVerification::factory()->for($teacher)->create();
        $this->service()->reject($verification, User::factory()->admin()->create(), 'Добавьте ссылку на сайт школы');
        Sanctum::actingAs($teacher);

        $this->getJson('/teacher_verification')
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('reviewer_comment', 'Добавьте ссылку на сайт школы');
        $this->getJson('/me')
            ->assertJsonPath('teacher_verified', false)
            ->assertJsonPath('teacher_verification.status', 'rejected')
            ->assertJsonPath('teacher_verification.reviewer_comment', 'Добавьте ссылку на сайт школы');

        $this->postJson('/teacher_verification', $this->payload())->assertCreated()->assertJsonPath('status', 'pending');
        $this->getJson('/me')->assertJsonPath('teacher_verification.status', 'pending');
        $this->assertSame(2, $teacher->teacherVerifications()->count());
    }

    public function test_teacher_can_reapply_after_revocation(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherVerification::factory()->for($teacher)->status(Status::revoked)->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/teacher_verification', $this->payload())->assertCreated();
    }

    public static function nonTeachers(): array
    {
        return [
            'student' => ['student'],
            'parent' => ['parent'],
            'moderator' => ['moderator'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('nonTeachers')]
    public function test_non_teacher_cannot_apply(string $role): void
    {
        $user = $role === 'student' ? User::factory()->create() : User::factory()->{$role}()->create();
        Sanctum::actingAs($user);

        $this->postJson('/teacher_verification', $this->payload())->assertForbidden();
        $this->postJson('/teacher_verification', [])->assertForbidden();
        $this->getJson('/teacher_verification')->assertForbidden();
        $this->assertSame(0, TeacherVerification::query()->count());
    }

    public function test_guest_cannot_apply(): void
    {
        $this->postJson('/teacher_verification', $this->payload())->assertUnauthorized();
        $this->getJson('/teacher_verification')->assertUnauthorized();
    }

    public function test_approval_grants_catalog_submit_and_verified_mark(): void
    {
        $teacher = User::factory()->teacher()->create();
        $verification = TeacherVerification::factory()->for($teacher)->create();

        $this->service()->approve($verification, User::factory()->admin()->create(), 'Добро пожаловать');

        // право выдаётся прямо пользователю, роль teacher не меняется
        $this->assertTrue($teacher->fresh()->hasDirectPermission(PermissionName::catalogSubmit->value));
        $this->assertFalse($teacher->roles()->first()->hasPermissionTo(PermissionName::catalogSubmit->value));

        Sanctum::actingAs($teacher->fresh());
        $this->getJson('/me')
            ->assertJsonPath('teacher_verified', true)
            ->assertJsonPath('permissions', ['catalog.submit'])
            ->assertJsonPath('teacher_verification.status', 'approved')
            ->assertJsonPath('teacher_verification.reviewer_comment', 'Добро пожаловать');
        $this->assertNotNull($this->getJson('/me')->json('teacher_verification.reviewed_at'));
    }

    public function test_approval_does_not_verify_other_teachers(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();

        $this->service()->approve(TeacherVerification::factory()->for($teacher)->create(), User::factory()->admin()->create());

        $this->assertFalse($other->fresh()->isVerifiedTeacher());
    }

    public function test_revocation_takes_catalog_submit_away(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create();
        $verification = TeacherVerification::factory()->for($teacher)->create();
        $this->service()->approve($verification, $admin);

        $this->service()->revoke($verification, $admin, 'Жалобы на материалы');

        $teacher = $teacher->fresh();
        $this->assertFalse($teacher->can(PermissionName::catalogSubmit->value));
        Sanctum::actingAs($teacher);
        $this->getJson('/me')
            ->assertJsonPath('teacher_verified', false)
            ->assertJsonPath('permissions', [])
            ->assertJsonPath('teacher_verification.status', 'revoked')
            ->assertJsonPath('teacher_verification.reviewer_comment', 'Жалобы на материалы');
    }

    public function test_owner_of_materials_shows_verified_mark_without_private_fields(): void
    {
        $verified = User::factory()->teacher()->create();
        $this->service()->approve(TeacherVerification::factory()->for($verified)->create(), User::factory()->admin()->create());
        $plain = User::factory()->teacher()->create();
        $verifiedSet = CardSet::factory()->for($verified)->create();
        $plainSet = CardSet::factory()->for($plain)->create();

        Sanctum::actingAs($verified);
        $this->getJson("/card_set/{$verifiedSet->id}")
            ->assertOk()
            ->assertJsonPath('user.teacher_verified', true)
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.permissions')
            ->assertJsonMissingPath('user.roles');

        Sanctum::actingAs($plain);
        $this->getJson("/card_set/{$plainSet->id}")->assertOk()->assertJsonPath('user.teacher_verified', false);
    }

    public function test_database_allows_only_one_pending_application_per_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherVerification::factory()->for($teacher)->status(Status::rejected)->create();
        TeacherVerification::factory()->for($teacher)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        TeacherVerification::factory()->for($teacher)->create();
    }

    public function test_decision_email_goes_through_the_queue(): void
    {
        Queue::fake();
        $verification = TeacherVerification::factory()->create();

        $this->service()->approve($verification, User::factory()->admin()->create());

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->notification instanceof TeacherVerificationDecided
            && $job->channels === ['mail']
            && $job->notifiables->first()->is($verification->user));
    }
}
