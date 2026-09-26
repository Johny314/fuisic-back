<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\UserBlock;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Блокировка и разблокировка пользователей. Вход и запросы заблокированного
 * отсекает fuisic-auth по User::authBlock(), админку — LogoutBlockedBackpackUser.
 */
class UserBlocking
{
    /**
     * Право users.block; себя — нельзя; персонал (admin, moderator) блокирует только admin.
     * Проверяется явно, без Gate::before: запрет на самоблокировку действует и для admin.
     */
    public function canBlock(User $actor, User $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        if (! $actor->can(PermissionName::usersBlock->value)) {
            return false;
        }

        return $actor->isAdmin() || ! $target->isStaff();
    }

    public function block(User $target, User $actor, string $reason, ?string $comment = null, ?DateTimeInterface $until = null): UserBlock
    {
        abort_unless($this->canBlock($actor, $target), 403, 'Недостаточно прав');

        return DB::transaction(function () use ($target, $actor, $reason, $comment, $until) {
            $block = $target->blocks()->create([
                'blocked_by_id' => $actor->id,
                'reason' => $reason,
                'comment' => $comment,
                'until' => $until,
            ]);

            $this->revokeAccess($target);

            return $block;
        });
    }

    public function unblock(User $target, User $actor): int
    {
        abort_unless($this->canBlock($actor, $target), 403, 'Недостаточно прав');

        return $target->blocks()->active()->update([
            'unblocked_at' => now(),
            'unblocked_by_id' => $actor->id,
        ]);
    }

    /** Закрывает блокировки с истёкшим сроком (планировщик); на доступ не влияет — они уже не действуют. */
    public function closeExpired(): int
    {
        return UserBlock::query()
            ->whereNull('unblocked_at')
            ->whereNotNull('until')
            ->where('until', '<=', now())
            ->update(['unblocked_at' => DB::raw('until')]);
    }

    /**
     * Отзыв доступа: API-токены и сессии в БД. Сессии админки с другим
     * драйвером (или без user_id в строке) закрывает LogoutBlockedBackpackUser на следующем запросе.
     */
    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
