<?php

namespace App\Support;

/**
 * Build web-facing URLs for files on the public disk without baking APP_URL into them.
 */
class PublicStorageUrl
{
    public static function fromPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            if (preg_match('#/storage/(.+)$#', $path, $matches)) {
                return '/storage/'.$matches[1];
            }

            // Third-party absolute URLs are rejected so CSP img-src 'self' stays safe.
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
