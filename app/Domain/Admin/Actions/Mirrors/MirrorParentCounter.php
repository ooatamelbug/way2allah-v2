<?php

namespace App\Domain\Admin\Actions\Mirrors;

use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 1 — the two pieces of mirror arithmetic that
 * Khotab and Anasheed share exactly, kept in one place so the subtle parts
 * are written once. Deliberately not a CRUD base class: every operation
 * lives in its own explicit, per-domain action, because the two domains'
 * legacy behavior genuinely differs (Pass 3E.1 §3.2).
 *
 * **Parent counters are compatibility state, not truth** (3E.1 invariant
 * I-2). Production drift is 3.92% for Khotab and 0.23% for Anasheed, so
 * nothing in Laravel reads `parent.mirror` as the real count — the
 * relation is authoritative. These methods only keep the legacy column
 * moving the way OLD moved it (+1 / -1), so OLD's own pages, which do read
 * it, keep working during coexistence. They never recompute it: setting it
 * to the real count would silently repair historical drift row by row,
 * which the plan forbids as part of CRUD.
 *
 * Both updates are single atomic `SET mirror = mirror ± 1` statements, so
 * a concurrent OLD write to the same parent cannot be lost.
 */
final class MirrorParentCounter
{
    /**
     * OLD insert: `SET lastmirror = $time, mirror = mirror + 1`.
     * OLD re-parent: `SET mirror = mirror + 1` with lastmirror untouched —
     * pass null for that case.
     */
    public static function increment(string $parentTable, int $parentId, ?int $lastmirror = null): void
    {
        $values = ['mirror' => DB::raw('mirror + 1')];

        if ($lastmirror !== null) {
            $values['lastmirror'] = $lastmirror;
        }

        DB::connection('main')->table($parentTable)->where('id', $parentId)->update($values);
    }

    /**
     * OLD delete / re-parent source: `SET mirror = mirror - 1`.
     *
     * **One deliberate divergence: floored at zero.** OLD's bare
     * `mirror - 1` would take a counter already at 0 below zero. On an
     * unsigned column under strict SQL mode that is an error, which would
     * abort the whole transaction and make the mirror undeletable — the
     * "unsafe decrement path" the plan rules out. Production has exactly
     * this state today: 2 Khotab parents with `mirror = 0` that still own
     * mirrors (Pass 3E.1 Check D1, `counter_zero_but_has_mirrors`). The
     * floor changes nothing for any counter above zero, and repairs nothing
     * either — it only refuses to make a wrong value more wrong.
     *
     * `CASE` rather than `GREATEST()` so the same SQL runs on MySQL and on
     * the SQLite test connection.
     */
    public static function decrement(string $parentTable, int $parentId): void
    {
        DB::connection('main')->table($parentTable)->where('id', $parentId)->update([
            'mirror' => DB::raw('CASE WHEN mirror > 0 THEN mirror - 1 ELSE 0 END'),
        ]);
    }

    /**
     * OLD: `(int) (1024 * 1024 * (float) $input)` — truncation, not
     * rounding, reproduced exactly (3E.1 invariant I-8). Used only by the
     * two *insert* paths; Anasheed's edit path takes bytes directly.
     */
    public static function megabytesToBytes(mixed $megabytes): int
    {
        return (int) (1024 * 1024 * (float) ($megabytes ?? 0));
    }
}
