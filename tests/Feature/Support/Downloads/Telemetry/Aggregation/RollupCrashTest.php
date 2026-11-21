<?php

/*
 * Phase 2 — crash recovery and concurrency across REAL processes sharing one SQLite FILE database. A child is
 * SIGKILLed at a chosen checkpoint (a real crash: no rollback code, no shutdown function), then the rollup runs again.
 * In every case each batch must end up applied exactly once.
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Telemetry\SpoolProcess;

// Relative to the real clock: the replay command applies the stale-batch guard against real time.
define('RCR_MINUTE', intdiv(time(), 60) - 10);
define('RCR_NOW', RCR_MINUTE * 60 + 180);

function rcrSettings(object $test): array
{
    return ['spool' => 'on', 'root' => $test->root, 'ledger_shards' => 2, 'ledger_slots' => 10080, 'disk_reserve_bytes' => 0,
        'seal_grace_minutes' => 0, 'aggregate' => 'on', 'connection' => 'main'];
}

function rcrChild(object $test, string $id, array $spec = []): SpoolProcess
{
    return SpoolProcess::start($spec + ['id' => $id, 'db' => $test->db, 'config' => rcrSettings($test), 'now' => RCR_NOW],
        $test->barrier, null, 'rollup-child.php');
}

function rcrRunHere(object $test): array
{
    return (new RollupRunner(SpoolConfig::fromArray(rcrSettings($test)), RollupConfig::fromArray(rcrSettings($test)), null,
        fn (): float => (float) RCR_NOW))->run();
}

function rcrState(): array
{
    $db = DB::connection('main');

    return [
        'ledger' => $db->table(T::BATCHES)->count(),
        'events_5m' => (int) $db->table(T::METRICS_5M)->sum('events'),
        'events_daily' => (int) $db->table(T::METRICS_DAILY)->sum('events'),
        'ip_ua_events' => (int) $db->table(T::IP_UA_HOURLY)->sum('events'),
        'period_events' => (int) $db->table(T::PERIODS)->where('granularity', 'hour')->sum('events'),
        'period_batches' => (int) $db->table(T::PERIODS)->where('granularity', 'hour')->sum('batches'),
    ];
}

function rcrExpectAppliedOnce(int $events): void
{
    expect(rcrState())->toBe(['ledger' => 1, 'events_5m' => $events, 'events_daily' => $events, 'ip_ua_events' => $events,
        'period_events' => $events, 'period_batches' => 1]);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-rcrash-'.bin2hex(random_bytes(5));
    $this->barrier = $this->root.'-barrier';
    mkdir($this->barrier, 0775, true);
    $this->db = $this->barrier.'/telemetry.sqlite';
    touch($this->db);
    config(['database.connections.main' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => false, 'busy_timeout' => 15000]]);
    DB::purge('main');
    foreach (T::tables() as $table => $definition) {
        Schema::connection('main')->create($table, $definition);
    }
    DropLedger::resetProcessStateForTesting();

    $writer = new SpoolWriter(SpoolConfig::fromArray(rcrSettings($this)), fn (): float => RCR_MINUTE * 60 + 1.0);
    foreach (range(1, 40) as $i) {
        $writer->write(['t' => RCR_MINUTE * 60 + 1.0, 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'id' => "e{$i}", 'ip' => '203.0.113.'.($i % 7), 'rid' => $i]);
    }
});

afterEach(function () {
    DB::purge('main');
    foreach ([$this->root, $this->barrier] as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
});

it('crash at a checkpoint, then recovery: the batch is applied exactly once', function (string $point, int $ledgerAfterCrash) {
    rcrChild($this, 'crasher', ['crash_at' => $point])->wait(60, false);           // SIGKILLed mid-run

    DB::purge('main');                                                              // a fresh connection, like the next cron run
    expect(DB::connection('main')->table(T::BATCHES)->count())->toBe($ledgerAfterCrash)
        ->and(glob($this->root.'/sealed/*.ndjson'))->toHaveCount(1);              // never moved before the commit … or after it

    $report = rcrChild($this, 'recovery')->wait();

    DB::purge('main');
    rcrExpectAppliedOnce(40);
    expect(glob($this->root.'/done/*.ndjson'))->toHaveCount(1)->and(glob($this->root.'/sealed/*.ndjson'))->toBe([])
        ->and($report['applied'] + $report['already_applied'])->toBe(1);
})->with([
    'before the transaction' => ['before_transaction', 0],
    'inside the transaction, after the ledger insert' => ['after_ledger_insert', 0],
    'inside the transaction, just before COMMIT' => ['before_commit', 0],
    'after COMMIT, before the move to done/' => ['after_commit', 1],
]);

it('two workers that both bypass rollup.lock and apply the same batch at the same moment: the ledger lets only one through', function () {
    (new SpoolSealer(SpoolConfig::fromArray(rcrSettings($this))))->seal(true);    // one sealed batch, visible to both
    $a = rcrChild($this, 'a', ['exclusive' => false, 'pause' => ['before_transaction']]);
    $b = rcrChild($this, 'b', ['exclusive' => false, 'pause' => ['before_transaction']]);
    $a->waitReached('before_transaction');
    $b->waitReached('before_transaction');                                          // both have parsed the same batch
    $a->release('before_transaction');
    $b->release('before_transaction');
    $results = [$a->wait(), $b->wait()];

    DB::purge('main');
    rcrExpectAppliedOnce(40);
    expect(array_sum(array_column($results, 'applied')))->toBe(1)
        ->and(array_sum(array_column($results, 'already_applied')))->toBe(1);
});

it('a second concurrent worker finds rollup.lock held and exits without touching anything', function () {
    $first = rcrChild($this, 'first', ['pause' => ['before_transaction']]);
    $first->waitReached('before_transaction');                                      // holds rollup.lock
    $second = rcrChild($this, 'second')->wait();
    $first->release('before_transaction');
    $firstResult = $first->wait();

    DB::purge('main');
    expect($second)->toMatchArray(['busy' => true, 'ran' => false])->and($firstResult['applied'])->toBe(1);
    rcrExpectAppliedOnce(40);
});

it('crash while applying a replayed batch: still exactly once', function () {
    rcrRunHere($this);
    $done = glob($this->root.'/done/*.ndjson')[0];
    $id = basename($done, '.ndjson');

    // Operator scenario: the database was restored from before this batch (ledger and aggregates both without it).
    DB::connection('main')->table(T::BATCHES)->delete();
    foreach ([T::METRICS_5M, T::METRICS_DAILY, T::IP_UA_HOURLY, T::SKETCHES, T::PERIODS] as $table) {
        DB::connection('main')->table($table)->delete();
    }
    config(['download_telemetry' => rcrSettings($this) + config('download_telemetry')]);
    expect(Artisan::call('downloads:telemetry-replay', ['batch' => $id]))->toBe(0)
        ->and(Artisan::call('downloads:telemetry-replay', ['batch' => $id]))->toBe(1);   // nothing left in done/ to replay twice

    rcrChild($this, 'crasher', ['crash_at' => 'before_commit'])->wait(60, false);
    rcrChild($this, 'recovery')->wait();

    DB::purge('main');
    rcrExpectAppliedOnce(40);
});
