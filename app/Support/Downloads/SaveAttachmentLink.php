<?php

namespace App\Support\Downloads;

/**
 * F04 «حفظ المادة» — mark a redirect-delivered download so the TARGET HOST can answer it as an attachment.
 *
 * Only a host the site operates can make a cross-origin file download instead of opening it: the browser decides
 * from the file response's own `Content-Disposition`, and an HTML `download` attribute is ignored cross-origin.
 * PHP never touches the bytes here (no proxy; the Apache worker / PHP-CGI protections are untouched): the visitor's
 * browser still fetches the file directly from that host.
 *
 * For a link whose host is EXACTLY one of `download_save.attachment_query_hosts` (empty by default), the redirect target
 * gets `w2a_save=1`; the host's web-server rule (docs/reports/f04-save-material-download-results.md §3) answers such
 * requests with `Content-Disposition: attachment`. Every other link — archive.org, third-party hosts, anything that
 * fails the strict checks below — is returned byte-for-byte unchanged, so behaviour there is exactly as before.
 *
 * Strict, like ExternalMediaLink: scheme http/https; exact host (case-insensitive, trailing dot removed — no suffix
 * or substring match); no userinfo, no "@" or "\" in the authority; default port only; ≤ 2048 bytes; no whitespace
 * or control characters.
 */
final class SaveAttachmentLink
{
    public const PARAM = 'w2a_save';

    public static function apply(string $url): string
    {
        $hosts = (array) config('download_save.attachment_query_hosts', []);
        if ($hosts === [] || $url === '' || strlen($url) > ExternalMediaLink::MAX_BYTES || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return $url;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return $url;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || ! str_starts_with(strtolower($url), $scheme.'://')) {
            return $url;
        }
        if (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80)) {
            return $url;
        }
        $authority = substr($url, strlen($scheme) + 3, strcspn($url, '/?#', strlen($scheme) + 3));
        if (str_contains($authority, '@') || str_contains($url, '\\')) {
            return $url;
        }
        $host = rtrim(strtolower($parts['host']), '.');
        if (! in_array($host, $hosts, true)) {
            return $url;
        }
        if (isset($parts['query']) && preg_match('/(^|&)'.self::PARAM.'=/', $parts['query']) === 1) {
            return $url;
        }

        $fragmentAt = strpos($url, '#');
        $base = $fragmentAt === false ? $url : substr($url, 0, $fragmentAt);
        $fragment = $fragmentAt === false ? '' : substr($url, $fragmentAt);
        $separator = str_contains($base, '?') ? (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&') : '?';

        return $base.$separator.self::PARAM.'=1'.$fragment;
    }
}
