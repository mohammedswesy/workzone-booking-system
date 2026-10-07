<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SecureImageStore
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Validate, re-encode (strip EXIF/GPS), store with a random name.
     *
     * @return array{path: string, sha256: string, mime: string}
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'local', string $field = 'proof'): array
    {
        $this->assertSafeImage($file, $field);

        $mime = $this->detectMime($file);
        $ext = self::ALLOWED_MIME[$mime];
        $binary = $this->reencode($file->getRealPath(), $mime, $field);
        $sha256 = hash('sha256', $binary);
        $name = Str::uuid()->toString().'.'.$ext;
        $path = trim($directory, '/').'/'.$name;

        if (! Storage::disk($disk)->put($path, $binary)) {
            throw new RuntimeException('Failed to store secure image.');
        }

        return [
            'path' => $path,
            'sha256' => $sha256,
            'mime' => $mime,
        ];
    }

    public function assertSafeImage(UploadedFile $file, string $field = 'proof'): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file is invalid.',
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => 'Images must be 5MB or smaller.',
            ]);
        }

        $mime = $this->detectMime($file);
        if (! isset(self::ALLOWED_MIME[$mime])) {
            throw ValidationException::withMessages([
                $field => 'Only JPG, PNG, and WebP images are allowed.',
            ]);
        }

        $path = $file->getRealPath();
        $info = @getimagesize($path);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw ValidationException::withMessages([
                $field => 'The file is not a valid image.',
            ]);
        }

        $raw = file_get_contents($path, false, null, 0, 512 * 1024) ?: '';
        if (preg_match('/<\?php|<script/i', $raw) === 1) {
            throw ValidationException::withMessages([
                $field => 'The file contains disallowed embedded content.',
            ]);
        }
    }

    private function detectMime(UploadedFile $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath()) ?: '';

        return strtolower($mime);
    }

    private function reencode(string $path, string $mime, string $field): string
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            throw ValidationException::withMessages([
                $field => 'Could not process the uploaded image.',
            ]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        }
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, null, 90),
            'image/png' => imagepng($canvas, null, 6),
            'image/webp' => imagewebp($canvas, null, 90),
        };
        imagedestroy($canvas);
        $binary = ob_get_clean();

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Failed to re-encode image.');
        }

        return $binary;
    }
}
