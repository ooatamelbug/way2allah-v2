<?php

namespace App\Domain\Content\Support;

/**
 * Visual parity audit (khotab-video-17.htm, Batch 2): `functions.php:357-365`'s
 * `Duration($Duration)` — formats a millisecond duration (`nuke_islamic_advanced.adur`)
 * as `h:i:s` once it exceeds one hour, or `00:i:s` otherwise. A static
 * class method rather than a global Blade `@php function` — Blade
 * templates in this app render more than once per test-suite run (a
 * global `function` declaration would fatal with "cannot redeclare
 * function" the second time the view renders in the same PHP process).
 * Presentation-layer only, not a broad legacy-helper port — this is the
 * one formatting function this batch's row markup actually needs.
 */
class LegacyDurationFormatter
{
    public static function format(int $milliseconds): string
    {
        $seconds = (int) floor($milliseconds / 1000);

        if ($milliseconds > 60 * 60 * 1000) {
            return gmdate('h:i:s', $seconds);
        }

        return '00:'.gmdate('i:s', $seconds);
    }
}
