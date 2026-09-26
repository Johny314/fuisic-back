<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // право roles.manage проверяет RoleCrudController (AuthorizesCrud)
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $role = $this->route('id') ? Role::query()->find($this->route('id')) : null;

        return [
            // имя — идентификатор роли в API (/me → roles), поэтому латиница
            'name' => array_filter([
                'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('roles', 'name')->where('guard_name', RoleCatalog::GUARD)->ignore($role?->id),
                // стартовые роли знает код (RoleName) — переименовывать нельзя
                $role?->isStarter() ? Rule::in([$role->name]) : null,
            ]),
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', RoleCatalog::GUARD)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'permission_ids' => 'права',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Название — латиница в нижнем регистре, цифры, «_» и «-», начиная с буквы.',
            'name.in' => 'Стартовую роль нельзя переименовать.',
        ];
    }
}
