<?php

use App\Support\Database\LegacyIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enhancement Batch E-08 — the two khotab listing indexes E-07 measured.
 *
 * These two families look like one problem and are two. Both filter on
 * `vedio`, but one sorts and the other ranges, so the column that must
 * come second differs and no single index can serve both — E-07 tested
 * both orderings of the same three columns to establish that:
 *
 *   idx_khotab_news_listing (vedio, weight, time)
 *     khotab/news.php's listing, ORDER BY weight DESC, time DESC LIMIT 50.
 *     Delivers rows already in sort order, so MySQL stops after 50
 *     instead of filesorting ~156,000. The third column is what makes
 *     it work: with only (vedio, weight) the optimizer cannot resolve
 *     the second sort key and ignores the index entirely.
 *       video 1021ms -> 2ms   fixed 823ms -> 41ms   audio 167ms -> 3ms
 *
 *   idx_khotab_day_listing (vedio, time)
 *     khotab/day.php's listing and every dated archive page,
 *     WHERE time >= ? AND time < ?. Turns a 156,341-row scan into a
 *     range read of the day's actual rows. The remaining filesort is
 *     deliberate — it sorts a few hundred rows at most.
 *       latest 805ms -> 2ms   densest 802ms -> 5ms   sparse 814ms -> 1ms
 *
 * `hidden` appears in both queries and in neither index: it excludes 87
 * rows out of 292,592, so it earns no position.
 *
 * Write cost is why these were approved where E-06's (vedio, hits) was
 * not. `time` is immutable after creation. `weight` is written only by
 * the legacy admin's manual reorder screen — never per view, unlike
 * `hits`, which is incremented on every single content view and stays
 * out of every index.
 *
 * Shares E-04's and E-05's guards for the shared `main` connection; see
 * the E-04 migration for why they are needed. The two ALTERs are kept
 * as independent statements rather than merged into one for
 * convenience, so a failure on the first is not obscured by the second.
 */
return new class extends Migration
{
    private const TABLE = 'nuke_islamic_khotab';

    /**
     * News first: it fixes the single worst route on the site.
     *
     * @var list<array{name: string, columns: list<string>}>
     */
    private const INDEXES = [
        ['name' => 'idx_khotab_news_listing', 'columns' => ['vedio', 'weight', 'time']],
        ['name' => 'idx_khotab_day_listing', 'columns' => ['vedio', 'time']],
    ];

    /**
     * E-15: structure-aware guard. On production `idx_khotab_day_listing`'s
     * columns are already carried by `idx_vedio_time (vedio, time)` — an
     * exact structural duplicate under a different name, which the previous
     * name-only check would have missed and re-created.
     */
    public function up(): void
    {
        $connection = DB::connection('main');

        if (! LegacyIndexGuard::applicable($connection, self::TABLE)) {
            return;
        }

        foreach (self::INDEXES as $index) {
            if (LegacyIndexGuard::alreadyCovered($connection, self::TABLE, $index['name'], $index['columns'])) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE',
                self::TABLE,
                $index['name'],
                self::columnList($index['columns']),
            ));
        }
    }

    /**
     * Name-based on purpose: this must only drop indexes this migration
     * created, never whatever else happens to cover the same columns.
     */
    public function down(): void
    {
        $connection = DB::connection('main');

        if (! LegacyIndexGuard::applicable($connection, self::TABLE)) {
            return;
        }

        foreach (array_reverse(self::INDEXES) as $index) {
            if (! LegacyIndexGuard::namedIndexExists($connection, self::TABLE, $index['name'])) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                self::TABLE,
                $index['name'],
            ));
        }
    }

    /** @param  list<string>  $columns */
    private static function columnList(array $columns): string
    {
        return implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns));
    }
};
