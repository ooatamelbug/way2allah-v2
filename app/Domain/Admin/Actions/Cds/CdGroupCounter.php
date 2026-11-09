<?php

namespace App\Domain\Admin\Actions\Cds;

use Illuminate\Support\Facades\DB;

/**
 * Phase 6 — `nuke_w2acd_groups.w2acd`, the per-group release counter OLD
 * moved by +1 on create and −1 on delete. Group 0 (the main list) has no row
 * and is skipped; a decrement never goes below 0. No other counter is
 * repaired.
 */
final class CdGroupCounter
{
    /** @return array{id: int, before: int, after: int}|null */
    public static function move(int $groupId, int $delta): ?array
    {
        if ($groupId < 1) {
            return null;
        }
        $main = DB::connection('main');
        $row = $main->table('nuke_w2acd_groups')->where('id', $groupId)->lockForUpdate()->first(['id', 'w2acd']);
        if ($row === null) {
            return null;
        }
        $before = (int) $row->w2acd;
        $after = max(0, $before + $delta);
        if ($after !== $before) {
            $main->table('nuke_w2acd_groups')->where('id', $groupId)->update(['w2acd' => $after]);
        }

        return ['id' => $groupId, 'before' => $before, 'after' => $after];
    }

    /** OLD: a group id with no row became 0 (`if ($Group[id] != $group_id) $group_id = 0`). */
    public static function resolve(int $groupId): int
    {
        return $groupId > 0 && DB::connection('main')->table('nuke_w2acd_groups')->where('id', $groupId)->exists() ? $groupId : 0;
    }
}
