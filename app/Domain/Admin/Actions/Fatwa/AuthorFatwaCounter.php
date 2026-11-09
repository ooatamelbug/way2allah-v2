<?php

namespace App\Domain\Admin\Actions\Fatwa;

use Illuminate\Support\Facades\DB;

/**
 * Owner decision 2 (2026-10-30): `nuke_islamic_authors.fatwa` is kept correct
 * when Q1 adds a fatwa, A5 deletes one and A4 changes its sheikh — OLD
 * maintained it in none of the three.
 *
 * The counter is recounted from the rows that exist (`COUNT(*)` of the
 * author's fatwas) instead of `± 1`: the result is right whatever the stored
 * value was, can never go negative, and an author change leaves both the old
 * and the new sheikh exact. It runs inside the caller's transaction, after
 * the fatwa write, with the author rows locked, so a failure rolls the
 * fatwa and the counters back together.
 *
 * Only the authors an operation touches are recounted — no historical sweep.
 * Laravel's own author listings derive their counts and do not read this
 * column; it is maintained for OLD and any other reader of the table.
 */
final class AuthorFatwaCounter
{
    /**
     * @param  iterable<int|null>  $authorIds
     * @return array<int, array{from: int, to: int}> per existing author
     */
    public static function recount(iterable $authorIds): array
    {
        $ids = [];
        foreach ($authorIds as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        sort($ids);

        $main = DB::connection('main');
        $current = $main->table('nuke_islamic_authors')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('fatwa', 'id');

        $result = [];
        foreach ($current as $id => $stored) {
            $count = $main->table('nuke_fatwa_questions')->where('auther_id', $id)->count();
            if ((int) $stored !== $count) {
                $main->table('nuke_islamic_authors')->where('id', $id)->update(['fatwa' => $count]);
            }
            $result[(int) $id] = ['from' => (int) $stored, 'to' => $count];
        }

        return $result;
    }
}
