<?php

use App\Support\Database\NonEmptyTableGuard;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Download telemetry, Phase 2 (2026-11-11): the aggregation tables. Additive only — seven NEW Laravel-owned tables,
 * defined in TelemetrySchema (shared with the tests, so the two cannot drift); no existing or legacy table is
 * touched.
 *
 * Same conventions as `create_download_rejections_table`: the `main` connection (the production MySQL/MariaDB
 * database), unguarded create() (a conflicting existing table must fail loudly), no foreign keys, and skipped under
 * unit tests because `main` is a real database while the suite runs — the tests build the same tables from
 * TelemetrySchema in an SQLite connection.
 */
return new class extends Migration
{
    private const CONNECTION = 'main';

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        foreach (TelemetrySchema::tables() as $table => $definition) {
            Schema::connection(self::CONNECTION)->create($table, $definition);
        }
    }

    /**
     * Owner rule (2026-11-08): never drops a table while it holds rows — NonEmptyTableGuard raises first, so the
     * rollback fails loudly and the migration stays recorded. Empty or missing tables are dropped.
     */
    public function down(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $tables = array_reverse(array_keys(TelemetrySchema::tables()));
        foreach ($tables as $table) {
            NonEmptyTableGuard::assertSafeToDrop(DB::connection(self::CONNECTION), $table);
        }
        foreach ($tables as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
    }
};
