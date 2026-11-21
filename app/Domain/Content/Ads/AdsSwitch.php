<?php

namespace App\Domain\Content\Ads;

/**
 * F01 / B6c-1 — the global public-advertising switches (`config/ads.php`). Only a strict boolean `true` counts:
 * a missing, empty or malformed value keeps advertising OFF.
 */
final class AdsSwitch
{
    public static function renderingEnabled(): bool
    {
        return config('ads.rendering_enabled') === true;
    }

    public static function popupEnabled(): bool
    {
        return self::renderingEnabled() && config('ads.popup_enabled') === true;
    }
}
