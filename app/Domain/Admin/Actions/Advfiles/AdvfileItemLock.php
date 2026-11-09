<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — reads the item's workflow row under a row lock, inside the
 * caller's transaction, so two concurrent transitions on one item serialize.
 */
final class AdvfileItemLock
{
    public static function lock(KhotabItem $item): object
    {
        $row = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item->getKey())->lockForUpdate()
            ->first(['id', 'advfile', 'author', 'group_id', 'ser_id']);
        abort_if($row === null, 404);

        return $row;
    }

    /** @return array{author: int, group: int, series: int} */
    public static function parents(object $row): array
    {
        return ['author' => (int) $row->author, 'group' => (int) $row->group_id, 'series' => (int) $row->ser_id];
    }
}
