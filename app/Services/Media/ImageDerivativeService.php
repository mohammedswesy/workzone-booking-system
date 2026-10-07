<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Generate public card (~800px) and gallery (~1600px) WebP derivatives.
 * Original files are never modified.
 */
class ImageDerivativeService
{
    public const CARD_MAX = 800;

    public const GALLERY_MAX = 1600;

    /**
     * @return array{card: ?string, gallery: ?string}
     */
    public function ensureForPath(string $relativePath, string $disk = 'public'): array
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            return ['card' => null, 'gallery' => null];
        }

        $card = $this->derivativeRelativePath($relativePath, 'card');
        $gallery = $this->derivativeRelativePath($relativePath, 'gallery');

        if (! Storage::disk($disk)->exists($card)) {
            $this->writeDerivative($disk, $relativePath, $card, self::CARD_MAX);
        }
        if (! Storage::disk($disk)->exists($gallery)) {
            $this->writeDerivative($disk, $relativePath, $gallery, self::GALLERY_MAX);
        }

        return [
            'card' => Storage::disk($disk)->exists($card) ? $card : null,
            'gallery' => Storage::disk($disk)->exists($gallery) ? $gallery : null,
        ];
    }

    public function derivativeRelativePath(string $originalRelative, string $size): string
    {
        $originalRelative = ltrim($originalRelative, '/');
        $dir = trim(dirname($originalRelative), '.\\/');
        $base = pathinfo($originalRelative, PATHINFO_FILENAME);

        return ($dir !== '' ? $dir.'/' : '').'derivatives/'.$size.'/'.$base.'.webp';
    }

    public function deleteDerivatives(string $originalRelative, string $disk = 'public'): void
    {
        foreach (['card', 'gallery'] as $size) {
            $path = $this->derivativeRelativePath($originalRelative, $size);
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function writeDerivative(string $disk, string $sourceRelative, string $destRelative, int $maxEdge): void
    {
        $absolute = Storage::disk($disk)->path($sourceRelative);
        $binary = $this->resizeToWebp($absolute, $maxEdge);
        if ($binary === null) {
            return;
        }

        Storage::disk($disk)->put($destRelative, $binary);
    }

    private function resizeToWebp(string $absolutePath, int $maxEdge): ?string
    {
        if (! is_file($absolutePath) || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? '';

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if ($src === false) {
            return null;
        }

        $scale = min(1.0, $maxEdge / max($width, $height));
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        if (! function_exists('imagewebp')) {
            imagedestroy($dst);

            return null;
        }

        ob_start();
        imagewebp($dst, null, 82);
        imagedestroy($dst);
        $binary = ob_get_clean();

        return ($binary === false || $binary === '') ? null : $binary;
    }
}
