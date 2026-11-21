<?php

/*
 * Phase 2.1 — the telemetry tables and the exactly-once rollup on a REAL MariaDB 10.11.
 *
 * Runs only when W2A_MARIADB_TEST_HOST / _PORT / _DATABASE / _USERNAME / _PASSWORD point at a DISPOSABLE database
 * created for these tests (every test drops and recreates the seven telemetry tables in it). Skipped otherwise, so the
 * normal suite never needs MariaDB. Never point it at production.
 *
 * The connection uses the `mysql` driver against MariaDB — exactly how production's `main` connection is configured,
 * so Laravel's MySQL grammar (upserts with VALUES()) is what is exercised.
 */

use App\Support\Downloads\Telemetry\Aggregation\DatabaseRollupLock;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\HyperLogLog;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Telemetry\SpoolProcess;

const MDB_MINUTE = 29_379_840;
const MDB_NOW = MDB_MINUTE * 60 + 600;

function mdbConfig(): ?array
{
    $host = getenv('W2A_MARIADB_TEST_HOST');
    if ($host === false || $host === '') {
        return null;
    }

    return ['driver' => 'mysql', 'host' => $host, 'port' => (string) getenv('W2A_MARIADB_TEST_PORT'),
        'database' => (string) getenv('W2A_MARIADB_TEST_DATABASE'), 'username' => (string) getenv('W2A_MARIADB_TEST_USERNAME'),
        'password' => (string) getenv('W2A_MARIADB_TEST_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true, 'engine' => null];
}

function mdbSettings(string $root): array
{
    return ['spool' => 'on', 'root' => $root, 'ledger_shards' => 2, 'ledger_slots' => 10080, 'disk_reserve_bytes' => 0,
        'seal_grace_minutes' => 0, 'aggregate' => 'on', 'connection' => 'main'];
}

/** Write $n events for one minute (same IP/UA/resource dimensions every time, so batches collide on the same rows). */
function mdbWrite(string $root, int $minute, int $n, string $prefix = 'e', int $t = 0): void
{
    $writer = new SpoolWriter(SpoolConfig::fromArray(mdbSettings($root)), fn (): float => $minute * 60 + 1.0);
    for ($i = 1; $i <= $n; $i++) {
        $writer->write(['t' => (float) ($t ?: MDB_MINUTE * 60 + 1), 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'id' => "{$prefix}{$i}",
            'ip' => '203.0.113.'.($i % 3), 'uah' => 'ua', 'rid' => $i % 4, 'var' => 'mirror']);
    }
}

function mdbSeal(string $root): void
{
    (new SpoolSealer(SpoolConfig::fromArray(mdbSettings($root))))->seal(true);
}

function mdbRun(string $root, ?Closure $hook = null): array
{
    return (new RollupRunner(SpoolConfig::fromArray(mdbSettings($root)), RollupConfig::fromArray(mdbSettings($root)), $hook,
        fn (): float => (float) MDB_NOW))->run();
}

function mdbChild(object $test, string $id, string $root, array $spec = [], array $settings = []): SpoolProcess
{
    return SpoolProcess::start($spec + ['id' => $id, 'db_config' => mdbConfig(), 'config' => $settings + mdbSettings($root), 'now' => MDB_NOW],
        $test->barrier, null, 'rollup-child.php');
}

/** Totals that must equal the number of captured events, whatever happened. */
function mdbTotals(): array
{
    $db = DB::connection('main');
    $db->getPdo()->exec('COMMIT');

    return [
        'ledger' => $db->table(T::BATCHES)->count(),
        '5m' => (int) $db->table(T::METRICS_5M)->sum('events'),
        'daily' => (int) $db->table(T::METRICS_DAILY)->sum('events'),
        'ip_ua' => (int) $db->table(T::IP_UA_HOURLY)->sum('events'),
        'hour' => (int) $db->table(T::PERIODS)->where('granularity', 'hour')->sum('events'),
    ];
}

function mdbExpectTotals(int $batches, int $events): void
{
    expect(mdbTotals())->toBe(['ledger' => $batches, '5m' => $events, 'daily' => $events, 'ip_ua' => $events, 'hour' => $events]);
}

beforeEach(function () {
    if (mdbConfig() === null) {
        $this->markTestSkipped('No disposable MariaDB configured (W2A_MARIADB_TEST_*).');
    }
    config(['database.connections.main' => mdbConfig()]);
    DB::purge('main');
    expect(DB::connection('main')->selectOne('select version() as v')->v)->toContain('MariaDB');

    foreach (array_reverse(array_keys(T::tables())) as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    foreach (T::tables() as $table => $definition) {
        Schema::connection('main')->create($table, $definition);
    }

    $this->root = sys_get_temp_dir().'/w2a-mdb-'.bin2hex(random_bytes(5));
    $this->root2 = $this->root.'-second';
    $this->barrier = $this->root.'-barrier';
    mkdir($this->barrier, 0775, true);
    DropLedger::resetProcessStateForTesting();
});

afterEach(function () {
    if (! isset($this->root)) {
        return;
    }
    DB::purge('main');
    foreach ([$this->root, $this->root2, $this->barrier] as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
});

// --- Schema, constraints, BLOBs -----------------------------------------------------------------------------------

it('enforces the ledger\'s uniqueness on batch id AND content hash', function () {
    $row = ['batch_id' => 'b1', 'sha256' => str_repeat('a', 64), 'schema_version' => 1, 'batch_minute' => 1, 'lines' => 1, 'events' => 1,
        'malformed' => 0, 'min_t' => 1, 'max_t' => 1, 'late_periods' => 0, 'applied_at' => 1];
    DB::connection('main')->table(T::BATCHES)->insert($row);

    expect(fn () => DB::connection('main')->table(T::BATCHES)->insert(['sha256' => str_repeat('b', 64)] + $row))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => DB::connection('main')->table(T::BATCHES)->insert(['batch_id' => 'b2'] + $row))->toThrow(UniqueConstraintViolationException::class);
});

it('round-trips a 4,096-byte HyperLogLog BLOB byte for byte (NUL and high bytes included)', function () {
    // Every byte value 0x00–0xFF, repeated to 4,096 bytes — NULs and high bytes included.
    $bytes = str_repeat(implode('', array_map('chr', range(0, 255))), 16);
    $hll = new HyperLogLog($bytes);
    expect(strlen($bytes))->toBe(4096)->and(substr_count($bytes, "\0"))->toBe(16);

    DB::connection('main')->table(T::SKETCHES)->insert(['day' => 1, 'dimension' => 'ip', 'registers' => $bytes, 'updated_at' => 1]);
    DB::connection('main')->table(T::SKETCHES)->upsert([['day' => 1, 'dimension' => 'ip', 'registers' => $bytes, 'updated_at' => 2]], ['day', 'dimension'], ['registers', 'updated_at']);
    $back = DB::connection('main')->table(T::SKETCHES)->value('registers');

    expect(strlen($back))->toBe(4096)->and($back === $bytes)->toBeTrue()
        ->and((new HyperLogLog($back))->estimate())->toBe($hll->estimate());
});

// --- Exactly once, duplicates, rollback -------------------------------------------------------------------------------

it('applies exactly once with real upserts; a second run changes nothing', function () {
    mdbWrite($this->root, MDB_MINUTE, 60);

    $first = mdbRun($this->root);
    $second = mdbRun($this->root);

    expect($first['applied'])->toBe(1)->and($first['errors'])->toBe([])->and($second['applied'])->toBe(0);
    mdbExpectTotals(1, 60);
    expect(DB::connection('main')->table(T::IP_UA_HOURLY)->count())->toBe(3)                 // 3 IP addresses × 1 UA
        ->and(DB::connection('main')->table(T::SKETCHES)->count())->toBe(4);
});

it('merges a second batch into existing aggregate rows additively (upsert path)', function () {
    mdbWrite($this->root, MDB_MINUTE, 30, 'a');
    mdbRun($this->root);
    mdbWrite($this->root, MDB_MINUTE + 1, 30, 'b');   // same hour, same IP/UA rows, same 5-minute bucket by t
    mdbRun($this->root);

    mdbExpectTotals(2, 60);
    expect((int) DB::connection('main')->table(T::IP_UA_HOURLY)->where('ip', '203.0.113.1')->value('events'))->toBe(20);
});

it('duplicate replay on MariaDB: same batch, same content under a new id, and a same-id conflict', function () {
    mdbWrite($this->root, MDB_MINUTE, 10);
    mdbRun($this->root);
    $done = glob($this->root.'/done/*.ndjson')[0];
    $id = basename($done, '.ndjson');
    copy($done, $this->root.'/sealed/'.$id.'.ndjson');
    copy($done, $this->root.'/sealed/202511101600-1-deadbeef.ndjson');
    $dup = mdbRun($this->root);
    file_put_contents($this->root.'/sealed/'.$id.'.ndjson', file_get_contents($done)."\n");
    $conflict = mdbRun($this->root);

    expect($dup)->toMatchArray(['applied' => 0, 'already_applied' => 1, 'duplicate_content' => 1])
        ->and($conflict['conflicts'])->toBe(1);
    mdbExpectTotals(1, 10);
});

it('a failure inside the transaction rolls back every table on MariaDB', function () {
    mdbWrite($this->root, MDB_MINUTE, 20);
    $failed = mdbRun($this->root, function (string $p) {
        if ($p === 'before_commit') {
            throw new RuntimeException('simulated failure');
        }
    });

    expect($failed['errors'])->not->toBe([]);
    expect(mdbTotals())->toBe(['ledger' => 0, '5m' => 0, 'daily' => 0, 'ip_ua' => 0, 'hour' => 0])
        ->and(DB::connection('main')->table(T::SKETCHES)->count())->toBe(0);
    mdbRun($this->root);
    mdbExpectTotals(1, 20);
});

// --- Crash recovery (real processes, SIGKILL) ------------------------------------------------------------------------

it('crash recovery on MariaDB: killed at each checkpoint, then recovered — exactly once', function (string $point, int $ledgerAfterCrash) {
    mdbWrite($this->root, MDB_MINUTE, 40);
    mdbChild($this, 'crasher', $this->root, ['crash_at' => $point])->wait(60, false);

    DB::purge('main');
    expect(DB::connection('main')->table(T::BATCHES)->count())->toBe($ledgerAfterCrash);

    $report = mdbChild($this, 'recovery', $this->root)->wait();
    DB::purge('main');
    mdbExpectTotals(1, 40);
    expect($report['applied'] + $report['already_applied'])->toBe(1);
})->with([
    'before the transaction' => ['before_transaction', 0],
    'after the ledger insert' => ['after_ledger_insert', 0],
    'just before COMMIT' => ['before_commit', 0],
    'after COMMIT, before the move' => ['after_commit', 1],
]);

// --- Concurrency and the advisory lock (Phase 2.2) ------------------------------------------------------------------

it('holds the database advisory lock for the run and releases it afterwards', function () {
    mdbWrite($this->root, MDB_MINUTE, 5);
    $report = mdbRun($this->root);

    expect($report['db_lock'])->toBe('held')->and($report['applied'])->toBe(1)
        ->and(DB::connection('main')->selectOne('SELECT IS_USED_LOCK(?) AS h', [DatabaseRollupLock::NAME])->h)->toBeNull();
});

it('ledger alone (advisory lock disabled, file lock bypassed): the same batch raced by two workers is applied once', function () {
    mdbWrite($this->root, MDB_MINUTE, 40);
    mdbSeal($this->root);
    $off = ['rollup_db_lock' => false];
    $a = mdbChild($this, 'a', $this->root, ['exclusive' => false, 'pause' => ['before_transaction']], $off);
    $b = mdbChild($this, 'b', $this->root, ['exclusive' => false, 'pause' => ['before_transaction']], $off);
    $a->waitReached('before_transaction');
    $b->waitReached('before_transaction');
    $a->release('before_transaction');
    $b->release('before_transaction');
    $results = [$a->wait(), $b->wait()];

    DB::purge('main');
    mdbExpectTotals(1, 40);
    expect(array_sum(array_column($results, 'applied')))->toBe(1)->and(array_sum(array_column($results, 'already_applied')))->toBe(1);
});

it('two workers sharing ONE spool root: the file lock stops the second', function () {
    mdbWrite($this->root, MDB_MINUTE + 1, 25, 'x');
    mdbWrite($this->root, MDB_MINUTE + 2, 25, 'y');
    mdbSeal($this->root);
    $x = mdbChild($this, 'x', $this->root, ['pause' => ['after_ledger_insert']]);
    $x->waitReached('after_ledger_insert');
    $y = mdbChild($this, 'y', $this->root)->wait();
    $x->release('after_ledger_insert');
    $x->wait();
    mdbRun($this->root);

    DB::purge('main');
    expect($y['busy'])->toBeTrue()->and($y['db_lock'])->toBeNull();               // stopped by rollup.lock, before the database lock
    mdbExpectTotals(2, 50);
});

/**
 * Two DIFFERENT batches hitting the SAME aggregate rows, from two workers with SEPARATE spool roots (rollup.lock does
 * not serialize them). x is paused inside its transaction holding the advisory lock; y starts meanwhile.
 */
function mdbSeparateRoots(object $test, ?string $isolation, bool $preExisting): array
{
    if ($preExisting) {
        mdbWrite($test->root, MDB_MINUTE, 10, 'seed');
        mdbRun($test->root);
    }
    mdbWrite($test->root, MDB_MINUTE + 1, 25, 'x');
    mdbSeal($test->root);
    mdbWrite($test->root2, MDB_MINUTE + 2, 25, 'y');
    mdbSeal($test->root2);

    $iso = $isolation !== null ? ['isolation' => $isolation] : [];
    $x = mdbChild($test, 'x', $test->root, ['pause' => ['after_ledger_insert']] + $iso);
    $x->waitReached('after_ledger_insert');
    $y = mdbChild($test, 'y', $test->root2, $iso)->wait();                          // concurrent attempt
    $x->release('after_ledger_insert');
    $xr = $x->wait();
    $y2 = mdbChild($test, 'y2', $test->root2, $iso)->wait();                        // the next scheduled run

    return [$xr, $y, $y2];
}

it('separate spool roots, one database, READ COMMITTED: the advisory lock serializes them — the reproduced lost update no longer occurs', function (bool $preExisting) {
    [$x, $y, $y2] = mdbSeparateRoots($this, 'READ COMMITTED', $preExisting);

    DB::purge('main');
    expect($y)->toMatchArray(['busy' => true, 'db_lock' => 'busy', 'applied' => 0])
        ->and($x['applied'])->toBe(1)->and($y2['applied'])->toBe(1);
    mdbExpectTotals($preExisting ? 3 : 2, $preExisting ? 60 : 50);
})->with(['new rows' => [false], 'existing rows' => [true]]);

it('separate spool roots, one database, REPEATABLE READ: serialized and exact', function (bool $preExisting) {
    [$x, $y, $y2] = mdbSeparateRoots($this, null, $preExisting);

    DB::purge('main');
    expect($y['db_lock'])->toBe('busy')->and($x['errors'])->toBe([])->and($y2['errors'])->toBe([]);
    mdbExpectTotals($preExisting ? 3 : 2, $preExisting ? 60 : 50);
})->with(['new rows' => [false], 'existing rows' => [true]]);

it('RESIDUAL RISK (unsupported configuration): with the advisory lock DISABLED, separate roots under READ COMMITTED can still lose an update', function () {
    mdbWrite($this->root, MDB_MINUTE + 1, 25, 'x');
    mdbSeal($this->root);
    mdbWrite($this->root2, MDB_MINUTE + 2, 25, 'y');
    mdbSeal($this->root2);
    $spec = ['pause' => ['after_ledger_insert'], 'isolation' => 'READ COMMITTED'];
    $off = ['rollup_db_lock' => false];
    $x = mdbChild($this, 'x', $this->root, $spec, $off);
    $y = mdbChild($this, 'y', $this->root2, $spec, $off);
    $x->waitReached('after_ledger_insert');
    $y->waitReached('after_ledger_insert');
    $x->release('after_ledger_insert');
    $y->release('after_ledger_insert');
    $x->wait();
    $y->wait();

    DB::purge('main');
    $totals = mdbTotals();
    fwrite(STDERR, "\n[advisory lock DISABLED, RC, separate roots] ".json_encode($totals));
    expect($totals['ledger'])->toBe(2)->and($totals['5m'])->toBeLessThanOrEqual(50);
});

it('a worker SIGKILLed while holding the advisory lock: the server releases it, the next worker proceeds, exactly once', function () {
    mdbWrite($this->root, MDB_MINUTE, 30);
    $victim = mdbChild($this, 'victim', $this->root, ['pause' => ['advisory_lock_acquired']]);
    $victim->waitReached('advisory_lock_acquired');
    $victim->kill();

    // Give the server a moment to notice the closed session (a SIGKILL closes the socket immediately).
    $until = microtime(true) + 10;
    do {
        DB::purge('main');
        $held = DB::connection('main')->selectOne('SELECT IS_USED_LOCK(?) AS h', [DatabaseRollupLock::NAME])->h;
    } while ($held !== null && microtime(true) < $until && usleep(100_000) === null);

    $next = mdbChild($this, 'next', $this->root)->wait();
    DB::purge('main');
    expect($held)->toBeNull()->and($next)->toMatchArray(['db_lock' => 'held', 'applied' => 1]);
    mdbExpectTotals(1, 30);
});

it('database connection loss: the advisory-lock session is killed mid-run — the batch rolls back, nothing more is written, the next run applies it once', function () {
    mdbWrite($this->root, MDB_MINUTE, 30);
    $worker = mdbChild($this, 'w', $this->root, ['pause' => ['before_transaction']]);
    $worker->waitReached('before_transaction');                                      // lock held, batch parsed, not yet in a transaction
    $session = (int) file_get_contents($this->barrier.'/w.session');
    DB::connection('main')->statement("KILL CONNECTION {$session}");                // the lock's session disappears
    $worker->release('before_transaction');
    $report = $worker->wait();

    DB::purge('main');
    expect($report['applied'])->toBe(0)
        ->and(implode(' ', $report['errors']))->toContain('ownership lost')
        ->and($report['finalizer'])->toBeNull()                                       // nothing further written
        ->and(DB::connection('main')->table(T::BATCHES)->count())->toBe(0)
        ->and(glob($this->root.'/sealed/*.ndjson'))->toHaveCount(1);

    $next = mdbChild($this, 'next', $this->root)->wait();
    DB::purge('main');
    expect($next['applied'])->toBe(1);
    mdbExpectTotals(1, 30);
});
