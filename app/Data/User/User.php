<?php

namespace App\Data\User;

use App\Data\Data;
use App\Enums\UserType;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;
use Spatie\LaravelData\Attributes\Hidden;

#[Schema(required: ['name', 'email', 'user_type'])]
class User extends Data
{
    #[Property(readOnly: true, example: '1')]
    public ?int $id;

    #[Property(example: 'John Doe')]
    public string $name;

    #[Property(example: 'johndoe@example.com')]
    public string $email;

    #[Hidden]
    #[Property(example: 'password')]
    public string $password = '';

    #[Property(example: 'admin')]
    public ?UserType $user_type;

    #[Property(readOnly: true, example: '2024-01-01 12:00:00')]
    public ?string $email_verified_at;

    #[Hidden]
    #[Property(writeOnly: true, example: 'avatars/abc.png')]
    public ?string $avatar_path = null;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/avatars/abc.png')]
    public ?string $avatar_url = null;

    public static function fromRequest(Request $request): User
    {
        return static::from([
                'user_type' => UserType::student->value,
            ] + $request->toArray()
        );
    }
}
