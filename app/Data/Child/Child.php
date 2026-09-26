<?php

namespace App\Data\Child;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/**
 * Аккаунт ребёнка глазами родителя.
 */
#[Schema(required: ['id', 'name', 'username'])]
class Child extends Data
{
    #[Property(readOnly: true, example: '12')]
    public int $id;

    #[Property(readOnly: true, example: 'Маша Петрова')]
    public string $name;

    #[Property(readOnly: true, example: 'masha.petrova')]
    public ?string $username;

    #[Property(readOnly: true, example: '5')]
    public ?int $grade;

    #[Property(readOnly: true, example: null, description: 'Email ребёнок может добавить сам в профиле')]
    public ?string $email;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/avatars/abc.png')]
    public ?string $avatar_url = null;
}
