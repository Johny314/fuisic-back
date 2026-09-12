<?php

namespace App\Data\File;

use App\Data\Data;
use App\OpenApi\Property;
use OpenApi\Attributes\Schema;

#[Schema]
class StoredFile extends Data
{
    #[Property(example: 'avatars/abc.png')]
    public string $path;

    #[Property(example: 'http://localhost:9000/fuisic/avatars/abc.png')]
    public string $url;

    #[Property(example: 'avatar')]
    public string $purpose;
}
