<?php

namespace App\Support\Http;

/**
 * Whether a STORED, untrusted URL may be rendered as an `href` / `src` (F01: B6a's rule for new advertisement URLs,
 * B6b's report links, B6c's public advertising). Shared by the Admin and Content domains.
 *
 * Accepted: an absolute http(s) URL that `FILTER_VALIDATE_URL` accepts and that contains no whitespace, control
 * characters, `< > " ' \`` or `\` (OLD/Gen 2 renderers put these values in single- and double-quoted attributes and
 * in a JavaScript string). Anything else returns null and must not become a link or an image source.
 */
final class SafeExternalUrl
{
    public static function href(?string $url): ?string
    {
        $url = (string) $url;

        $safe = $url !== ''
            && preg_match('#^https?://#i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('/[\s\x00-\x1F\x7F<>"\'`\\\\]/u', $url) !== 1;

        return $safe ? $url : null;
    }
}
