<?php

use App\Support\Database\LegacyIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enhancement Batch E-05 — one index supporting every PDF listing query.
 *
 * `pdf > 0` reads like a range predicate that an index can only partly
 * serve, but on this data it is overwhelmingly selective: 1,154 of
 * 292,592 rows, or 0.39% of the table. That single fact decides the
 * whole design. Once the optimizer can jump straight to those 1,154
 * rows, the `ORDER BY` that follows — `pdf_time`, `hits`, or
 * `weight, pdf_time` — is a filesort over a trivially small set and
 * costs nothing worth indexing away:
 *
 *   khotab/dump.php       LIMIT 50, ORDER BY pdf_time   228.6ms →  7.1ms
 *   home latest dumps     LIMIT 3,  ORDER BY pdf_time   206.4ms →  5.0ms
 *   pdf sidebar           LIMIT 5,  ORDER BY hits       227.7ms →  4.5ms
 *   khotab/news.php       unlimited, ORDER BY weight    280.6ms → 17.4ms
 *
 * Every one drops from a 273,897-row full scan to a 1,154-row range
 * read. The filesort remains in all four EXPLAINs and is deliberately
 * left there — reading the rows was the cost, never sorting them.
 *
 * Wider candidates were measured and rejected. `(pdf, pdf_time)` saved
 * 2.4ms on one query and nothing on the rest, because a range on the
 * leading column stops MySQL 5.7 using the next column to satisfy
 * ORDER BY. `(pdf_time)` alone was faster still for the two pdf_time
 * queries but left the `hits` and `weight` orderings on a full scan.
 * `(hidden, pdf)` failed outright on the homepage query, which filters
 * `pdf` without filtering `hidden`.
 *
 * Notably this also resolves the `ORDER BY hits` query without putting
 * `hits` — the one column written on every single page view — into any
 * index. See the E-05 report's "Hits Query Decision".
 *
 * Shares E-04's shape and guards: see that migration for why the
 * `main` connection needs an applicability check and why
 * `ALGORITHM=INPLACE, LOCK=NONE` is stated explicitly rather than left
 * to the server. Kept as a separate migration so E-04 stays a coherent
 * unit that production may receive on its own.
 */
return new class extends Migration
{
    private const TABLE = 'nuke_islamic_khotab';

    private const INDEX = 'idx_khotab_pdf';

    /** @var list<string> */
    private const COLUMNS = ['pdf'];

    /**
     * E-15: structure-aware guard. `alreadyCovered()` skips creation when
     * an index of this name exists *or* when these columns are a left-prefix
     * of an existing index — the case production presented, where an
     * equivalent index already existed under a different name.
     */
    public function up(): void
    {
        $connection = DB::connection('main');

        if (! LegacyIndexGuard::applicable($connection, self::TABLE)) {
            return;
        }

        if (LegacyIndexGuard::alreadyCovered($connection, self::TABLE, self::INDEX, self::COLUMNS)) {
            return;
        }

        $connection->statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (`pdf`), ALGORITHM=INPLACE, LOCK=NONE',
            self::TABLE,
            self::INDEX,
        ));
    }

    /**
     * Name-based on purpose: this must only drop the index this migration
     * created, never whatever else happens to cover the same columns.
     */
    public function down(): void
    {
        $connection = DB::connection('main');

        if (! LegacyIndexGuard::applicable($connection, self::TABLE)
            || ! LegacyIndexGuard::namedIndexExists($connection, self::TABLE, self::INDEX)) {
            return;
        }

        $connection->statement(sprintf(
            'ALTER TABLE `%s` DROP INDEX `%s`',
            self::TABLE,
            self::INDEX,
        ));
    }
};
