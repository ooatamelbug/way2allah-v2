<?php

namespace App\Domain\Content\Support;

/**
 * Builds public URLs for the legacy media tree and thumbnail endpoint.
 *
 * Filesystem paths remain the responsibility of MediaPathResolver. Keeping
 * public URL generation separate lets the application serve media from a
 * different host without changing file-existence checks or download logic.
 */
final class MediaUrl
{
    public static function asset(string $path): string
    {
        $path = trim($path);

        if ($path === '' || self::isAbsoluteUrl($path)) {
            return $path;
        }

        $relativePath = ltrim($path, '/');

        if (str_starts_with($relativePath, 'media/')) {
            $relativePath = substr($relativePath, strlen('media/'));
        }

        $baseUrl = rtrim((string) config('media.base_url', '/media'), '/');
        $baseUrl = $baseUrl !== '' ? $baseUrl : '/media';

        return $baseUrl.'/'.$relativePath;
    }

    /**
     * Append a cache-busting `v` parameter (the project's `?v={filemtime}` convention) to an already-built URL,
     * keeping any query string it already has. For files replaced in place under the same name (an author's
     * `media/authors/{b}/{id}.jpg`): without it a browser keeps showing its cached copy, as Apache sends no
     * Cache-Control for media and browsers then cache heuristically (author-image feedback, 2026-11-18).
     */
    public static function withVersion(string $url, int $version): string
    {
        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version.$fragment;
    }

    /**
     * Prefix an already-built legacy query string with the configured
     * thumbnail endpoint. Query construction intentionally stays with each
     * caller so its established parameter order and source-path encoding do
     * not change.
     */
    public static function thumbnail(string $query): string
    {
        $endpoint = trim((string) config('media.thumbnail_url', '/thumbnails.php'));
        $endpoint = $endpoint !== '' ? $endpoint : '/thumbnails.php';

        return rtrim($endpoint, '?').'?'.ltrim($query, '?');
    }

    private static function isAbsoluteUrl(string $path): bool
    {
        return str_starts_with($path, '//')
            || filter_var($path, FILTER_VALIDATE_URL) !== false;
    }
}
