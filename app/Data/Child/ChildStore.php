<?php

namespace App\Data\Child;

use App\Data\Data;
use App\OpenApi\Property;
use App\Rules\Username;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes\Schema;

#[Schema(required: ['name', 'username', 'password', 'password_confirmation'])]
class ChildStore extends Data
{
    #[Property(example: 'Маша Петрова')]
    public string $name;

    #[Property(example: 'masha.petrova', description: 'Латиница, цифры, «_» и «.», 3–32 символа; без учёта регистра')]
    public string $username;

    #[Property(example: '5', description: 'Класс, 1–11')]
    public ?int $grade = null;

    #[Property(writeOnly: true, example: 'Secret-123')]
    public string $password;

    #[Property(writeOnly: true, example: 'Secret-123')]
    public ?string $password_confirmation = null;

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', new Username],
            'grade' => ['nullable', 'integer', 'between:1,11'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }
}
