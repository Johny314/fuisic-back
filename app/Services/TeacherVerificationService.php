<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\TeacherVerificationStatus as Status;
use App\Exceptions\TeacherVerificationConflict;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Notifications\TeacherVerificationDecided;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Заявки на «Проверенного учителя»: подача учителем и решения проверяющего.
 * Одобрение выдаёт пользователю прямое право catalog.submit, отзыв — забирает.
 */
class TeacherVerificationService
{
    public function abortUnlessTeacher(User $user): void
    {
        abort_unless($user->hasRole(RoleName::teacher->value), 403, 'Доступно только учителям');
    }

    public function submit(User $user, array $attributes): TeacherVerification
    {
        $this->abortUnlessTeacher($user);

        try {
            return DB::transaction(function () use ($user, $attributes) {
                // Блокировка строки пользователя — параллельные подачи идут по очереди
                User::query()->whereKey($user->id)->lockForUpdate()->first();

                match ($user->latestTeacherVerification()->first()?->status) {
                    Status::pending => throw new TeacherVerificationConflict('Заявка уже на рассмотрении'),
                    Status::approved => throw new TeacherVerificationConflict('Статус «Проверенный учитель» уже подтверждён'),
                    default => null,
                };

                return $user->teacherVerifications()->create($attributes);
            });
        } catch (UniqueConstraintViolationException) {
            throw new TeacherVerificationConflict('Заявка уже на рассмотрении');
        }
    }

    public function approve(TeacherVerification $verification, User $reviewer, ?string $comment = null): TeacherVerification
    {
        return $this->decide($verification, $reviewer, Status::pending, Status::approved, $comment,
            fn (User $user) => $user->givePermissionTo(PermissionName::catalogSubmit->value));
    }

    public function reject(TeacherVerification $verification, User $reviewer, string $comment): TeacherVerification
    {
        return $this->decide($verification, $reviewer, Status::pending, Status::rejected, $comment);
    }

    public function revoke(TeacherVerification $verification, User $reviewer, string $comment): TeacherVerification
    {
        return $this->decide($verification, $reviewer, Status::approved, Status::revoked, $comment,
            fn (User $user) => $user->revokePermissionTo(PermissionName::catalogSubmit->value));
    }

    private function decide(
        TeacherVerification $verification,
        User $reviewer,
        Status $from,
        Status $to,
        ?string $comment,
        ?Closure $applyToUser = null,
    ): TeacherVerification {
        return DB::transaction(function () use ($verification, $reviewer, $from, $to, $comment, $applyToUser) {
            $locked = TeacherVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($locked->status !== $from) {
                throw new TeacherVerificationConflict(
                    'Действие недоступно: статус заявки — «'.$locked->status->label().'»'
                );
            }

            $locked->forceFill([
                'status' => $to,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewer_comment' => filled($comment) ? $comment : null,
            ])->save();

            if ($locked->user && $applyToUser) {
                $applyToUser($locked->user);
            }

            // afterCommit: письмо уйдёт в очередь только после фиксации решения
            $locked->user?->notify(new TeacherVerificationDecided($to, $locked->reviewer_comment));

            return $locked;
        });
    }
}
