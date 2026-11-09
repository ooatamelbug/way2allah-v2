<?php

namespace App\Domain\Content\Support;

/**
 * An item's public banner — OLD `Detailes()` (`modules/Khotab/index.php`):
 * `if ($Khotab['banner'] > 0)` → `media/banner/{id/1000}/{id}.gif` at its own
 * size (`getimagesize`), and `Codes()` for the three embed codes.
 *
 * Eligible only for `banner = 1` (a requested banner, -1, has no image), a
 * non-hidden item, and a file that exists — OLD printed a broken image when
 * the file was missing; not reproduced. Read from `admin_khotab.media_root`,
 * where «رفع بانر» stores it.
 */
final class KhotabBanner
{
    /** @return array{url: string, width: int, height: int}|null */
    public static function for(int $itemId, int $banner, int $hidden): ?array
    {
        if ($banner !== 1 || $hidden !== 0) {
            return null;
        }

        $relative = MediaPathResolver::path('banner', $itemId, 'gif');
        $file = rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr($relative, strlen('media/'));
        if (! is_file($file)) {
            return null;
        }

        $size = @getimagesize($file);

        return [
            'url' => MediaUrl::asset($relative),
            'width' => is_array($size) ? (int) $size[0] : 0,
            'height' => is_array($size) ? (int) $size[1] : 0,
        ];
    }
}
