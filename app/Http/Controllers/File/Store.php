<?php

namespace App\Http\Controllers\File;

use App\Data\File\StoredFile as Data;
use App\Enums\Uri;
use App\OpenApi\Post;
use App\OpenApi\Response\Response;
use App\OpenApi\Tag;
use App\Services\MediaStorage;
use App\Support\ContentAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class Store extends Controller
{
    #[Post(
        path: Uri::files,
        tag: Tag::files,
        summary: 'Загрузить изображение в S3',
    )]
    #[Response(201, Data::class)]
    public function __invoke(Request $request, MediaStorage $media): Data
    {
        ContentAccess::requireUser();

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'purpose' => ['required', 'in:avatar,card_set_logo'],
        ]);

        $directory = $validated['purpose'] === 'avatar' ? 'avatars' : 'card-set-logos';
        $path = $media->store($request->file('file'), $directory);

        return Data::from([
            'path' => $path,
            'url' => $media->url($path),
            'purpose' => $validated['purpose'],
        ]);
    }
}
