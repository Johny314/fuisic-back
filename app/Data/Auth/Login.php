<?php

namespace App\Data\Auth;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema(required: ['login', 'password'])]
class Login extends Data
{
    #[Property(example: 'johndoe@example.com', description: 'Email или логин ребёнка')]
    public ?string $login = null;

    #[Property(example: 'johndoe@example.com', deprecated: true, description: 'Старое поле, вместо него — login')]
    public ?string $email = null;

    #[Property(example: 'password')]
    public string $password;
}
