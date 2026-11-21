<?php

/*
 * B2 / F07-B2 — the byte-exact guard on REAL MySQL / MariaDB semantics (SQLite cannot prove them).
 *
 * Runs only when W2A_MYSQL_BYTE_TEST_* is configured; skipped otherwise. It creates ONLY session-private TEMPORARY tables
 * (`w2a_f07b2_tmp_*`, never a real table name, invisible to other sessions, dropped with the session) with the
 * production column definition (`link TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci`), and runs the command's own
 * read path (`plan()`, `storedBytes()`) and write primitive (`replaceExact()`) against them. `apply()` / `rollback()`
 * are NOT called here (they audit to the real `admin_audit_log`); their flow is covered on SQLite.
 */

use App\Domain\Admin\Actions\Anasheed\F07LinkRepair;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

const F07M_ITEMS = 'w2a_f07b2_tmp_items';
const F07M_MIRRORS = 'w2a_f07b2_tmp_mirrors';

function f07mConfig(): ?array
{
    $host = getenv('W2A_MYSQL_BYTE_TEST_HOST');
    if ($host === false || $host === '') {
        return null;
    }

    return ['driver' => 'mysql', 'host' => $host, 'port' => (string) getenv('W2A_MYSQL_BYTE_TEST_PORT'), 'database' => (string) getenv('W2A_MYSQL_BYTE_TEST_DATABASE'),
        'username' => (string) getenv('W2A_MYSQL_BYTE_TEST_USERNAME'), 'password' => (string) getenv('W2A_MYSQL_BYTE_TEST_PASSWORD'),
        'charset' => 'utf8', 'collation' => 'utf8_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => null];
}

function f07mDb(): ConnectionInterface
{
    return DB::connection('main');
}

function f07mRepair(): F07LinkRepair
{
    return new F07LinkRepair(app(AdminRecordAuthorizer::class), app(AdminAuditRecorder::class), ['item' => F07M_ITEMS, 'mirror' => F07M_MIRRORS]);
}

function f07mSeedManifest(): void
{
    foreach (F07LinkRepair::MANIFEST as [$type, $id, $parent, $oldHex]) {
        $type === 'item'
            ? f07mDb()->table(F07M_ITEMS)->insert(['id' => $id, 'group_id' => $parent, 'link' => hex2bin($oldHex)])
            : f07mDb()->table(F07M_MIRRORS)->insert(['id' => $id, 'khid' => $parent, 'link' => hex2bin($oldHex)]);
    }
}

beforeEach(function () {
    if (f07mConfig() === null) {
        $this->markTestSkipped('No MySQL/MariaDB configured for byte-semantics tests (W2A_MYSQL_BYTE_TEST_*).');
    }
    // The test's own 'main' connection is the MySQL server; every table it touches is a TEMPORARY table of THIS session.
    config(['database.connections.main' => f07mConfig()]);
    DB::purge('main');
    expect(f07mDb()->getDriverName())->toBe('mysql');
    foreach ([[F07M_ITEMS, 'group_id'], [F07M_MIRRORS, 'khid']] as [$table, $parent]) {
        expect($table)->toStartWith('w2a_f07b2_tmp_');
        f07mDb()->statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
        f07mDb()->statement("CREATE TEMPORARY TABLE {$table} (id INT NOT NULL PRIMARY KEY, {$parent} INT NOT NULL DEFAULT 0, link TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL) ENGINE=InnoDB");
    }
});

afterEach(function () {
    if (f07mConfig() !== null) {
        f07mDb()->statement('DROP TEMPORARY TABLE IF EXISTS '.F07M_ITEMS);
        f07mDb()->statement('DROP TEMPORARY TABLE IF EXISTS '.F07M_MIRRORS);
        DB::purge('main');
    }
});

it('matches the production link column definition when the real tables exist (read-only)', function () {
    $columns = f07mDb()->select("SELECT TABLE_NAME AS t, DATA_TYPE AS type, COLLATION_NAME AS c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('nuke_anasheed_anasheed', 'nuke_anasheed_mirror') AND COLUMN_NAME = 'link'");
    if ($columns === []) {
        $this->markTestSkipped('The real Variety tables are not in this database; the temporary tables use the documented definition.');
    }
    foreach ($columns as $column) {
        expect($column->type)->toBe('text')->and($column->c)->toBeIn(['utf8_unicode_ci', 'utf8mb3_unicode_ci']);   // MySQL 8 names utf8 "utf8mb3"
    }
});

it('proves the hazard: plain equality ignores trailing spaces and case on this collation; HEX() does not', function () {
    $old = (string) hex2bin(F07LinkRepair::MANIFEST[array_search(8703, array_column(F07LinkRepair::MANIFEST, 1), true)][3]);
    f07mDb()->table(F07M_ITEMS)->insert(['id' => 1, 'link' => $old]);

    $plain = fn (string $v) => f07mDb()->table(F07M_ITEMS)->where('link', $v)->count();
    $hex = fn (string $v) => f07mDb()->table(F07M_ITEMS)->whereRaw('HEX(link) = ?', [strtoupper(bin2hex($v))])->count();

    expect($plain($old.' '))->toBe(1)->and($hex($old.' '))->toBe(0)                        // PAD SPACE
        ->and($plain(strtoupper($old)))->toBe(1)->and($hex(strtoupper($old)))->toBe(0)      // case-insensitive
        ->and($plain($old))->toBe(1)->and($hex($old))->toBe(1);
});

it('reads every manifest value back byte-exactly (leading / trailing spaces, Arabic, doubled URL)', function () {
    f07mSeedManifest();

    foreach (F07LinkRepair::MANIFEST as [$type, $id, , $oldHex]) {
        expect(F07LinkRepair::storedBytes(f07mDb(), $type === 'item' ? F07M_ITEMS : F07M_MIRRORS, $id))->toBe(hex2bin($oldHex), "{$type} {$id}");
    }
});

it('replaceExact() writes only on an exact byte match', function (Closure $stored) {
    $old = ' https://soundcloud.com/way2allahcom/wfqm7e8fcriu ';
    f07mDb()->table(F07M_MIRRORS)->insert(['id' => 1, 'link' => $stored($old)]);

    $affected = F07LinkRepair::replaceExact(f07mDb(), F07M_MIRRORS, 1, $old, 'https://soundcloud.com/way2allahcom/wfqm7e8fcriu');

    expect($affected)->toBe(0)->and(F07LinkRepair::storedBytes(f07mDb(), F07M_MIRRORS, 1))->toBe($stored($old));
})->with([
    'trailing space dropped' => [fn (string $v) => rtrim($v)],
    'extra trailing space' => [fn (string $v) => $v.' '],
    'leading space dropped' => [fn (string $v) => ltrim($v)],
    'upper-case path' => [fn (string $v) => strtoupper($v)],
    'tab instead of space' => [fn (string $v) => "\t".ltrim($v)],
]);

it('replaceExact() replaces an exact match, storing the new value byte-exactly (Arabic prefix case)', function () {
    $old = 'رhttp://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4';
    $new = mb_substr($old, 1);
    f07mDb()->table(F07M_ITEMS)->insert(['id' => 1, 'link' => $old]);

    expect(F07LinkRepair::replaceExact(f07mDb(), F07M_ITEMS, 1, $old, $new))->toBe(1)
        ->and(F07LinkRepair::storedBytes(f07mDb(), F07M_ITEMS, 1))->toBe($new)
        ->and(F07LinkRepair::replaceExact(f07mDb(), F07M_ITEMS, 1, $old, $new))->toBe(0);   // second time: nothing matches
});

it('plan() on MySQL: all 46 pending; the write path applies them; then already_applied; reversing restores the exact bytes', function () {
    f07mSeedManifest();
    $repair = f07mRepair();

    $plan = $repair->plan();
    expect($plan['verdict'])->toBe('ready')->and($plan['counts'])->toBe(['pending' => 46]);

    // The same primitive apply() uses inside its transaction, row by row.
    foreach ($plan['rows'] as $row) {
        expect(F07LinkRepair::replaceExact(f07mDb(), $repair->table($row['type']), $row['id'], $row['expected'], $row['new']))->toBe(1);
    }
    $after = $repair->plan();
    expect($after['verdict'])->toBe('already_applied')->and($after['counts'])->toBe(['applied' => 46]);

    // The same primitive rollback() uses: back to the exact original bytes.
    foreach ($after['rows'] as $row) {
        expect(F07LinkRepair::replaceExact(f07mDb(), $repair->table($row['type']), $row['id'], $row['new'], $row['expected']))->toBe(1);
    }
    foreach (F07LinkRepair::MANIFEST as [$type, $id, , $oldHex]) {
        expect(F07LinkRepair::storedBytes(f07mDb(), $repair->table($type), $id))->toBe(hex2bin($oldHex));
    }
});

it('plan() on MySQL flags collation-equal but byte-different values as conflicts', function (string $type, int $id, Closure $mutate) {
    f07mSeedManifest();
    $repair = f07mRepair();
    $table = $repair->table($type);
    $old = F07LinkRepair::storedBytes(f07mDb(), $table, $id);
    f07mDb()->table($table)->where('id', $id)->update(['link' => $mutate($old)]);
    // Plain equality would still call it "the old value" on this collation:
    expect(f07mDb()->table($table)->where('id', $id)->where('link', $old)->count())->toBe(1);

    $plan = $repair->plan();

    expect($plan['verdict'])->toBe('blocked')->and($plan['problems'])->toBe(["{$type} {$id}: conflict"]);
})->with([
    'trailing space added' => ['item', 8703, fn (string $v) => $v.' '],
    'case changed' => ['item', 9870, fn (string $v) => str_replace('heekmaa702', 'HEEKMAA702', $v)],
    'trailing space removed' => ['mirror', 15979, fn (string $v) => rtrim($v)],
]);
