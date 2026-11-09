<?php

namespace App\Domain\Admin\Actions\Khotab;

use Illuminate\Support\Facades\DB;

/**
 * OLD `update_author_location` / `update_group_location` /
 * `update_series_location` (`modules/Khotab/admin/index.php:5170-5246`,
 * production working copy): after an item's location or container changes,
 * recount the items of that author / group / series at the location and
 * store the count in the matching `nuke_islamic_*_location` row, inserting
 * the row when none exists.
 *
 * Reproduced exactly, including one OLD edge: when the recount is zero and no
 * row exists yet, OLD's INSERT took its `location_id` from an empty result
 * and failed silently — so no row is created. Callers run inside their own
 * transaction.
 */
final class KhotabLocationCounts
{
    private const KINDS = [
        'author' => ['table' => 'nuke_islamic_authors_location', 'owner' => 'author_id', 'item_key' => 'author'],
        'group' => ['table' => 'nuke_islamic_groups_location', 'owner' => 'group_id', 'item_key' => 'group_id'],
        'series' => ['table' => 'nuke_islamic_series_location', 'owner' => 'series_id', 'item_key' => 'ser_id'],
    ];

    public function update(string $kind, int $ownerId, int $locationId): void
    {
        if ($locationId <= 0 || $ownerId <= 0 || ! isset(self::KINDS[$kind])) {
            return;
        }

        ['table' => $table, 'owner' => $owner, 'item_key' => $itemKey] = self::KINDS[$kind];
        $main = DB::connection('main');

        $count = (int) $main->table('nuke_islamic_khotab')
            ->where('location_id', $locationId)->where($itemKey, $ownerId)->count();

        $record = $main->table($table)->where($owner, $ownerId)->where('location_id', $locationId)->first(['id']);

        if ($record !== null) {
            $main->table($table)->where('id', $record->id)->update(['count' => $count]);

            return;
        }

        if ($count > 0) {
            $main->table($table)->insert([$owner => $ownerId, 'location_id' => $locationId, 'count' => $count]);
        }
    }
}
