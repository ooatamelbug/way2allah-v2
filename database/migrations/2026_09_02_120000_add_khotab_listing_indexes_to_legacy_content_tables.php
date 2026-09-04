<?php

use App\Support\Database\LegacyIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enhancement Batch E-04 (F-01) — three read-path indexes on the legacy
 * content tables, each justified by measurement rather than inspection:
 *
 *   idx_mirror_khid            423,168-row scan → 2 rows;  865ms → 0.6ms
 *   idx_khotab_author_listing  139,147-row scan → 544 rows; 830ms → 7-39ms
 *   idx_khotab_channel_listing 139,147-row scan → 3 rows;   915ms → 0.9-8.5ms
 *
 * **This is the first migration that touches the `main` (legacy) connection.**
 * That database is shared with the still-running legacy PHP site and has
 * never been under Laravel migration control — it has no `migrations`
 * table of its own; migration *state* lives in the default connection.
 * Two consequences drove the shape of this file:
 *
 * 1. Every statement is guarded by an existence check, so running it
 *    against a database where the indexes already exist (applied by hand
 *    through phpMyAdmin, or re-run after the default connection was
 *    rebuilt independently) is a safe no-op rather than a duplicate-key
 *    error that aborts the whole migration run.
 * 2. `ALGORITHM=INPLACE, LOCK=NONE` is stated explicitly so the operation
 *    is online — concurrent reads AND writes from the legacy site continue
 *    during the build. Verified supported on the real server (MySQL
 *    5.7.44 / InnoDB). `ALGORITHM=INSTANT` is deliberately NOT used: it
 *    does not exist before MySQL 8.0.12 and would hard-fail here.
 *
 * Adds indexes only. No column, data, name, or engine is altered.
 */
return new class extends Migration
{
    /** @var list<array{table: string, name: string, columns: list<string>}> */
    private const INDEXES = [
        [
            'table' => 'nuke_islamic_mirror',
            'name' => 'idx_mirror_khid',
            'columns' => ['khid'],
        ],
        [
            'table' => 'nuke_islamic_khotab',
            'name' => 'idx_khotab_author_listing',
            'columns' => ['author', 'vedio', 'ser_id', 'group_id'],
        ],
        [
            'table' => 'nuke_islamic_khotab',
            'name' => 'idx_khotab_channel_listing',
            'columns' => ['channel_id', 'vedio', 'ser_id', 'group_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $index) {
            $connection = DB::connection('main');

            if (! LegacyIndexGuard::applicable($connection, $index['table'])) {
                continue;
            }

            if (LegacyIndexGuard::alreadyCovered($connection, $index['table'], $index['name'], $index['columns'])) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE',
                $index['table'],
                $index['name'],
                self::columnList($index['columns']),
            ));
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as $index) {
            $connection = DB::connection('main');

            if (! LegacyIndexGuard::applicable($connection, $index['table'])
                || ! LegacyIndexGuard::namedIndexExists($connection, $index['table'], $index['name'])) {
                continue;
            }

            $connection->statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                $index['table'],
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
