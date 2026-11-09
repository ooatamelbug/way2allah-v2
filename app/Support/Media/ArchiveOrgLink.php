<?php

namespace App\Support\Media;

/**
 * OLD `fix_archive_links()` (`way2allah_functions.php`), made safe.
 *
 * OLD rewrote any archive.org URL to `http://www.archive.org/download/{second-to-last segment}/{last segment}`
 * before every Anasheed download, mirror download and player. Stored links include retired
 * storage nodes (`ia331419.us.archive.org/3/items/{folder}/{file}`, …) that no longer serve the
 * file, while archive.org's own `/download/{folder}/{file}` still resolves to a live node
 * (verified 2026-11-04, decision-log #87).
 *
 * Only two shapes are normalised — `…/items/{folder}/{file…}` (storage nodes) and
 * `…/download/{folder}/{file…}` — to `https://archive.org/download/{folder}/{file…}`. Anything else
 * (another host, `/details/…`, a path without both a folder and a file) is returned unchanged: OLD's
 * blind rewrite turned a details-page link into a 404. The stored URL is never modified.
 */
final class ArchiveOrgLink
{
    private const CANONICAL = 'https://archive.org/download/';

    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'], $parts['path'])) {
            return $url;
        }

        $host = strtolower($parts['host']);
        if ($host !== 'archive.org' && ! str_ends_with($host, '.archive.org')) {
            return $url;
        }

        $segments = array_values(array_filter(explode('/', $parts['path']), fn (string $segment) => $segment !== ''));

        foreach ($segments as $index => $segment) {
            if ($segment !== 'items' && $segment !== 'download') {
                continue;
            }

            $folder = $segments[$index + 1] ?? null;
            $file = array_slice($segments, $index + 2);

            if ($folder === null || $file === []) {
                return $url;
            }

            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return self::CANONICAL.$folder.'/'.implode('/', $file).$query;
        }

        return $url;
    }
}
