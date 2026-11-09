<?php

namespace App\Domain\Content\Support;

/**
 * A CD release's banner — OLD `UploadBanner` (`modules/w2acd/admin/index.php`)
 * writes `media/w2acd/banner/{floor(id/1000)}/{id}.gif` and sets `banner = 1`;
 * OLD `Codes()` (`modules/w2acd/index.php`, `cds-code-{id}-{type}.htm`) reads
 * that file at its own size (`getimagesize`) for a release with `banner = 1`
 * and `hidden = 0`.
 *
 * Read from `admin_cds.media_root`, where «رفع بانر» stores it. A missing file
 * is no banner (OLD printed a broken image; not reproduced).
 */
final class CdBanner
{
    /** The banner's public path, `media/w2acd/banner/…`. */
    public static function path(int $releaseId): string
    {
        return MediaPathResolver::path('w2acd/banner', $releaseId, 'gif');
    }

    /** Where that file lives on disk. */
    public static function absolutePath(int $releaseId): string
    {
        return rtrim((string) config('admin_cds.media_root'), '/').'/'.substr(self::path($releaseId), strlen('media/'));
    }

    /**
     * The banner `cds-code` embeds: a stable URL, as OLD's codes carried.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public static function for(int $releaseId, int $banner, int $hidden): ?array
    {
        if ($banner !== 1 || $hidden !== 0) {
            return null;
        }

        return self::describe($releaseId, false);
    }

    /**
     * The stored file regardless of visibility, cache-busted — the admin
     * banner pages show it whenever there is one.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public static function onDisk(int $releaseId): ?array
    {
        return self::describe($releaseId, true);
    }

    /** @return array{url: string, width: int, height: int}|null */
    private static function describe(int $releaseId, bool $versioned): ?array
    {
        $file = self::absolutePath($releaseId);
        if (! is_file($file)) {
            return null;
        }

        $size = @getimagesize($file);
        $url = MediaUrl::asset(self::path($releaseId));

        return [
            'url' => $versioned ? $url.'?v='.(int) filemtime($file) : $url,
            'width' => is_array($size) ? (int) $size[0] : 0,
            'height' => is_array($size) ? (int) $size[1] : 0,
        ];
    }
}
