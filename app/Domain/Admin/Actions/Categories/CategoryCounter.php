<?php

namespace App\Domain\Admin\Actions\Categories;

use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 3 — the per-category usage counters on
 * `nuke_w2a_cat` (`video_count` for Khotab items, series and groups;
 * `anasheed_count` for Anasheed items), moved exactly as OLD moved them:
 * `+1` unconditionally, `-1` only while above zero
 * (`WHERE id = ? AND video_count > 0`).
 *
 * A bulk operation moves a counter by the number of rows it actually
 * changed, in one statement. That is the same end value as OLD's one-row-
 * at-a-time loop: n floored single decrements equal `max(0, v - n)`.
 * Like Phase 1's mirror counter, these are compatibility values only — never
 * recomputed, never repaired.
 */
final class CategoryCounter
{
    public const KHOTAB = 'video_count';

    public const ANASHEED = 'anasheed_count';

    /**
     * The column name is interpolated into raw SQL below, so only these two
     * known constants are ever accepted.
     */
    private static function column(string $column): string
    {
        if (! in_array($column, [self::KHOTAB, self::ANASHEED], true)) {
            throw new \InvalidArgumentException("CategoryCounter: unknown counter column '{$column}'.");
        }

        return $column;
    }

    public static function increment(int $categoryId, string $column, int $by = 1): void
    {
        $column = self::column($column);

        if ($by < 1) {
            return;
        }

        DB::connection('main')->table('nuke_w2a_cat')->where('id', $categoryId)
            ->update([$column => DB::raw("{$column} + {$by}")]);
    }

    public static function decrement(int $categoryId, string $column, int $by = 1): void
    {
        $column = self::column($column);

        if ($by < 1) {
            return;
        }

        DB::connection('main')->table('nuke_w2a_cat')->where('id', $categoryId)
            ->update([$column => DB::raw("CASE WHEN {$column} >= {$by} THEN {$column} - {$by} ELSE 0 END")]);
    }
}
