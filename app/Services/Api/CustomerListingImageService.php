<?php

namespace App\Services\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/** Listing-only media; preserve the existing profile service and website behavior. */
class CustomerListingImageService
{
    public function store(int $listingId, UploadedFile $file): string
    {
        try {
            $bytes = (string) (new ImageManager(new Driver()))->read($file->getRealPath())
                ->scaleDown(width: 420, height: 340)->toJpeg(quality: 85);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['image' => 'Provide a decodable JPEG, PNG or WebP image.']);
        }
        $directory = realpath(public_path('frontend/img/listing'));
        $root = realpath(public_path());
        if (! $directory || ! $root || ! $this->inside($directory, $root) || ! is_writable($directory)) {
            throw new \RuntimeException('Listing image directory is unavailable.');
        }
        $name = 'api_listing_'.$listingId.'_'.bin2hex(random_bytes(16)).'.jpg';
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        $handle = @fopen($path, 'x+b');
        if ($handle === false) throw new \RuntimeException('Listing image could not be stored.');
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException('Listing image could not be stored.');
                $offset += $written;
            }
            if (! fflush($handle)) throw new \RuntimeException('Listing image could not be stored.');
        } catch (\Throwable $exception) {
            fclose($handle);@unlink($path); // Only this request's brand-new exclusively created file.
            throw $exception;
        }
        fclose($handle);

        return $name;
    }

    public function discardNew(string $name, int $listingId): void
    {
        if (! preg_match('/\Aapi_listing_'.preg_quote((string) $listingId, '/').'_[a-f0-9]{32}\.jpg\z/', $name)) return;
        $path = public_path('frontend/img/listing/'.$name);
        $real = realpath($path);$directory = realpath(public_path('frontend/img/listing'));
        if ($real && $directory && ! is_link($path) && $this->inside($real, $directory)) @unlink($path);
    }

    private function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);$root = str_replace('\\', '/', $root);
        if (PHP_OS_FAMILY === 'Windows') {$path = strtolower($path);$root = strtolower($root);}

        return str_starts_with($path, rtrim($root, '/').'/');
    }
}
