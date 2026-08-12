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
}
