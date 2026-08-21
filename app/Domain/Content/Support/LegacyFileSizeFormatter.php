<?php

namespace App\Domain\Content\Support;

/**
 * var-item-{id}.htm parity: `functions.php:371-395`'s `CoolSize($size, $lang='ar')`
 * — used by `anasheed_details()`'s "حجم المادة" row and `list_anasheed_mirrors()`'s
 * per-mirror size column. Only the `$lang='ar'` branch is ported — no call
 * site on this page ever passes `'en'`.
 */
class LegacyFileSizeFormatter
{
    public static function format(int $bytes): string
    {
        $mb = 1024 * 1024;

        if ($bytes > $mb) {
            return sprintf('%01.2f', $bytes / $mb).' ميجا بايت';
        }

        if ($bytes >= 1024) {
            return sprintf('%01.2f', $bytes / 1024).' كيلو بايت';
        }

        if ($bytes === 0) {
            return 'غير معروف';
        }

        return $bytes.' بايت';
    }
}
