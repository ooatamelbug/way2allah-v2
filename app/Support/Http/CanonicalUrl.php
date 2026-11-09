<?php

namespace App\Support\Http;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Pagination\Paginator;
use RuntimeException;

/**
 * Canonical absolute-URL generation (N3, owner-approved option A, 2026-11-08).
 *
 * When `app.force_root_url` (APP_FORCE_ROOT_URL, default false) is enabled, every
 * absolute URL Laravel generates — `url()`, `route()`, `asset()`, redirects built
 * from paths or routes, and paginator links — uses the root and scheme of
 * `app.url` (APP_URL) instead of the incoming request's Host header and scheme.
 * Disabled, Laravel's normal request-based behaviour is untouched.
 *
 * APP_URL must then be an absolute http(s) URL with a host and nothing but an
 * optional path (no user info, port is allowed, no query or fragment). An
 * invalid value is never forced and never replaced by a guessed host: an HTTP
 * request fails with a RuntimeException naming the setting (not its value);
 * console commands only skip the forcing, so `config:clear` / `config:cache`
 * can still be run to fix the configuration.
 *
 * Only configuration values are read, so a cached configuration is honoured.
 * Not covered: code that builds URLs from the Request object itself (e.g. an
 * explicit `$request->url()`), relative URLs, and the media / thumbnail base
 * URLs (MEDIA_BASE_URL / THUMBNAIL_BASE_URL, relative by default).
 */
final class CanonicalUrl
{
    public static function apply(UrlGenerator $url, bool $runningInConsole): void
    {
        if (! (bool) config('app.force_root_url', false)) {
            return;
        }

        $root = self::validatedRoot(config('app.url'));

        if ($root === null) {
            if ($runningInConsole) {
                return;
            }

            throw new RuntimeException('APP_FORCE_ROOT_URL is enabled but APP_URL is not a valid absolute http(s) URL with a host.');
        }

        $url->forceRootUrl($root['root']);
        $url->forceScheme($root['scheme']);

        // The framework's default paginator path comes from $request->url(); route it through the
        // URL generator so pagination links use the same canonical root.
        Paginator::currentPathResolver(fn () => $url->current());
    }

    /** @return array{root: string, scheme: string}|null */
    public static function validatedRoot(mixed $appUrl): ?array
    {
        if (! is_string($appUrl) || trim($appUrl) === '' || trim($appUrl) !== $appUrl) {
            return null;
        }

        $parts = parse_url($appUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! self::validHost($host)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return ['root' => rtrim($appUrl, '/'), 'scheme' => $scheme];
    }

    private static function validHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
