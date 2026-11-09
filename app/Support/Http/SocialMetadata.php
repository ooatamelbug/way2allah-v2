<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Value helpers for the shared layout's social-sharing tags (IF-056) — Gen 2
 * `header.php` emits `og:image` / `og:title` / `og:url` / `og:description` /
 * `og:type` / `twitter:image` / `image_src` / `videothumbnail` on every page.
 *
 * Not an SEO system: no storage, no settings. Absolute URLs are built from
 * the configured public application URL (`config('app.url')`, i.e.
 * `APP_URL`), never from the request's Host header — Gen 2 also took its
 * share image from the configured `$siteurl` / `$image_url`.
 */
final class SocialMetadata
{
    /** Gen 2 `header.php`: `$video_thumb = $siteurl."logo-light.png"` when no page image is set. */
    public const DEFAULT_IMAGE = 'logo-light.png';

    /**
     * Plain text from a rendered Blade section: tags removed, entities decoded
     * (sections are already escaped once), whitespace collapsed. The caller
     * escapes the result exactly once with `{{ }}`.
     */
    public static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function absoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url(self::publicRoot(), PHP_URL_SCHEME);

            return (is_string($scheme) && $scheme !== '' ? $scheme : 'https').':'.$url;
        }

        return self::publicRoot().'/'.ltrim($url, '/');
    }

    /** Gen 2 `og:url`: the requested path and query string, on the public host. */
    public static function currentUrl(Request $request): string
    {
        $query = (string) $request->server('QUERY_STRING', '');

        return self::publicRoot().$request->getPathInfo().($query !== '' ? '?'.$query : '');
    }

    public static function defaultImage(): string
    {
        return self::absoluteUrl(self::DEFAULT_IMAGE);
    }

    private static function publicRoot(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
