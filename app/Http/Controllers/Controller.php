<?php

namespace App\Http\Controllers;

use App\OpenApi\Tag;
use App\OpenApi\TagAttr;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.1',
    description: 'Данный проект находится в разработке',
    title: 'FUISIC Backend API'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearer',
    type: 'http',
    description: 'access_token',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer',
)]

#[TagAttr(Tag::card)]
#[TagAttr(Tag::card_set)]
#[TagAttr(Tag::test)]
#[TagAttr(Tag::task)]
#[TagAttr(Tag::section)]
#[TagAttr(Tag::user)]
abstract class Controller
{
    //
}
