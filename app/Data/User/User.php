<?php

namespace App\Data\User;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Attributes\Hidden;

#[Schema(required: ['name'])]
class User extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 'John Doe')]
    public string $name;

    #[Property(example: 'johndoe@example.com', description: 'null — аккаунт ребёнка со входом по логину')]
    public ?string $email;

    #[Property(readOnly: true, example: 'masha.petrova')]
    public ?string $username = null;

    #[Hidden]
    #[Property(example: 'password')]
    public string $password = '';

    #[Property(readOnly: true, example: '2024-01-01 12:00:00')]
    public ?string $email_verified_at;

    #[Hidden]
    #[Property(writeOnly: true, example: 'avatars/abc.png')]
    public ?string $avatar_path = null;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/avatars/abc.png')]
    public ?string $avatar_url = null;

    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
