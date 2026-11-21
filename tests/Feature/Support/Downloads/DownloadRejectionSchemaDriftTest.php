<?php

use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * The rejection-telemetry migration is skipped under tests (it creates a table
 * on `main`, which is real MySQL while the suite runs — see the migration's
 * docblock), so the suite builds the table from
 * `MainSchema::downloadRejections()`. This keeps that fixture and the
 * migration in exact column agreement, as AdminAuditSchemaDriftTest does for
 * the audit log.
 */
it('keeps the download_rejections fixture and its migration in exact column agreement', function () {
    // The creating migration plus every later additive one (Phase 3D: resource_title).
    $migration = file_get_contents(base_path('database/migrations/2026_10_22_130000_create_download_rejections_table.php'))
        .file_get_contents(base_path('database/migrations/2026_11_11_300000_add_resource_title_to_download_rejections.php'));

    preg_match_all("/\\\$table->[a-zA-Z]+\('([a-z_]+)'/", $migration, $matches);
    $migrationColumns = collect($matches[1])->unique()->sort()->values()->all();

    InMemoryConnection::setup('main', [
        'download_rejections' => MainSchema::downloadRejections(),
    ]);

    $fixtureColumns = collect(Schema::connection('main')->getColumnListing('download_rejections'))->sort()->values()->all();

    expect($fixtureColumns)->toBe($migrationColumns);
});

it('leaves no Khotab-specific rejection migration that could ever run in production', function () {
    expect(glob(base_path('database/migrations/*khotab_download_rejections*')))->toBe([]);
});
