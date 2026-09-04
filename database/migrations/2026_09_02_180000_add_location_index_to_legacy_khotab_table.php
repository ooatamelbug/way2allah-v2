<?php

use App\Support\Database\LegacyIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enhancement Batch E-12 — the chat room's lesson sidebar.
 *
 * `chat_room/functions.php`'s `most_chat_lessons()` and its Laravel port
 * select `WHERE location_id = 10 AND hidden = 0 ORDER BY hits DESC LIMIT
 * 10`, uncached, twice per request. It read 275,496 rows to return 10.
 *
 *   238.6ms -> 6.3ms      /chat_room.htm: ~343ms -> ~39ms
 *
 * The `ORDER BY hits` made this look like E-06's sidebar query, which was
 * declined as ACCEPTABLE DEBT because indexing `hits` would add a B-tree
 * relocation to every content view. The difference is selectivity:
 * E-06's `vedio` matches 82.7% of the table, while `location_id = 10`
 * matches 1,875 rows of 292,592 — 0.64%. Once the optimizer reaches
 * those 1,875 rows, sorting them by `hits` is free, so `hits` stays out
 * of this index exactly as it stays out of every other one.
 *
 * Single column deliberately. `hidden` was measured and rejected: not one
 * of the 1,875 rows in the bucket is hidden, so `(location_id, hidden)`
 * added width for nothing and measured slightly slower.
 *
 * `location_id` is written by neither the application nor any live legacy
 * path, so this index carries no write amplification at all.
 *
 * Shares the guards E-04 established for the shared `main` connection;
 * see that migration for why they exist. Kept separate so each batch
 * rolls back independently and production may receive them in any order.
 */
return new class extends Migration
{
    private const TABLE = 'nuke_islamic_khotab';

    private const INDEX = 'idx_khotab_location';

    /** @var list<string> */
    private const COLUMNS = ['location_id'];

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
            'ALTER TABLE `%s` ADD INDEX `%s` (`location_id`), ALGORITHM=INPLACE, LOCK=NONE',
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
