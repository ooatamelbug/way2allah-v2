<?php

namespace App\Domain\Content\Support;

/**
 * A CD group's icon — OLD `UploadIcon` (`modules/w2acd/admin/index.php`)
 * writes `media/w2acd/icons/{floor(id/1000)}/{id}.jpg` and sets `icon = 1`;
 * OLD `ListGroup()` (`modules/w2acd/index.php`) shows that file on the
 * sub-group card when `icon = 1`, otherwise `images/way2_cddefault.png`.
 *
 * Read from `admin_cds.media_root`, where «اضافة صورة للقسم» stores it. A
 * missing file shows the default (OLD printed a broken image; not reproduced).
 */
final class CdGroupIcon
{
    public const DEFAULT = '/images/way2_cddefault.png';

    /** The icon's public path, `media/w2acd/icons/…`. */
    public static function path(int $groupId): string
    {
        return MediaPathResolver::path('w2acd/icons', $groupId, 'jpg');
    }

    /** Where that file lives on disk. */
    public static function absolutePath(int $groupId): string
    {
        return rtrim((string) config('admin_cds.media_root'), '/').'/'.substr(self::path($groupId), strlen('media/'));
    }

    /** The stored icon, cache-busted, or null when the group has none. */
    public static function current(int $groupId, int $icon): ?string
    {
        $file = self::absolutePath($groupId);
        if ($icon !== 1 || ! is_file($file)) {
            return null;
        }

        return MediaUrl::asset(self::path($groupId)).'?v='.(int) filemtime($file);
    }

    /** What the sub-group card shows. */
    public static function forCard(int $groupId, int $icon): string
    {
        return self::current($groupId, $icon) ?? self::DEFAULT;
    }
}
