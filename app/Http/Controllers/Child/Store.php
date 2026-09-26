<?php

namespace App\Http\Controllers\Child;

use App\Data\Child\Child;
use App\Data\Child\ChildStore as Data;
use App\Enums\RoleName;
use App\Enums\Uri;
use App\Models\User;
use App\OpenApi\Post;
use App\OpenApi\Request\RequestBody;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Store extends Controller
{
    #[Post(
        path: Uri::children,
        tag: Tag::children,
        summary: 'Создать аккаунт ребёнка',
        description: 'Право `children.manage`. Ребёнок — роль student без email, входит по логину (`POST /login` с полем `login`).',
    )]
    #[RequestBody(Data::class)]

    #[Response(201, Child::class)]
    public function __invoke(Request $request, Data $data): Child
    {
        $parent = $request->user();

        $child = DB::transaction(function () use ($parent, $data) {
            $child = new User;
            $child->forceFill([
                'name' => $data->name,
                'username' => $data->username,
                'grade' => $data->grade,
                'password' => Hash::make($data->password),
                'created_by_id' => $parent->id,
            ])->save();

            $child->syncRoles([RoleName::student->value]);
            $parent->children()->attach($child);

            return $child;
        });

        return Child::from($child->fresh());
    }
}
