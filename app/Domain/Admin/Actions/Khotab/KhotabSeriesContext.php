<?php

namespace App\Domain\Admin\Actions\Khotab;

use Illuminate\Support\Facades\DB;

/**
 * The `Group` a Khotab series page hands to OLD's admin ops.
 *
 * OLD `Series()` (`modules/Khotab/index.php`) fetched the series' group row
 * and its panel links (`series_cp.htm`) and add-item form carried
 * `$Group[id]` — the series' `group_id` when that group row exists, and an
 * empty value (read as 0) when the series has no group or points at a
 * group that no longer exists. Every series operation that filters items
 * by `ser_id` and `group_id` together uses this value, as OLD did.
 */
final class KhotabSeriesContext
{
    public static function groupId(int $seriesGroupId): int
    {
        if ($seriesGroupId <= 0) {
            return 0;
        }

        return DB::connection('main')->table('nuke_islamic_groups')->where('id', $seriesGroupId)->exists() ? $seriesGroupId : 0;
    }
}
