<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public const string ROLES = 'role_ids';

    public function authorize(): bool
    {
        // доступ к записи проверяет UserCrudController (AuthorizesCrud)
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $target = $this->route('id') ? User::query()->find($this->route('id')) : null;
        $actor = backpack_user();

        return [
            'name' => ['required', 'string', 'max:255'],
            // аккаунт ребёнка входит по логину — email ему не обязателен
            'email' => [Rule::requiredIf(blank($target?->username)), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            // при редактировании пустой пароль = оставить прежний (см. UserCrudController::update)
            'password' => [$target ? 'nullable' : 'required', 'string', 'min:8'],
            // роли меняет только roles.manage; без поля — роли не трогаем
            self::ROLES => $actor?->can(PermissionName::rolesManage->value)
                ? ['sometimes', 'required', 'array', $this->keepsOwnAdminRole($actor, $target)]
                : ['prohibited'],
            self::ROLES.'.*' => array_filter([
                'integer',
                Rule::exists('roles', 'id')->where('guard_name', RoleCatalog::GUARD),
                // роль admin выдаёт только admin
                $actor?->isAdmin() ? null : Rule::notIn(array_filter([self::adminRoleId()])),
            ]),
        ];
    }

    /**
     * Роли, которые может выдать текущий пользователь: admin — только admin.
     *
     * @return array<int, string> id => подпись
     */
    public static function assignableRoles(): array
    {
        return Role::query()
            ->where('guard_name', RoleCatalog::GUARD)
            ->when(! backpack_user()?->isAdmin(), fn ($query) => $query->where('name', '!=', RoleName::admin->value))
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->id => $role->displayName()])
            ->all();
    }

    public static function adminRoleId(): ?int
    {
        return Role::query()->where('guard_name', RoleCatalog::GUARD)->where('name', RoleName::admin->value)->value('id');
    }

    /** Снять роль admin с самого себя нельзя — иначе можно остаться без администратора. */
    private function keepsOwnAdminRole(?User $actor, ?User $target): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($actor, $target): void {
            if ($target && $actor && $target->is($actor) && $actor->isAdmin()
                && ! in_array((string) self::adminRoleId(), array_map('strval', (array) $value), true)) {
                $fail('Нельзя снять роль администратора с самого себя.');
            }
        };
    }

    public function attributes(): array
    {
        return [
            'name' => 'имя',
            'password' => 'пароль',
            self::ROLES => 'роли',
            self::ROLES.'.*' => 'роль',
        ];
    }

    public function messages(): array
    {
        return [
            self::ROLES.'.required' => 'Выберите хотя бы одну роль.',
            self::ROLES.'.prohibited' => 'Недостаточно прав для назначения ролей.',
            self::ROLES.'.*.not_in' => 'Роль администратора может выдать только администратор.',
        ];
    }
}
