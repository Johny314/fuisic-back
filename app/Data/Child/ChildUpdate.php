<?php

namespace App\Data\Child;

use App\Data\Data;
use App\OpenApi\Property;
use App\Rules\Username;
use OpenApi\Attributes\Schema;

#[Schema(required: ['name', 'username'])]
class ChildUpdate extends Data
{
    #[Property(example: 'Маша Петрова')]
    public string $name;

    #[Property(example: 'masha.petrova', description: 'Латиница, цифры, «_» и «.», 3–32 символа; без учёта регистра')]
    public string $username;

    #[Property(example: '6', description: 'Класс, 1–11')]
    public ?int $grade = null;

    public static function rules(): array
    {
        $child = request()->route('child');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', new Username(is_numeric($child) ? (int) $child : null)],
            'grade' => ['nullable', 'integer', 'between:1,11'],
        ];
    }
}
