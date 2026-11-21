<?php

use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * F02 — the migration is skipped under tests (it targets the real `main`
 * MySQL database); this keeps `MainSchema::adminAccountStates()` and the
 * migration in exact column agreement, as AdminAuditSchemaDriftTest does.
 */
it('keeps the admin_account_states fixture and its migration in exact column agreement', function () {
    $migration = file_get_contents(base_path('database/migrations/2026_11_17_100000_create_admin_account_states_table.php'));
    preg_match_all("/\\\$table->[a-zA-Z]+\('([a-z_]+)'/", $migration, $matches);
    $migrationColumns = collect($matches[1])->unique()->sort()->values()->all();

    InMemoryConnection::setup('main', ['admin_account_states' => MainSchema::adminAccountStates()]);
    $fixtureColumns = collect(Schema::connection('main')->getColumnListing('admin_account_states'))->sort()->values()->all();

    expect($fixtureColumns)->toBe($migrationColumns);
});

it('creates the table on main with an explicit utf8mb4 charset and never drops it while non-empty', function () {
    $migration = file_get_contents(base_path('database/migrations/2026_11_17_100000_create_admin_account_states_table.php'));

    expect($migration)->toContain("private const CONNECTION = 'main';")
        ->toContain("\$table->charset = 'utf8mb4';")
        ->toContain('NonEmptyTableGuard::assertSafeToDrop')
        ->toContain('runningUnitTests()');
});
