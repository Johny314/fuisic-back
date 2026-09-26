<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\Card\CardSet;
use App\Models\Test\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ContentAccess
{
    public static function user(): ?User
    {
        $user = auth('sanctum')->user() ?? auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function requireUser(): User
    {
        $user = self::user();
        abort_unless($user, 401, 'Требуется авторизация');

        return $user;
    }

    public static function isAdmin(?User $user = null): bool
    {
        $user ??= self::user();

        return $user?->user_type === UserType::admin;
    }

    public static function abortUnlessAdmin(): User
    {
        $user = self::requireUser();
        abort_unless(self::isAdmin($user), 403, 'Недостаточно прав');

        return $user;
    }

    public static function abortUnlessCanManageUser(User $target): User
    {
        $user = self::requireUser();
        abort_unless(
            self::isAdmin($user) || (int) $target->id === (int) $user->id,
            403,
            'Недостаточно прав'
        );

        return $user;
    }

    public static function canManageCardSet(CardSet $set, ?User $user = null): bool
    {
        $user ??= self::user();
        if (! $user) {
            return false;
        }

        return self::isAdmin($user) || (int) $set->user_id === (int) $user->id;
    }

    public static function canViewCardSet(CardSet $set, ?User $user = null): bool
    {
        $set->loadMissing('user');
        if (self::isCatalogOwner($set->user)) {
            return true;
        }

        return self::canManageCardSet($set, $user);
    }

    public static function abortUnlessCanManageCardSet(CardSet $set): User
    {
        $user = self::requireUser();
        abort_unless(self::canManageCardSet($set, $user), 403, 'Недостаточно прав');

        return $user;
    }

    public static function abortUnlessCanViewCardSet(CardSet $set): void
    {
        abort_unless(self::canViewCardSet($set), 403, 'Набор недоступен');
    }

    public static function canManageTest(Test $test, ?User $user = null): bool
    {
        $user ??= self::user();
        if (! $user) {
            return false;
        }

        return self::isAdmin($user) || (int) $test->user_id === (int) $user->id;
    }

    public static function canViewTest(Test $test, ?User $user = null): bool
    {
        $test->loadMissing('user');
        if (self::isCatalogOwner($test->user)) {
            return true;
        }

        return self::canManageTest($test, $user);
    }

    public static function abortUnlessCanManageTest(Test $test): User
    {
        $user = self::requireUser();
        abort_unless(self::canManageTest($test, $user), 403, 'Недостаточно прав');

        return $user;
    }

    public static function abortUnlessCanViewTest(Test $test): void
    {
        abort_unless(self::canViewTest($test), 403, 'Тест недоступен');
    }

    /**
     * Контент, который видит текущий пользователь: каталог админов (кроме заблокированных), свой контент; админ — всё.
     */
    public static function applyVisibleScope(Builder $query, string $ownerColumn = 'user_id'): void
    {
        $user = self::user();

        if (self::isAdmin($user)) {
            return;
        }

        $query->where(function (Builder $visible) use ($user, $ownerColumn) {
            $visible->whereHas('user', fn (Builder $owner) => self::whereCatalogOwner($owner));

            if ($user) {
                $visible->orWhere($ownerColumn, $user->id);
            }
        });
    }

    public static function applyIndexScope(Builder $query, mixed $scope, string $ownerColumn = 'user_id'): void
    {
        $user = self::user();
        $scope = is_string($scope) ? $scope : null;

        if ($scope === 'mine') {
            abort_unless($user, 401, 'Требуется авторизация');
            $query->where($ownerColumn, $user->id);

            return;
        }

        if ($scope === 'studio') {
            abort_unless($user, 401, 'Требуется авторизация');
            if (! self::isAdmin($user)) {
                $query->where($ownerColumn, $user->id);
            }

            return;
        }

        $query->whereHas('user', fn (Builder $owner) => self::whereCatalogOwner($owner));
    }

    /** Каталог — контент админов; материалы заблокированного пользователя из него скрыты. */
    public static function isCatalogOwner(?User $owner): bool
    {
        return $owner?->user_type === UserType::admin && ! $owner->isBlocked();
    }

    private static function whereCatalogOwner(Builder $owner): void
    {
        $owner->where('user_type', UserType::admin)->notBlocked();
    }
}
