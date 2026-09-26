<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Скоупы видимости материалов в списках. Проверки доступа к конкретной записи — политики в `App\Policies`.
 */
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

    /**
     * Каталог: материалы администраторов.
     */
    public static function applyCatalogScope(Builder $query): void
    {
        $query->whereHas('user', fn (Builder $owner) => $owner->role(RoleName::admin->value));
    }

    /**
     * Контент, который видит текущий пользователь: каталог админов, свой контент; админ — всё.
     */
    public static function applyVisibleScope(Builder $query, string $ownerColumn = 'user_id'): void
    {
        $user = self::user();

        if ($user?->isAdmin()) {
            return;
        }

        $query->where(function (Builder $visible) use ($user, $ownerColumn) {
            self::applyCatalogScope($visible);

            if ($user) {
                $visible->orWhere($ownerColumn, $user->id);
            }
        });
    }

    /**
     * Контент, который пользователь может редактировать: свой, каталог — с правом catalog.manage; админ — всё.
     */
    public static function applyEditableScope(Builder $query, User $user, string $ownerColumn = 'user_id'): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->can(PermissionName::catalogManage->value)) {
            $query->where($ownerColumn, $user->id);

            return;
        }

        $query->where(function (Builder $editable) use ($user, $ownerColumn) {
            $editable->where($ownerColumn, $user->id);
            $editable->orWhere(fn (Builder $catalog) => self::applyCatalogScope($catalog));
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
            self::applyEditableScope($query, $user, $ownerColumn);

            return;
        }

        self::applyCatalogScope($query);
    }
}
