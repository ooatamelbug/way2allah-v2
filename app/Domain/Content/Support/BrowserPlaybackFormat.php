<?php

namespace App\Domain\Content\Support;

/**
 * B2 / F05-2 — the media-format facts the in-page player relies on, from the URL **path** only (never a substring of
 * the whole URL, so a query string, fragment, host or a file named `…mp4.wmv` never decides).
 *
 * `UNSUPPORTED` is the explicit, owner-approved list of legacy formats no current browser plays (Part 2, Option B,
 * decision-log #144): RealMedia, Windows Media, AVI and Flash video. It is deliberately a closed list: an extension
 * that is not in it (unknown, missing, or another legacy format) is **not** classified as unsupported and keeps the
 * existing behaviour. Being absent from this list says nothing about a file's codec either (an `.mp4` may still hold
 * a codec the browser cannot decode); no codec detection is attempted.
 *
 * F07-A (decision-log #147) adds `asf` (Windows Media), `ram` / `ra` (RealMedia metafile / RealAudio) and `mpg`
 * (MPEG-1/2 program stream). Browser- or codec-dependent formats (`3gp`, `ogv`, `m4v`, `ogg`) are deliberately NOT
 * listed.
 */
final class BrowserPlaybackFormat
{
    /** @var list<string> */
    public const UNSUPPORTED = ['rmvb', 'wmv', 'rm', 'avi', 'flv', 'wma', 'asf', 'ram', 'ra', 'mpg'];

    /** The lower-cased extension of the URL's parsed PATH (no query string, no fragment); '' when unparseable. */
    public static function pathExtension(string $link): string
    {
        $path = parse_url(trim($link), PHP_URL_PATH);

        return is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
    }

    public static function isUnsupported(string $link): bool
    {
        return in_array(self::pathExtension($link), self::UNSUPPORTED, true);
    }
}
