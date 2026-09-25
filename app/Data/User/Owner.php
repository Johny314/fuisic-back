<?php

namespace App\Data\User;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

/**
 * Публичные данные автора контента (без email и служебных полей) — для каталога.
 */
#[Schema(required: ['id', 'name'])]
class Owner extends Data
{
    #[Property(readOnly: true, example: '1')]
    public int $id;

    #[Property(readOnly: true, example: 'John Doe')]
    public string $name;

    #[Property(readOnly: true, example: 'http://localhost:9000/fuisic/avatars/abc.png')]
    public ?string $avatar_url = null;
}
