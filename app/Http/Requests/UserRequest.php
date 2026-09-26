<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('id'))],
            // при редактировании пустой пароль = оставить прежний (см. UserCrudController::update)
            'password' => [$this->route('id') ? 'nullable' : 'required', 'string', 'min:8'],
            'user_type' => ['required', Rule::enum(UserType::class)->only(self::assignableUserTypes())],
        ];
    }

    /**
     * Роль admin выдаёт только admin.
     *
     * @return list<UserType>
     */
    public static function assignableUserTypes(): array
    {
        return backpack_user()?->isAdmin()
            ? UserType::cases()
            : array_values(array_filter(UserType::cases(), fn (UserType $type) => $type !== UserType::admin));
    }

    /**
     * Get the validation attributes that apply to the request.
     */
    public function attributes(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            //
        ];
    }
}
