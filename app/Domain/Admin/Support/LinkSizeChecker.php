<?php

namespace App\Domain\Admin\Support;

use Illuminate\Support\Facades\Http;

/**
 * `stats.php`/`stats_khotab.php`/`telawah/stats.php`'s `getsizeid` branch's
 * outbound HTTP check, extracted as a small, stateless, model-agnostic
 * service — deliberately holds no knowledge of `Mirror`/`KhotabItem`/
 * `TelawahItem` at all. Used by `RecheckMirrorLinkSizeJob`/
 * `RecheckKhotabLinkSizeJob`/`RecheckTelawahLinkSizeJob` (Roadmap task
 * 6.7), each of which owns applying the result to its own concrete model.
 *
 * `$downgradeHttpsToHttp` reproduces a real, confirmed *inconsistency*
 * between the legacy files, not unified here: `stats.php`/`stats_khotab.php`
 * both rewrite `https:` to `http:` before checking (`str_replace('https:',
 * 'http:', $row->link)`); `telawah/stats.php` does not. Each job passes
 * its own module's real value — this service has no opinion of its own.
 */
class LinkSizeChecker
{
    public function check(string $link, bool $downgradeHttpsToHttp): int
    {
        $link = $downgradeHttpsToHttp ? str_replace('https:', 'http:', $link) : $link;

        try {
            return (int) Http::get($link)->header('Content-Length');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * An admin form's interactive size lookup (Fatwa X2 — OLD
     * `getfilesize()`, a cURL `NOBODY` request following redirects): a HEAD
     * request, so the media itself is never downloaded, with a short
     * timeout. Only an absolute http(s) URL is requested. Null when the size
     * cannot be learned — the caller keeps whatever the admin typed.
     */
    public function headContentLength(string $link, int $timeoutSeconds = 10): ?int
    {
        $link = trim($link);
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || (string) parse_url($link, PHP_URL_HOST) === '') {
            return null;
        }

        try {
            $response = Http::timeout($timeoutSeconds)->head($link);
        } catch (\Throwable) {
            return null;
        }

        $length = $response->header('Content-Length');

        return $response->successful() && ctype_digit($length) && (int) $length > 0 ? (int) $length : null;
    }
}
