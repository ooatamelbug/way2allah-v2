<?php

namespace App\Domain\Admin\Actions;

use Illuminate\Support\Facades\DB;

/**
 * `stats.php:7`/`stats_khotab.php:7`/`telawah/stats.php:7`'s unconditional
 * `UPDATE {table} SET percent = (online/linksize)*100`, run on every page
 * load in the legacy source (Pattern E, `admincp.md` §5) — reproduced as
 * an explicit admin action instead (task 6.7), matching the same
 * on-demand-not-every-GET correction already applied to `nuke_uploaders`'
 * counters in `RecomputeUploaderStatsAction`. Division-by-zero (`linksize`
 * = 0) is reproduced as-is (MySQL yields `NULL`, matching legacy's own
 * unguarded behavior) — not a fix, not this action's concern.
 *
 * Multiplies before dividing (`(online * 100) / linksize`, not legacy's
 * literal `(online / linksize) * 100`) — mathematically identical under
 * MySQL, whose `/` always performs float division regardless of operand
 * order, but SQLite's `/` performs integer division when both operands
 * are integers, silently truncating `online/linksize` to `0` before the
 * `*100` ever runs. Driver-aware in effect, not in code — same
 * discipline as `LiveStreamController::titleOrderClause()`/
 * `LocationsController::titleOrderClause()`, needed here so the test
 * suite can execute this statement at all, not a production behavior
 * change.
 */
class RecomputeLinkQualityPercentAction
{
    public function execute(string $table): void
    {
        DB::connection('main')->statement(
            "UPDATE {$table} SET percent = (online * 100) / linksize"
        );
    }
}
