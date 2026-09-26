<?php

namespace App\Policies\Concerns;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Правила для материалов с автором (наборы, тесты): каталог — материалы администраторов,
 * открыт всем; личное видит и меняет владелец; каталог меняет право catalog.manage.
 * Admin проходит раньше, через Gate::before.
 */
trait AuthorizesContent
{
    protected function isCatalog(Model $content): bool
    {
        return (bool) $content->user?->isAdmin();
    }

    protected function canView(?User $user, Model $content): bool
    {
        return $this->isCatalog($content) || ($user && $this->canManage($user, $content));
    }

    protected function canManage(User $user, Model $content): bool
    {
        if ((int) $content->user_id === (int) $user->id) {
            return true;
        }

        return $user->can(PermissionName::catalogManage->value) && $this->isCatalog($content);
    }
}
