<?php

namespace App\Data\User;

use App\Data\Data;
use App\Enums\UserType;
use App\OpenApi\Property;
use Illuminate\Http\Request;
use OpenApi\Attributes\Schema;

#[Schema(required: ['name', 'email', ])]
class UserUpdate extends Data
{
    #[Property(example: 'John Doe')]
    public string $name;

    #[Property(example: 'johndoe@example.com')]
    public string $email;

    #[Property(example: 'password')]
    public ?string $password = null;

    #[Property(example: 'avatars/abc.png')]
    public ?string $avatar_path = null;
}
