<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared setup for the in-memory-SQLite-override pattern used throughout
 * the test suite since Wave 0 to verify code against named connections
 * (main/vbulletin/flashchat) without real infrastructure — extracted
 * after the pattern had been independently redefined in 8 test files
 * (proposed and approved 2026-08-04, testing-infrastructure-only, not
 * routed through the ADR process).
 *
 * Table definitions are supplied by callers, ideally sourced from
 * Tests\Support\Fixtures\* rather than redefined inline — a shared
 * *mechanism* alone doesn't prevent schema drift between test files if
 * each file still writes its own column list; using the same named
 * fixture closure everywhere does. This is what closed the
 * isGuardableColumn cache-poisoning hazard found during Wave 0/1 (two
 * files defining `nuke_authors` with different columns, whichever ran
 * first silently broke mass-assignment for the other).
 */
class InMemoryConnection
{
    /**
     * @param  array<string, \Closure>  $tables  table name => Blueprint closure
     */
    public static function setup(string $connection, array $tables): void
    {
        config(["database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge($connection);

        foreach ($tables as $tableName => $definition) {
            Schema::connection($connection)->create($tableName, $definition);
        }
    }
}
