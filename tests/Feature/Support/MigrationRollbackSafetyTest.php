<?php

/*
 * Migration rollback safety (owner-approved, 2026-11-08):
 *  - the four 2026_09_02 legacy-index migrations' down() never drops an index (production carries
 *    hand-deployed indexes under the same names, decision-log #58); their up() is unchanged;
 *  - the two October `main` tables (admin_audit_log, download_rejections) are never dropped while they hold
 *    rows; an empty or missing table is dropped as before; up() unchanged.
 * Production `migrate:rollback` stays prohibited regardless.
 */

use App\Support\Database\NonEmptyTableGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const MRS_INDEX_MIGRATIONS = [
    '2026_09_02_120000_add_khotab_listing_indexes_to_legacy_content_tables.php' => 'c266ff2012688ea730ad51c74731e03bda33289e87ecd5659abf9574518e13c9',
    '2026_09_02_140000_add_pdf_index_to_legacy_khotab_table.php' => '0d507d5899cd655d5da479e4a388afe528859e7494bc435c17c83b23eaaa44e1',
    '2026_09_02_160000_add_khotab_news_and_day_listing_indexes.php' => '5e876b0a1016c6f15e7f60dd8257389e76755ecd07bcf671069e9aa2203f861a',
    '2026_09_02_180000_add_location_index_to_legacy_khotab_table.php' => 'dabb9f9437f7b7a8b6034764ed20ed3e275f38ffeea4a64e4c6bfe4a4de83126',
];

const MRS_TABLE_MIGRATIONS = [
    '2026_10_12_100000_create_admin_audit_log_table.php' => ['admin_audit_log', '10e302f72360018db886545ed7855916cf254889015bf7b42af30b8dbafd1655'],
    '2026_10_22_130000_create_download_rejections_table.php' => ['download_rejections', 'd65515c25847de80142a64aaecd486e5c6c77525bc688058730880f4886b505a'],
];

/** The up() method's exact source text (signature to closing brace). */
function mrsUpSource(string $file): string
{
    $s = (string) file_get_contents(database_path('migrations/'.$file));
    $start = strpos($s, '    public function up(): void');
    $end = strpos($s, "\n    }\n", $start) + strlen("\n    }\n");

    return substr($s, $start, $end - $start);
}

/** The down() method's executable code only (comments and docblocks stripped). */
function mrsDownCode(string $file): string
{
    $s = (string) file_get_contents(database_path('migrations/'.$file));
    $start = strpos($s, '    public function down(): void');
    $end = strpos($s, "\n    }\n", $start) + strlen("\n    }\n");
    $code = '';
    foreach (token_get_all('<?php '.substr($s, $start, $end - $start)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}

/** Run a migration's down() outside the unit-test short-circuit, then restore the environment. */
function mrsDownAsNonTest(object $migration): void
{
    $env = app()['env'];
    app()['env'] = 'local';
    try {
        $migration->down();
    } finally {
        app()['env'] = $env;
    }
}

// ================================================================ legacy index migrations

it('index migrations: up() is byte-for-byte unchanged', function (string $file, string $hash) {
    expect(hash('sha256', mrsUpSource($file)))->toBe($hash);
})->with(array_map(null, array_keys(MRS_INDEX_MIGRATIONS), array_values(MRS_INDEX_MIGRATIONS)));

it('index migrations: down() contains no DROP and issues no statement, even with same-named indexes present', function (string $file) {
    expect(mrsDownCode($file))->not->toMatch('/drop|statement\\(|Schema::|->table\\(/i');

    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
    ]);
    $main = DB::connection('main');
    foreach (['idx_khotab_author_listing' => 'author', 'idx_khotab_channel_listing' => 'channel_id', 'idx_khotab_news_listing' => 'vedio', 'idx_khotab_pdf' => 'pdf', 'idx_khotab_location' => 'location_id', 'idx_khotab_day_listing' => 'time'] as $name => $column) {
        $main->statement("CREATE INDEX {$name} ON nuke_islamic_khotab ({$column})");
    }
    $main->statement('CREATE INDEX idx_mirror_khid ON nuke_islamic_mirror (khid)');
    $countIndexes = fn () => $main->table('sqlite_master')->where('type', 'index')->where('name', 'like', 'idx_%')->count();
    $before = $countIndexes();

    $statements = [];
    DB::listen(function (QueryExecuted $q) use (&$statements) {
        $statements[] = $q->sql;
    });
    $migration = require database_path('migrations/'.$file);
    mrsDownAsNonTest($migration);
    $migration->down();

    expect($statements)->toBe([])->and($countIndexes())->toBe($before)->and($before)->toBe(7);
})->with(array_keys(MRS_INDEX_MIGRATIONS));

// ================================================================ October tables on main

it('October migrations: up() is byte-for-byte unchanged', function (string $file, array $meta) {
    expect(hash('sha256', mrsUpSource($file)))->toBe($meta[1]);
})->with(array_map(null, array_keys(MRS_TABLE_MIGRATIONS), array_values(MRS_TABLE_MIGRATIONS)));

it('October migrations: down() refuses to drop the table while it holds rows, and leaves it intact', function (string $file, array $meta) {
    [$table] = $meta;
    // Any row at all must block the drop — a minimal table under the migration's own table name is enough.
    InMemoryConnection::setup('main', [$table => fn ($t) => $t->increments('id')]);
    DB::connection('main')->table($table)->insert(['id' => 1]);

    $migration = require database_path('migrations/'.$file);

    expect(fn () => mrsDownAsNonTest($migration))->toThrow(RuntimeException::class, "Refusing to drop `{$table}`")
        ->and(Schema::connection('main')->hasTable($table))->toBeTrue()
        ->and(DB::connection('main')->table($table)->count())->toBe(1);
})->with(array_map(null, array_keys(MRS_TABLE_MIGRATIONS), array_values(MRS_TABLE_MIGRATIONS)));

it('October migrations: an empty table is dropped as before, a missing one is a no-op', function (string $file, array $meta) {
    [$table] = $meta;
    InMemoryConnection::setup('main', [$table => $table === 'admin_audit_log' ? MainSchema::adminAuditLog() : MainSchema::downloadRejections()]);
    $migration = require database_path('migrations/'.$file);

    mrsDownAsNonTest($migration);
    expect(Schema::connection('main')->hasTable($table))->toBeFalse();

    mrsDownAsNonTest($migration);   // already gone: no error
    expect(Schema::connection('main')->hasTable($table))->toBeFalse();
})->with(array_map(null, array_keys(MRS_TABLE_MIGRATIONS), array_values(MRS_TABLE_MIGRATIONS)));

it('October migrations: under unit tests down() still does nothing (unchanged short-circuit)', function (string $file, array $meta) {
    [$table] = $meta;
    InMemoryConnection::setup('main', [$table => $table === 'admin_audit_log' ? MainSchema::adminAuditLog() : MainSchema::downloadRejections()]);

    (require database_path('migrations/'.$file))->down();

    expect(Schema::connection('main')->hasTable($table))->toBeTrue();
})->with(array_map(null, array_keys(MRS_TABLE_MIGRATIONS), array_values(MRS_TABLE_MIGRATIONS)));

it('NonEmptyTableGuard only checks — it never deletes rows', function () {
    InMemoryConnection::setup('main', ['download_rejections' => MainSchema::downloadRejections()]);
    $statements = [];
    DB::listen(function (QueryExecuted $q) use (&$statements) {
        $statements[] = $q->sql;
    });

    NonEmptyTableGuard::assertSafeToDrop(DB::connection('main'), 'download_rejections');
    NonEmptyTableGuard::assertSafeToDrop(DB::connection('main'), 'missing_table');

    expect(array_filter($statements, fn ($s) => preg_match('/^\s*(delete|drop|truncate|update|insert)\b/i', $s)))->toBe([]);
});
