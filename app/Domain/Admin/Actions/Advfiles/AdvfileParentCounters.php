<?php

namespace App\Domain\Admin\Actions\Advfiles;

use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — the `advfile` counters on the item's author, group and series.
 * OLD ConfirmInsertAdvFile / ConfirmDeleteAdvFile moved them by ±1
 * (`advfiles/admin/index.php:101-103, 189-191`); OLD's public advfiles module
 * lists authors / groups / series `WHERE advfile > 0` and shows the number.
 *
 * Reproduced ±1, with two integrity corrections: an id of 0 (no group / no
 * series) is skipped instead of issuing a no-match UPDATE, and a decrement
 * never goes below 0. No other counter is repaired.
 */
final class AdvfileParentCounters
{
    private const PARENTS = [
        'author' => 'nuke_islamic_authors',
        'group' => 'nuke_islamic_groups',
        'series' => 'nuke_islamic_series',
    ];

    /**
     * @param  array{author: int, group: int, series: int}  $ids
     * @return array<string, array{id: int, before: int, after: int}>
     */
    public static function move(array $ids, int $delta): array
    {
        $main = DB::connection('main');
        $moved = [];

        foreach (self::PARENTS as $key => $table) {
            $id = $ids[$key];
            if ($id < 1) {
                continue;
            }
            $row = $main->table($table)->where('id', $id)->lockForUpdate()->first(['id', 'advfile']);
            if ($row === null) {
                continue;
            }
            $before = (int) $row->advfile;
            $after = max(0, $before + $delta);
            if ($after !== $before) {
                $main->table($table)->where('id', $id)->update(['advfile' => $after]);
            }
            $moved[$key] = ['id' => $id, 'before' => $before, 'after' => $after];
        }

        return $moved;
    }
}
