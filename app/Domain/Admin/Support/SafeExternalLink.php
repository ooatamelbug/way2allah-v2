<?php

namespace App\Domain\Admin\Support;

use App\Support\Http\SafeExternalUrl;

/**
 * F01 / B6b — whether a STORED, untrusted URL may be rendered as a clickable link in AdminCP.
 *
 * Same strictness as B6a's rule for new advertisement URLs (`AdRequest`): an absolute http(s) URL that
 * `FILTER_VALIDATE_URL` accepts and that contains no whitespace, control characters, `< > " ' \`` or `\`.
 * Anything else is shown as escaped text only — never as an `href`.
 */
final class SafeExternalLink
{
    public static function href(?string $url): ?string
    {
        return SafeExternalUrl::href($url);   // the rule lives in App\Support (shared with B6c's public rendering)
    }
}
