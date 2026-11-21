<?php

/*
 * Phase 2.2 — retention pruning (bounded, idempotent, off by default), the stale-batch guard (old batches are never
 * applied, so pruning can never cause double counting or partially recreate expired rows), and read-only growth
 * monitoring of download_ip_ua_hourly. SQLite stand-in; MariaDB-specific behaviour is in MariaDbIntegrationTest.
 */

use App\Support\Downloads\Telemetry\Aggregation\IpUaGrowthMonitor;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Aggregation\TelemetryPruner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\InMemoryConnection;

const RSG_DAY = 86400;
const RSG_NOW = 1_800_000_000;   // a fixed "now" for the runner and pruner (2027-01-15)

function rsgSettings(object $test, array $o = []): array
{
    return $o + ['spool' => 'on', 'root' => $test->root, 'ledger_shards' => 2, 'ledger_slots' => 10080, 'disk_reserve_bytes' => 0,
        'seal_grace_minutes' => 0, 'aggregate' => 'on', 'connection' => 'main', 'prune' => 'on'];
}

function rsgRun(object $test, int $now, array $o = []): array
{
    $s = rsgSettings($test, $o);

    return (new RollupRunner(SpoolConfig::fromArray($s), RollupConfig::fromArray($s), null, fn (): float => (float) $now))->run();
}

function rsgPrune(object $test, int $now, bool $dryRun = false, array $o = []): array
{
    $s = rsgSettings($test, $o);

    return (new TelemetryPruner(DB::connection('main'), SpoolConfig::fromArray($s), RollupConfig::fromArray($s)))->run($now, $dryRun);
}

/** Events at time $t, written into the minute file of $writerMinute (defaults to $t's minute). */
function rsgWrite(object $test, int $t, int $n, ?int $writerMinute = null, string $prefix = 'e'): void
{
    $minute = $writerMinute ?? intdiv($t, 60);
    $writer = new SpoolWriter(SpoolConfig::fromArray(rsgSettings($test)), fn (): float => $minute * 60 + 1.0);
    for ($i = 1; $i <= $n; $i++) {
        $writer->write(['t' => (float) $t, 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'id' => "{$prefix}{$i}", 'ip' => '203.0.113.'.($i % 4), 'uah' => 'u', 'rid' => $i]);
    }
}

function rsgCount(string $table): int
{
    return DB::connection('main')->table($table)->count();
}

/** One row in every table at a given age (days before RSG_NOW). */
function rsgSeedAt(int $daysAgo, string $suffix): void
{
    $t = RSG_NOW - $daysAgo * RSG_DAY;
    $hour = intdiv($t, 3600) * 3600;
    $db = DB::connection('main');
    $metrics = ['module' => 'm'.$suffix, 'event' => 'capacity_rejected', 'outcome' => '', 'variant' => '', 'source_host' => '', 'events' => 1, 'bytes_sum' => 0,
        'duration_ms_sum' => 0, 'duration_ms_max' => 0, 'ttfb_ms_sum' => 0, 'ttfb_ms_max' => 0, 'head_count' => 0, 'range_count' => 0, 'd_lt_10s' => 0,
        'd_lt_1m' => 0, 'd_lt_10m' => 0, 'd_lt_1h' => 0, 'd_ge_1h' => 0];
    $db->table(T::METRICS_5M)->insert(['bucket' => intdiv($t, 300) * 300] + $metrics);
    $db->table(T::METRICS_DAILY)->insert(['day' => intdiv($t, RSG_DAY) * RSG_DAY] + $metrics);
    $db->table(T::IP_UA_HOURLY)->insert(['hour' => $hour, 'ip' => '10.0.0.'.$suffix, 'ua_hash' => 'u', 'ua_family' => '', 'events' => 1, 'capacity_rejected' => 1,
        'transfers_started' => 0, 'transfers_finished' => 0, 'redirects' => 0, 'head_requests' => 0, 'range_requests' => 0, 'resources' => '[]',
        'resources_overflow' => false, 'first_t' => $t, 'last_t' => $t]);
    $db->table(T::SKETCHES)->insert(['day' => intdiv($t, RSG_DAY) * RSG_DAY, 'dimension' => 'ip'.$suffix, 'registers' => str_repeat("\0", 4096), 'updated_at' => $t]);
    $db->table(T::COVERAGE)->insert(['minute' => intdiv($t, 60), 'spool_enabled' => true, 'spool_writable' => true, 'breaker_open' => false, 'spool_full' => false, 'recorded_at' => $t]);
    $db->table(T::BATCHES)->insert(['batch_id' => 'b'.$suffix, 'sha256' => hash('sha256', $suffix), 'schema_version' => 1, 'batch_minute' => intdiv($t, 60),
        'lines' => 1, 'events' => 1, 'malformed' => 0, 'min_t' => $t, 'max_t' => $t, 'late_periods' => 0, 'applied_at' => $t]);
    $db->table(T::PERIODS)->insertOrIgnore(['granularity' => 'hour', 'period_start' => $hour, 'state' => 'final', 'events' => 1, 'batches' => 1, 'malformed' => 0,
        'late_batches' => 0, 'coverage_minutes' => 60, 'dropped_events' => 0, 'reasons' => '[]', 'finalized_at' => $t, 'corrected_at' => null, 'updated_at' => $t]);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-rsg-'.bin2hex(random_bytes(5));
    InMemoryConnection::setup('main', T::tables());
    DropLedger::resetProcessStateForTesting();
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    if (is_dir($this->root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }
});

// --- Retention ----------------------------------------------------------------------------------------------------

it('is off by default and refuses to delete anything unless enabled; a dry run reports only', function () {
    expect(RollupConfig::fromLaravel()->pruneEnabled)->toBeFalse()
        ->and(Artisan::call('downloads:telemetry-prune'))->toBe(1);

    rsgSeedAt(500, 'old');
    config(['download_telemetry.root' => $this->root]);
    Artisan::call('downloads:telemetry-prune', ['--dry-run' => true, '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['tables'][T::METRICS_DAILY])->toMatchArray(['eligible' => 1, 'deleted' => 0])
        ->and(rsgCount(T::METRICS_DAILY))->toBe(1);
});

it('deletes exactly the rows older than each approved retention, keeps the rest, and never prunes period status', function () {
    foreach ([['1', 1], ['13', 13], ['15', 15], ['36', 36], ['399', 399], ['401', 401]] as [$suffix, $days]) {
        rsgSeedAt($days, $suffix);
    }
    $report = rsgPrune($this, RSG_NOW);

    $left = fn (string $table, string $column) => DB::connection('main')->table($table)->count();
    expect($report['refused'])->toBe([])
        ->and($left(T::IP_UA_HOURLY, 'hour'))->toBe(2)        // 14 days: 1, 13 kept
        ->and($left(T::METRICS_5M, 'bucket'))->toBe(3)        // 35 days: 1, 13, 15 kept
        ->and($left(T::METRICS_DAILY, 'day'))->toBe(5)        // 400 days: all but 401
        ->and($left(T::SKETCHES, 'day'))->toBe(5)
        ->and($left(T::COVERAGE, 'minute'))->toBe(5)
        ->and($left(T::BATCHES, 'batch_minute'))->toBe(5)
        ->and($left(T::PERIODS, 'period_start'))->toBe(6);    // never pruned

    $again = rsgPrune($this, RSG_NOW);                       // idempotent
    expect(array_sum(array_column($again['tables'], 'deleted')))->toBe(0);
});

it('is bounded: at most prune_max_rows_per_table per run, in prune_batch_rows chunks; the next run continues', function () {
    foreach (range(1, 25) as $i) {
        rsgSeedAt(20 + $i, "x{$i}");   // 25 rows past the 14-day IP/UA retention
    }
    $first = rsgPrune($this, RSG_NOW, false, ['prune_batch_rows' => 4, 'prune_max_rows_per_table' => 10]);

    expect($first['tables'][T::IP_UA_HOURLY])->toMatchArray(['deleted' => 10, 'capped' => true])
        ->and(rsgCount(T::IP_UA_HOURLY))->toBe(15);
    rsgPrune($this, RSG_NOW, false, ['prune_batch_rows' => 4, 'prune_max_rows_per_table' => 10]);
    rsgPrune($this, RSG_NOW, false, ['prune_batch_rows' => 4, 'prune_max_rows_per_table' => 10]);
    expect(rsgCount(T::IP_UA_HOURLY))->toBe(0);
});

it('never prunes ledger rows newer than the oldest batch file still in the spool', function () {
    rsgSeedAt(450, 'ancient');
    rsgSeedAt(420, 'old');
    // A batch file from 430 days ago is still sitting in quarantine (e.g. an unsupported version).
    @mkdir($this->root.'/quarantine', 0775, true);
    file_put_contents($this->root.'/quarantine/'.SpoolConfig::minuteKey(intdiv(RSG_NOW - 430 * RSG_DAY, 60)).'-1-0000000a.ndjson', '{"v":9}'."\n");

    rsgPrune($this, RSG_NOW);

    expect(DB::connection('main')->table(T::BATCHES)->pluck('batch_id')->all())->toBe(['bold']);   // 420 days kept: newer than that file − 1 day
});

it('refuses to prune when the stale-batch boundary is not below the shortest retention', function () {
    rsgSeedAt(500, 'old');
    $report = rsgPrune($this, RSG_NOW, false, ['stale_batch_max_age_days' => 14]);

    expect($report['refused'])->not->toBe([])->and($report['tables'])->toBe([])->and(rsgCount(T::METRICS_DAILY))->toBe(1);
});

// --- Stale-batch guard --------------------------------------------------------------------------------------------

it('quarantines a batch whose events are older than the safe replay boundary instead of applying it', function () {
    rsgWrite($this, RSG_NOW - 20 * RSG_DAY, 5);                                     // events and file from 20 days ago

    $report = rsgRun($this, RSG_NOW);

    expect($report['stale'])->toBe(1)->and($report['applied'])->toBe(0)
        ->and(rsgCount(T::BATCHES))->toBe(0)->and(rsgCount(T::METRICS_5M))->toBe(0)
        ->and(glob($this->root.'/quarantine/*.stale.ndjson'))->toHaveCount(1);
});

it('a fresh batch file carrying one old event is stale as a whole (no partial application)', function () {
    $minute = intdiv(RSG_NOW, 60) - 5;
    rsgWrite($this, RSG_NOW - 300, 5, $minute, 'new');
    rsgWrite($this, RSG_NOW - 20 * RSG_DAY, 1, $minute, 'old');                     // a writer with a badly skewed event time

    $report = rsgRun($this, RSG_NOW);

    expect($report['stale'])->toBe(1)->and($report['applied'])->toBe(0)->and(rsgCount(T::METRICS_5M))->toBe(0);
});

it('a batch inside the boundary is applied normally', function () {
    rsgWrite($this, RSG_NOW - 2 * RSG_DAY, 5);

    expect(rsgRun($this, RSG_NOW))->toMatchArray(['applied' => 1, 'stale' => 0]);
});

it('after pruning, an expired batch re-submitted is never applied again (no double count, no recreated rows)', function () {
    $t = RSG_NOW - 10 * RSG_DAY;
    rsgWrite($this, $t, 8);
    $content = file_get_contents(glob($this->root.'/active/*.ndjson')[0]);            // sealing renames, never rewrites
    expect(rsgRun($this, $t + 600)['applied'])->toBe(1);                             // applied while fresh

    // 420 days later: everything about it has been pruned …
    $later = $t + 420 * RSG_DAY;
    rsgPrune($this, $later);
    expect(rsgCount(T::BATCHES))->toBe(0)->and(rsgCount(T::IP_UA_HOURLY))->toBe(0)->and(rsgCount(T::METRICS_5M))->toBe(0);

    // … and the same batch is submitted again: the ledger can no longer recognise it, the stale guard does.
    file_put_contents($this->root.'/sealed/'.SpoolConfig::minuteKey(intdiv($t, 60)).'-5-0000beef.ndjson', $content);
    $report = rsgRun($this, $later);

    expect($report['stale'])->toBe(1)->and($report['applied'])->toBe(0)
        ->and(rsgCount(T::METRICS_5M))->toBe(0)->and(rsgCount(T::IP_UA_HOURLY))->toBe(0)->and(rsgCount(T::METRICS_DAILY))->toBe(0);
});

it('a late batch for an hour whose IP/UA rows were already pruned does not partially recreate them', function () {
    $t = RSG_NOW - 15 * RSG_DAY;
    rsgSeedAt(15, 'pruned');
    rsgPrune($this, RSG_NOW);
    expect(rsgCount(T::IP_UA_HOURLY))->toBe(0);

    rsgWrite($this, $t, 3, intdiv(RSG_NOW, 60) - 2);                                 // arrives now, describes 15 days ago
    rsgRun($this, RSG_NOW);

    expect(rsgCount(T::IP_UA_HOURLY))->toBe(0)->and(glob($this->root.'/quarantine/*.stale.ndjson'))->toHaveCount(1);
});

it('the replay command refuses a batch older than the safe replay boundary', function () {
    $t = time() - 20 * RSG_DAY;
    rsgWrite($this, $t, 2);
    @mkdir($this->root.'/done', 0775, true);
    $sealed = glob($this->root.'/active/*.ndjson')[0];
    $id = SpoolConfig::minuteKey(intdiv($t, 60)).'-7-0000cafe';
    rename($sealed, $this->root.'/done/'.$id.'.ndjson');
    config(['download_telemetry' => rsgSettings($this) + config('download_telemetry')]);

    expect(Artisan::call('downloads:telemetry-replay', ['batch' => $id]))->toBe(1)
        ->and(Artisan::output())->toContain('safe replay boundary')
        ->and(file_exists($this->root.'/done/'.$id.'.ndjson'))->toBeTrue();
});

it('reports the database advisory lock as not supported on SQLite (test stand-in) and still runs', function () {
    rsgWrite($this, RSG_NOW - 120, 2);

    expect(rsgRun($this, RSG_NOW))->toMatchArray(['db_lock' => 'not_supported', 'applied' => 1]);
});

// --- Growth monitoring ------------------------------------------------------------------------------------------

function rsgIpUaRows(int $hour, int $n): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = ['hour' => $hour, 'ip' => "10.{$i}", 'ua_hash' => 'u', 'ua_family' => '', 'events' => 1, 'capacity_rejected' => 1, 'transfers_started' => 0,
            'transfers_finished' => 0, 'redirects' => 0, 'head_requests' => 0, 'range_requests' => 0, 'resources' => '[]', 'resources_overflow' => false,
            'first_t' => $hour, 'last_t' => $hour];
    }
    foreach (array_chunk($rows, 300) as $chunk) {
        DB::connection('main')->table(T::IP_UA_HOURLY)->insert($chunk);
    }
}

it('reports normal growth without warnings, and abnormal growth / thresholds with warnings — deleting nothing', function () {
    $current = intdiv(RSG_NOW, 3600) * 3600;
    foreach (range(2, 12) as $back) {
        rsgIpUaRows($current - $back * 3600, 20);                                    // steady baseline: 20 per hour
    }
    $config = RollupConfig::fromArray(['ip_ua_warn_rows_per_hour' => 100, 'ip_ua_critical_rows_per_hour' => 300, 'ip_ua_growth_ratio' => 5,
        'ip_ua_avg_row_bytes' => 1000, 'ip_ua_projected_warn_bytes' => 10_000_000]);
    $monitor = new IpUaGrowthMonitor(DB::connection('main'), $config);

    rsgIpUaRows($current - 3600, 25);
    expect($monitor->report(RSG_NOW, 12)['warnings'])->toBe([]);

    DB::connection('main')->table(T::IP_UA_HOURLY)->where('hour', $current - 3600)->delete();
    rsgIpUaRows($current - 3600, 150);                                               // a bot storm in the latest full hour
    $storm = $monitor->report(RSG_NOW, 12);

    expect($storm['warnings'])->toContain('rows_per_hour_warning', 'abnormal_growth')
        ->and($storm['latest_full_hour_rows'])->toBe(150)
        ->and($storm['definition'])->toContain('not people')
        ->and(rsgCount(T::IP_UA_HOURLY))->toBe(11 * 20 + 150);                       // nothing deleted
});

it('keeps its counts bounded during a storm and says "at least"; projects storage at the retention', function () {
    $current = intdiv(RSG_NOW, 3600) * 3600;
    rsgIpUaRows($current - 3600, 900);
    $config = RollupConfig::fromArray(['ip_ua_warn_rows_per_hour' => 100, 'ip_ua_critical_rows_per_hour' => 200, 'ip_ua_avg_row_bytes' => 1000,
        'retention_ip_ua_hourly_days' => 14, 'ip_ua_projected_warn_bytes' => 1_000_000]);
    $report = (new IpUaGrowthMonitor(DB::connection('main'), $config))->report(RSG_NOW, 3);

    $latest = collect($report['hours'])->firstWhere('hour', gmdate('Y-m-d H:00', $current - 3600));
    expect($latest)->toMatchArray(['rows' => 400, 'at_least' => true])                // capped at 2 × critical
        ->and($report['warnings'])->toContain('rows_per_hour_critical', 'projected_storage')
        ->and($report['projected_bytes_at_retention'])->toBe(intdiv(400, 4) * 24 * 14 * 1000);
});

it('the growth command exits 2 and logs DOWNLOAD_TELEMETRY_IP_UA_GROWTH on warnings', function () {
    $current = intdiv(time(), 3600) * 3600;
    rsgIpUaRows($current - 3600, 30);
    config(['download_telemetry.ip_ua_warn_rows_per_hour' => 10, 'download_telemetry.connection' => 'main']);
    Log::spy();

    expect(Artisan::call('downloads:telemetry-growth', ['--hours' => 3]))->toBe(2);
    Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c) => $c['event'] === 'DOWNLOAD_TELEMETRY_IP_UA_GROWTH')->once();
});
