<?php

namespace App\Domain\Admin\Actions;

use Illuminate\Support\Facades\DB;

/**
 * `khotab/uploaders.php:31-42`'s `?op=update` handler — recomputes every
 * uploader's item counter and last-upload date from `nuke_islamic_khotab`
 * directly (correlated subqueries, matching the legacy SQL shape exactly),
 * on demand via an explicit admin action, not on every page load — a
 * different (correct) pattern from the `stats*.php` family's confirmed
 * unconditional-bulk-UPDATE-on-every-GET anti-pattern (Pattern E,
 * `admincp.md` §5) elsewhere in this module.
 */
class RecomputeUploaderStatsAction
{
    public function execute(): void
    {
        DB::connection('main')->statement(
            'UPDATE nuke_uploaders SET counter = ('.
            'SELECT COUNT(uploader) FROM nuke_islamic_khotab WHERE nuke_islamic_khotab.uploader = nuke_uploaders.email'.
            ')'
        );

        DB::connection('main')->statement(
            'UPDATE nuke_uploaders SET last_upload = ('.
            'SELECT nuke_islamic_khotab.addeddate FROM nuke_islamic_khotab '.
            'WHERE nuke_islamic_khotab.uploader = nuke_uploaders.email '.
            'ORDER BY nuke_islamic_khotab.addeddate DESC LIMIT 1'.
            ')'
        );
    }
}
