<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\PermissionName;
use App\Models\User;
use App\Models\UserBlock;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Блокировка и разблокировка пользователей. Вход и запросы заблокированного
 * отсекает fuisic-auth по User::authBlock(), админку — LogoutBlockedBackpackUser.
 * Блокировки и разблокировки (в том числе по сроку) пишутся в журнал действий.
 */
class UserBlocking
{
    public function __construct(private readonly AuditLog $audit) {}

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

            $this->audit->record(AuditEvent::blocked, $target, $actor, new: self::blockDetails($block), properties: [
                'block_id' => $block->id,
            ]);

            return $block;
        });
    }

    public function unblock(User $target, User $actor): int
    {
        abort_unless($this->canBlock($actor, $target), 403, 'Недостаточно прав');

        return DB::transaction(function () use ($target, $actor) {
            $blocks = $target->blocks()->active()->lockForUpdate()->get();

            if ($blocks->isEmpty()) {
                return 0;
            }

            $unblockedAt = now();
            $count = UserBlock::query()->whereKey($blocks->modelKeys())->update([
                'unblocked_at' => $unblockedAt,
                'unblocked_by_id' => $actor->id,
            ]);

            foreach ($blocks as $block) {
                $this->audit->record(AuditEvent::unblocked, $target, $actor,
                    old: self::blockDetails($block),
                    new: ['unblocked_at' => $unblockedAt->toIso8601String()],
                    properties: ['block_id' => $block->id],
                );
            }

            return $count;
        });
    }

    /** Закрывает блокировки с истёкшим сроком (планировщик); на доступ не влияет — они уже не действуют. */
    public function closeExpired(): int
    {
        return DB::transaction(function () {
            $expired = UserBlock::query()
                ->with('user')
                ->whereNull('unblocked_at')
                ->whereNotNull('until')
                ->where('until', '<=', now())
                ->lockForUpdate()
                ->get();

            if ($expired->isEmpty()) {
                return 0;
            }

            $count = UserBlock::query()->whereKey($expired->modelKeys())->update(['unblocked_at' => DB::raw('until')]);

            // автор — «система»
            foreach ($expired as $block) {
                if ($block->user) {
                    $this->audit->record(AuditEvent::unblocked, $block->user, null,
                        old: self::blockDetails($block),
                        new: ['unblocked_at' => $block->until->toIso8601String()],
                        properties: ['block_id' => $block->id, 'expired' => true],
                        description: 'Блокировка снята по сроку',
                    );
                }
            }

            return $count;
        });
    }

    /** @return array{reason: string, comment: ?string, until: ?string} */
    private static function blockDetails(UserBlock $block): array
    {
        return [
            'reason' => $block->reason,
            'comment' => $block->comment,
            'until' => $block->until?->toIso8601String(),
        ];
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
