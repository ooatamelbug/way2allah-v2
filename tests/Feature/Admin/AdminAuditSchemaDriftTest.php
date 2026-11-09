<?php

use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * OLD Admin Migration Phase 0 — guards the one seam the migration's
 * `runningUnitTests()` skip opens.
 *
 * The migration creates `admin_audit_log` on `main`, which is a real MySQL
 * database during tests, so it is skipped there and the suite builds the
 * table from `MainSchema::adminAuditLog()` instead (see the migration's own
 * docblock for the full reasoning). The cost of that is a fixture that
 * could silently drift from the migration it stands in for. This test is
 * what makes drift impossible to miss: it parses the column names out of
 * both definitions and requires them to match exactly.
 */
it('keeps the audit-log fixture and its migration in exact column agreement', function () {
    $migration = file_get_contents(
        base_path('database/migrations/2026_10_12_100000_create_admin_audit_log_table.php')
    );

    // Every `$table->someType('column_name'...)` in the create() closure.
    preg_match_all("/\\\$table->[a-zA-Z]+\('([a-z_]+)'/", $migration, $matches);

    // `unique()` is load-bearing: the composite index() calls pass arrays
    // (which this pattern does not match at all), but
    // `index('created_at', ...)` passes a bare string and so appears twice.
    // `created_at` is a real column either way, so deduping is sufficient —
    // no exclusion list is needed.
    $migrationColumns = collect($matches[1])->unique()->sort()->values()->all();

    InMemoryConnection::setup('main', [
        'admin_audit_log' => MainSchema::adminAuditLog(),
    ]);

    $fixtureColumns = collect(
        Schema::connection('main')->getColumnListing('admin_audit_log')
    )->unique()->sort()->values()->all();

    expect($fixtureColumns)->toBe($migrationColumns);
});

it('declares every column the recorder writes', function () {
    InMemoryConnection::setup('main', [
        'admin_audit_log' => MainSchema::adminAuditLog(),
    ]);

    $columns = Schema::connection('main')->getColumnListing('admin_audit_log');

    expect($columns)->toContain(
        'id',
        'actor_admin_id',
        'actor_aid',
        'action',
        'module',
        'entity_type',
        'entity_id',
        'metadata',
        'ip',
        'created_at',
    );
});
