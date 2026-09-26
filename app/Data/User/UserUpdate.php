<?php

namespace App\Data\User;

use App\Data\Data;
use App\Models\User;
use App\OpenApi\Property;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\Schema;

/**
 * Логин, роли и связь с родителем здесь не меняются (логин ребёнка — только через родителя).
 */
#[Schema(required: ['name'])]
class UserUpdate extends Data
{
    #[Property(example: 'John Doe')]
    public string $name;

    #[Property(example: 'johndoe@example.com', description: 'Обязателен, если у пользователя нет логина')]
    public ?string $email = null;

    #[Property(example: 'password')]
    public ?string $password = null;

    #[Property(example: 'avatars/abc.png')]
    public ?string $avatar_path = null;

    public static function rules(): array
    {
        $user = request()->route('user');
        $user = $user instanceof User ? $user : null;

        return [
            // без email можно остаться только со входом по логину
            'email' => [
                $user?->username ? 'nullable' : 'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
        ];
    }
}
