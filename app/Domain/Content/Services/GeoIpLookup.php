<?php

namespace App\Domain\Content\Services;

use Illuminate\Support\Facades\DB;

/**
 * Blueprint v1.0 §4's "GeoIP lookup service" — the manually-maintained
 * `ips` range table, named consumers `khotab`/`anasheed` comment posting.
 * Added post-Wave-4 (cross-wave review, decision-log #7) — both
 * `storeComment()` implementations hardcoded an empty country `code`
 * instead of calling this, despite it being a named Blueprint component
 * with confirmed, exercised consumers, not a hypothetical one.
 *
 * Reproduces `khotab/functions.php`'s `add_khotab_comment()` (and its
 * byte-for-byte identical `anasheed/functions.php` counterpart) IP-to-long
 * conversion and range lookup exactly: `startip_num < ip < endip_num`
 * (strict inequalities, not `<=`/`>=` — confirmed from the legacy SQL, not
 * assumed).
 *
 * `ips` (00-database-schema.md) has no primary key — a plain query via the
 * `main` connection is used directly rather than an Eloquent model, which
 * would need to fake a key that doesn't exist for a table this service
 * only ever reads once, one way.
 */
class GeoIpLookup
{
    /**
     * Returns the lowercased 2-letter country code for the given IPv4
     * address, or `''` if it doesn't resolve to any range in the table —
     * matching legacy's own fallback (an unmatched lookup leaves the
     * comment's `code` column effectively empty), not a new behavior.
     */
    public function codeForIp(string $ip): string
    {
        $octets = explode('.', $ip);

        if (count($octets) !== 4) {
            // Legacy's explode/intval arithmetic has no real equivalent for
            // a non-IPv4 address (IPv6, malformed input) — fail to an empty
            // code rather than guess, same "fail closed" precedent as
            // VbulletinSessionGuard's cookie validation.
            return '';
        }

        $ipLong = ((int) $octets[3])
            + ((int) $octets[2] * 256)
            + ((int) $octets[1] * 256 * 256)
            + ((int) $octets[0] * 256 * 256 * 256);

        $match = DB::connection('main')->table('ips')
            ->where('startip_num', '<', $ipLong)
            ->where('endip_num', '>', $ipLong)
            ->first(['code']);

        if ($match === null || $match->code === null) {
            return '';
        }

        return strtolower($match->code);
    }
}
