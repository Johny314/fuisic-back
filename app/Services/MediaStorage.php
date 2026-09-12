<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorage
{
    public function disk(): string
    {
        $disk = (string) config('filesystems.default', 'public');
        $bucket = config("filesystems.disks.{$disk}.bucket");

        if ($disk === 's3' && blank($bucket)) {
            return 'public';
        }

        return $disk;
    }

    public function store(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        $path = $file->store($directory, [
            'disk' => $this->disk(),
            'visibility' => 'public',
        ]);

        if (! $path) {
            abort(500, 'Не удалось сохранить файл');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk($this->disk())->url($path);
        if (! app()->environment('local')) {
            return $url;
        }

        $requestHost = request()?->getHost();
        if (! $requestHost || in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            return $url;
        }

        return (string) preg_replace(
            '#https?://(?:localhost|127\.0\.0\.1)(:\d+)?#',
            'http://'.$requestHost.'$1',
            $url,
        );
    }
}
