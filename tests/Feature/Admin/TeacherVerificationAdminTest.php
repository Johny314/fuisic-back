<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Enums\TeacherVerificationStatus as Status;
use App\Http\Middleware\CheckIfAdmin;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Notifications\TeacherVerificationDecided;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherVerificationAdminTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/admin/teacher-verification';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'backpack');
    }

    private function search(string $query = ''): array
    {
        return $this->post(self::URL.'/search'.$query, ['draw' => 1, 'start' => 0, 'length' => 50])
            ->assertOk()
            ->json('data');
    }

    public function test_admin_sees_queue_and_application(): void
    {
        $verification = TeacherVerification::factory()->create(['workplace_name' => 'Лицей № 239']);

        $this->asAdmin()->get(self::URL)->assertOk()->assertSee('На рассмотрении');
        $this->get(self::URL.'/'.$verification->id.'/show')
            ->assertOk()
            ->assertSee('Лицей № 239')
            ->assertSee('Одобрить')
            ->assertSee('Отклонить');
    }

    public function test_queue_is_filtered_by_status(): void
    {
        TeacherVerification::factory()->create(['full_name' => 'Ждёт Решения']);
        TeacherVerification::factory()->status(Status::rejected)->create(['full_name' => 'Уже Отклонён']);

        $this->asAdmin();

        $pending = json_encode($this->search(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Ждёт Решения', $pending);
        $this->assertStringNotContainsString('Уже Отклонён', $pending);

        $rejected = json_encode($this->search('?status=rejected'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Уже Отклонён', $rejected);
        $this->assertStringNotContainsString('Ждёт Решения', $rejected);

        $this->assertCount(2, $this->search('?status=all'));
    }

    public function test_admin_approves_application(): void
    {
        $verification = TeacherVerification::factory()->create();
        $teacher = $verification->user;

        $this->asAdmin()
            ->post(self::URL.'/'.$verification->id.'/approve', ['reviewer_comment' => 'Добро пожаловать'])
            ->assertRedirect(self::URL.'/'.$verification->id.'/show');

        $verification->refresh();
        $this->assertSame(Status::approved, $verification->status);
        $this->assertSame('Добро пожаловать', $verification->reviewer_comment);
        $this->assertNotNull($verification->reviewer_id);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertTrue($teacher->fresh()->isVerifiedTeacher());

        Notification::assertSentTo($teacher, TeacherVerificationDecided::class,
            fn (TeacherVerificationDecided $n) => $n->decision === Status::approved);
    }

    public function test_admin_rejects_application_with_required_comment(): void
    {
        $verification = TeacherVerification::factory()->create();

        $this->asAdmin()
            ->post(self::URL.'/'.$verification->id.'/reject', ['reviewer_comment' => ''])
            ->assertSessionHasErrors('reviewer_comment');
        $this->assertSame(Status::pending, $verification->fresh()->status);

        $this->post(self::URL.'/'.$verification->id.'/reject', ['reviewer_comment' => 'Нет ссылки на место работы'])
            ->assertRedirect();

        $this->assertSame(Status::rejected, $verification->fresh()->status);
        $this->assertFalse($verification->user->fresh()->can(PermissionName::catalogSubmit->value));
        Notification::assertSentTo($verification->user, TeacherVerificationDecided::class,
            fn (TeacherVerificationDecided $n) => $n->decision === Status::rejected
                && $n->reviewerComment === 'Нет ссылки на место работы');
    }

    public function test_admin_revokes_approved_status(): void
    {
        $verification = TeacherVerification::factory()->create();
        $this->asAdmin()->post(self::URL.'/'.$verification->id.'/approve');
        $this->assertTrue($verification->user->fresh()->isVerifiedTeacher());

        $this->get(self::URL.'/'.$verification->id.'/show')->assertSee('Отозвать статус');
        $this->post(self::URL.'/'.$verification->id.'/revoke', ['reviewer_comment' => 'Жалобы на материалы'])
            ->assertRedirect();

        $this->assertSame(Status::revoked, $verification->fresh()->status);
        $this->assertFalse($verification->user->fresh()->isVerifiedTeacher());
        Notification::assertSentTo($verification->user, TeacherVerificationDecided::class,
            fn (TeacherVerificationDecided $n) => $n->decision === Status::revoked);
        Notification::assertSentToTimes($verification->user, TeacherVerificationDecided::class, 2);
    }

    public static function impossibleDecisions(): array
    {
        return [
            'approve rejected' => [Status::rejected, 'approve'],
            'reject approved' => [Status::approved, 'reject'],
            'revoke pending' => [Status::pending, 'revoke'],
            'revoke revoked' => [Status::revoked, 'revoke'],
        ];
    }

    #[DataProvider('impossibleDecisions')]
    public function test_decision_requires_matching_status(Status $status, string $action): void
    {
        $verification = TeacherVerification::factory()->status($status)->create();

        $this->asAdmin()
            ->post(self::URL.'/'.$verification->id.'/'.$action, ['reviewer_comment' => 'Комментарий'])
            ->assertRedirect();

        $this->assertSame($status, $verification->fresh()->status);
        $this->assertFalse($verification->user->fresh()->can(PermissionName::catalogSubmit->value));
        Notification::assertNothingSent();
    }

    public static function nonStaff(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
        ];
    }

    #[DataProvider('nonStaff')]
    public function test_teacher_and_student_have_no_access_to_queue(string $role): void
    {
        $user = $role === 'student' ? User::factory()->create() : User::factory()->teacher()->create();
        $verification = TeacherVerification::factory()->create();

        $this->actingAs($user, 'backpack')->get(self::URL)->assertRedirect('/admin/login');
        $this->post(self::URL.'/'.$verification->id.'/approve')->assertRedirect('/admin/login');

        // и без проверки входа в админку (её переделывает #24) операции закрыты правом
        $this->withoutMiddleware(CheckIfAdmin::class);
        $this->get(self::URL)->assertForbidden();
        $this->post(self::URL.'/'.$verification->id.'/approve')->assertForbidden();
        $this->assertSame(Status::pending, $verification->fresh()->status);
    }

    public function test_queue_requires_teachers_verify_permission(): void
    {
        $this->withoutMiddleware(CheckIfAdmin::class);
        $moderator = User::factory()->moderator()->create();
        $verification = TeacherVerification::factory()->create();

        $this->actingAs($moderator, 'backpack')->get(self::URL)->assertForbidden();

        $moderator->givePermissionTo(PermissionName::teachersVerify->value);

        $this->actingAs($moderator->fresh(), 'backpack')->get(self::URL)->assertOk();
        $this->post(self::URL.'/'.$verification->id.'/approve')->assertRedirect();
        $this->assertSame(Status::approved, $verification->fresh()->status);
    }

    public function test_menu_item_is_shown_to_admin(): void
    {
        $this->asAdmin()->get('/admin/dashboard')->assertOk()->assertSee('Заявки учителей');
    }

    public static function decisions(): array
    {
        return [
            'approved' => [Status::approved, 'Вы — проверенный учитель FUISIC'],
            'rejected' => [Status::rejected, 'Заявка на статус «Проверенный учитель» отклонена'],
            'revoked' => [Status::revoked, 'Статус «Проверенный учитель» отозван'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_decision_email_is_queued_and_in_russian(Status $decision, string $subject): void
    {
        $notification = new TeacherVerificationDecided($decision, 'Комментарий проверяющего');
        $mail = $notification->toMail(User::factory()->teacher()->create(['name' => 'Анна']));

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame($subject, $mail->subject);
        $this->assertSame('Здравствуйте, Анна!', $mail->greeting);
        $this->assertContains('Комментарий проверяющего: Комментарий проверяющего', $mail->introLines);
        $this->assertSame(['mail'], $notification->via(new User));
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
    }
}
