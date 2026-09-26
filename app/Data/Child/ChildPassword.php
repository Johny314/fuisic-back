<?php

namespace App\Data\Child;

use App\Data\Data;
use App\OpenApi\Property;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes\Schema;

#[Schema(required: ['password', 'password_confirmation'])]
class ChildPassword extends Data
{
    #[Property(writeOnly: true, example: 'New-secret-123')]
    public string $password;

    #[Property(writeOnly: true, example: 'New-secret-123')]
    public ?string $password_confirmation = null;

    public static function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }
}
