<?php

namespace App\Support\Downloads;

/**
 * YouTube / SoundCloud page links stored as Khotab mirrors (Phase 3B.1, owner-approved policy in
 * docs/operations/download-external-media-policy.md). Pure string classification: nothing is fetched, no redirect is
 * followed server-side.
 *
 * A link qualifies only when ALL hold: scheme http or https (the redirect is always https); host EXACTLY one of the
 * allowlist (case-insensitive, an optional trailing dot removed — no suffix or substring match); no userinfo; no port
 * other than the scheme's default (80/443); at most 2048 bytes; no whitespace or control characters anywhere. The
 * redirect target is rebuilt from the parsed parts, never echoed raw.
 */
final class ExternalMediaLink
{
    public const MAX_BYTES = 2048;

    /** @var array<string, 'youtube'|'soundcloud'> */
    public const HOSTS = [
        'youtube.com' => 'youtube', 'www.youtube.com' => 'youtube', 'm.youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'soundcloud.com' => 'soundcloud', 'www.soundcloud.com' => 'soundcloud', 'm.soundcloud.com' => 'soundcloud',
        'on.soundcloud.com' => 'soundcloud',
    ];

    /** @return array{url: string, kind: 'youtube'|'soundcloud', host: string}|null */
    public static function classify(?string $link): ?array
    {
        if ($link === null || $link === '' || strlen($link) > self::MAX_BYTES || preg_match('/[\x00-\x20\x7F]/', $link) === 1) {
            return null;
        }
        $parts = parse_url($link);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80)) {
            return null;
        }
        // parse_url() accepts "@" and "\" tricks in odd places; the authority must be exactly the host (and port).
        $authority = substr($link, strlen($parts['scheme']) + 3, strcspn($link, '/?#', strlen($parts['scheme']) + 3));
        if (! str_starts_with(strtolower($link), $scheme.'://') || str_contains($authority, '@') || str_contains($link, '\\')) {
            return null;
        }
        $host = rtrim(strtolower($parts['host']), '.');
        $kind = self::HOSTS[$host] ?? null;
        if ($kind === null) {
            return null;
        }

        $url = 'https://'.$host.($parts['path'] ?? '/')
            .(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '');

        return ['url' => $url, 'kind' => $kind, 'host' => $host];
    }
}
