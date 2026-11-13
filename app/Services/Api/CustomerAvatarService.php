<?php

namespace App\Services\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/** Existing public profile directory; no new disk, symlink or storage infrastructure. */
class CustomerAvatarService
{
    public function store(int $userId, UploadedFile $file): string
    {
        try {
            $bytes = (string) (new ImageManager(new Driver()))->read($file->getRealPath())
                ->scaleDown(width: 512, height: 512)->toJpeg(quality: 85);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['avatar' => 'The avatar must be a decodable JPEG, PNG or WebP image.']);
        }
        $directory = realpath(public_path('frontend/img/profiles'));
        $root = realpath(public_path());
        if (! $directory || ! $root || ! $this->inside($directory, $root) || ! is_writable($directory)) {
            throw new \RuntimeException('Profile image directory is unavailable.');
        }
        $name = 'api_user_'.$userId.'_'.bin2hex(random_bytes(16)).'.jpg';
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new \RuntimeException('Profile image could not be stored.');
        }
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Profile image could not be stored.');
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new \RuntimeException('Profile image could not be stored.');
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($path); // Only the new, exclusively created file from this request.
            throw $exception;
        }
        fclose($handle);

        return $name;
    }

    public function discardNew(string $name, int $userId): void
    {
        if (! preg_match('/\Aapi_user_'.preg_quote((string) $userId, '/').'_[a-f0-9]{32}\.jpg\z/', $name)) {
            return;
        }
        $path = public_path('frontend/img/profiles/'.$name);
        $real = realpath($path);
        $directory = realpath(public_path('frontend/img/profiles'));
        if ($real && $directory && ! is_link($path) && $this->inside($real, $directory)) {
            @unlink($path);
        }
    }

    private function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $root);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return str_starts_with($path, rtrim($root, '/').'/');
    }
}
