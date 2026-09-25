<?php

namespace App\OpenApi\Response;

use OpenApi\Attributes\Schema;

#[Schema]
class NotFound extends Schema
{
    public function __construct()
    {
        parent::__construct(
            description: 'Один из элементов, указанных в URL, не найден',
            type: 'object',
        );
    }
}
