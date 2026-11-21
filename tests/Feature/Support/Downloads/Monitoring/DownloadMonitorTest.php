<?php

/*
 * Phase 3B — the read-only download monitor: slot occupancy from the kernel lock table (never by taking a lock),
 * long-running holders, capacity rejections, stream outcomes, drops, breaker, spool usage, pending batches, rollup lag,
 * period quality, IP/UA growth; failure isolation between checks; no writes, no slot taken; bounded cost.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Monitoring\DownloadMonitor;
use App\Support\Downloads\Monitoring\SlotInspector;
use App\Support\Downloads\Telemetry\Aggregation\BatchAggregate;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function dmMonitor(object $test, array $telemetry = [], array $thresholds = [], ?string $locks = null): DownloadMonitor
{
    $settings = $telemetry + ['events' => 'on', 'spool' => 'on', 'aggregate' => 'on', 'root' => $test->root, 'disk_reserve_bytes' => 0, 'max_files' => 10, 'file_max_bytes' => 64 * 1024];
    config(['download_telemetry' => $settings]);

    return new DownloadMonitor(DB::connection('main'), SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings),
        $thresholds, $test->slotDir, DownloadCapacityGuard::limit(), ($settings['events'] ?? 'off') === 'on', $locks ?? '/proc/locks');
}

function dmHold(int $count): array
{
    $slots = [];
    for ($i = 0; $i < $count; $i++) {
        $slots[] = app(DownloadCapacityGuard::class)->tryAcquire();
    }

    return $slots;
}

beforeEach(function () {
    $this->scratch = sys_get_temp_dir().'/dm-'.bin2hex(random_bytes(5));
    mkdir($this->scratch);
    $this->root = $this->scratch.'/spool';
    $this->slotDir = $this->scratch.'/slots';
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 4, 'downloads.slot_directory' => $this->slotDir]);
    InMemoryConnection::setup('main', [DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections()] + T::tables());
    DropLedger::resetProcessStateForTesting();
    $this->held = [];
    $this->now = time();
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    DropLedger::resetProcessStateForTesting();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($this->scratch);
});

// --- Slots ----------------------------------------------------------------------------------------------------

it('finds held slots in the kernel lock table — with holder pid and age — without taking or disturbing any lock', function () {
    $this->held = dmHold(2);
    dmHold(0);
    $mtimes = array_map('filemtime', glob($this->slotDir.'/slot-*.lock'));

    $info = SlotInspector::inspect($this->slotDir, 4, (float) time());

    clearstatcache();
    expect($info['available'])->toBeTrue()
        ->and($info['occupied'])->toBe(2)
        ->and(array_column($info['holders'], 'pid'))->toBe([getmypid(), getmypid()])
        ->and($info['oldest_held_seconds'])->toBeLessThan(5)
        ->and(array_map('filemtime', glob($this->slotDir.'/slot-*.lock')))->toBe($mtimes)   // nothing touched
        ->and($this->held[0]->isHeld())->toBeTrue();

    // The two remaining slots are still free for downloads, and the inspector held none of them.
    $more = dmHold(2);
    expect($more[0]?->isHeld())->toBeTrue()->and($more[1]?->isHeld())->toBeTrue()
        ->and(app(DownloadCapacityGuard::class)->tryAcquire())->toBeNull();
    array_map(fn ($s) => $s->release(), $more);

    array_map(fn ($s) => $s->release(), $this->held);
    expect(SlotInspector::inspect($this->slotDir, 4, (float) time())['occupied'])->toBe(0);
});

it('reports occupancy as unknown — never guessed — when the lock table cannot be read', function () {
    $this->held = dmHold(1);

    $info = SlotInspector::inspect($this->slotDir, 4, (float) time(), $this->scratch.'/no-such-locks');
    $check = dmMonitor($this, locks: $this->scratch.'/no-such-locks')->collect($this->now)['checks']['slots'];

    expect($info['available'])->toBeFalse()->and($info['occupied'])->toBeNull()
        ->and($check['status'])->toBe(DownloadMonitor::UNKNOWN);
});

it('ignores lock-table waiters and unrelated files', function () {
    $this->held = dmHold(1);
    $path = glob($this->slotDir.'/slot-*.lock')[0];
    $real = array_values(array_filter(file('/proc/locks'), fn ($l) => str_contains($l, ' '.getmypid().' ') && str_contains($l, ':'.fileinode($path).' ')));
    expect($real)->not->toBe([]);
    $waiter = preg_replace('/^(\d+:)/', '$1 ->', $real[0]);
    file_put_contents($this->scratch.'/locks', "1: POSIX  ADVISORY  WRITE 99 00:00:1 0 EOF\n".$waiter);

    // Only a waiter and an unrelated lock: no slot holder, and the probe is absent from this (fake) table either, so
    // there is no evidence for zero — occupancy is unknown, never 0.
    expect(SlotInspector::inspect($this->slotDir, 4, (float) time(), $this->scratch.'/locks'))
        ->toMatchArray(['available' => false, 'occupied' => null, 'visibility' => 'probe_not_visible']);
});

it('flags long-running holders by the age stamped at acquisition: warn after 2 h, critical after 12 h', function () {
    $this->held = dmHold(1);
    $path = glob($this->slotDir.'/slot-*.lock')[0];

    touch($path, $this->now - 3 * 3600);
    $warn = dmMonitor($this)->collect($this->now)['checks']['slots'];
    touch($path, $this->now - 13 * 3600);
    $critical = dmMonitor($this)->collect($this->now)['checks']['slots'];

    expect($warn)->toMatchArray(['status' => 'warn', 'long_running_warn' => 1, 'long_running_critical' => 0])
        ->and($warn['holders'][0]['held_seconds'])->toBeGreaterThanOrEqual(3 * 3600)
        ->and($critical)->toMatchArray(['status' => 'critical', 'long_running_critical' => 1]);
});

it('the guard stamps the acquisition time on the slot file', function () {
    @mkdir($this->slotDir, 0775, true);
    foreach (range(1, 4) as $n) {
        touch(sprintf('%s/slot-%03d.lock', $this->slotDir, $n), $this->now - 86400);
    }

    $this->held = dmHold(1);
    $info = SlotInspector::inspect($this->slotDir, 4, (float) time());

    expect($info['holders'][0]['held_seconds'])->toBeLessThan(5);
});

it('occupancy: warn at 80 %, critical at 95 %', function () {
    $this->held = dmHold(3);
    expect(dmMonitor($this)->collect($this->now)['checks']['slots']['status'])->toBe('ok');   // 75 %

    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();
    expect(dmMonitor($this)->collect($this->now)['checks']['slots'])->toMatchArray(['status' => 'critical', 'occupied' => 4, 'occupancy_ratio' => 1.0]);
});

// --- Rejections -----------------------------------------------------------------------------------------------

function dmRejection(int $at): void
{
    DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->insert(['created_at' => $at, 'module' => 'khotab', 'resource_type' => 'khotab_item',
        'resource_id' => 1, 'variant' => 'primary', 'file_size_source' => 'unknown', 'client_ip' => '127.0.0.1', 'active_downloads' => 400,
        'reason' => 'capacity', 'pool' => 'global', 'requested_url' => '/k',
        'download_limit' => 400, 'retry_after_seconds' => 60]);
}

it('counts capacity rejections in the window only: warn from 1, critical from the threshold', function () {
    dmRejection($this->now - 7200);   // outside a 60-minute window
    expect(dmMonitor($this)->collect($this->now)['checks']['capacity_rejections'])->toMatchArray(['status' => 'ok', 'in_window' => 0]);

    dmRejection($this->now - 60);
    $one = dmMonitor($this)->collect($this->now)['checks']['capacity_rejections'];
    foreach (range(1, 4) as $i) {
        dmRejection($this->now - $i);
    }
    $many = dmMonitor($this, thresholds: ['rejections_critical' => 5])->collect($this->now)['checks']['capacity_rejections'];

    expect($one)->toMatchArray(['status' => 'warn', 'in_window' => 1])
        ->and($one['last_rejection_at'])->toBe(gmdate('c', $this->now - 60))
        ->and($many)->toMatchArray(['status' => 'critical', 'in_window' => 5]);
});

// --- Transfers (rolled-up metrics) ----------------------------------------------------------------------------

function dmMetric(int $bucket, string $event, string $outcome, int $events): void
{
    DB::connection('main')->table(T::METRICS_5M)->insert(['bucket' => $bucket, 'module' => 'khotab', 'event' => $event, 'outcome' => $outcome,
        'variant' => 'primary', 'source_host' => 'archive.org'] + array_merge(BatchAggregate::zeroMetrics(), ['events' => $events]));
}

it('stream failures and client disconnects from the 5-minute metrics, by ratio', function () {
    $bucket = intdiv($this->now, 300) * 300;
    dmMetric($bucket, 'transfer_finished', 'completed', 80);
    dmMetric($bucket, 'transfer_finished', 'client_aborted', 20);
    dmMetric($bucket - 86400, 'transfer_finished', 'stalled', 500);   // outside the window
    $ok = dmMonitor($this)->collect($this->now)['checks']['transfers'];

    dmMetric($bucket, 'transfer_finished', 'stalled', 30);
    dmMetric($bucket, 'source_rejected', 'html', 10);
    $bad = dmMonitor($this)->collect($this->now)['checks']['transfers'];

    expect($ok)->toMatchArray(['status' => 'ok', 'completed' => 80, 'client_disconnects' => 20, 'stream_failures' => 0])
        ->and($bad)->toMatchArray(['status' => 'critical', 'stream_failures' => 40])
        ->and($bad['failure_ratio'])->toBe(round(40 / 140, 4));
});

it('telemetry checks report "disabled" — not a problem — when telemetry is off', function () {
    $report = dmMonitor($this, ['events' => 'off', 'spool' => 'off', 'aggregate' => 'off'])->collect($this->now);

    foreach (['transfers', 'spool', 'telemetry_drops', 'circuit_breaker', 'pending_batches', 'rollup_lag', 'period_quality', 'ip_ua_growth'] as $name) {
        expect($report['checks'][$name]['status'])->toBe('disabled');
    }
    expect($report['overall'])->toBe('ok')->and(is_dir($this->root))->toBeFalse();
});

// --- Spool, drops, breaker, pending, lag ----------------------------------------------------------------------

it('a healthy spool: everything ok', function () {
    (new SpoolWriter(SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'disk_reserve_bytes' => 0])))
        ->write(['t' => microtime(true), 'ev' => 'download_accepted', 'mod' => 'khotab']);

    $report = dmMonitor($this)->collect(time());

    expect($report['checks']['spool'])->toMatchArray(['status' => 'ok', 'total_files' => 1])
        ->and($report['checks']['telemetry_drops'])->toMatchArray(['status' => 'ok', 'dropped_events' => 0])
        ->and($report['checks']['circuit_breaker']['status'])->toBe('ok')
        ->and($report['checks']['pending_batches'])->toMatchArray(['status' => 'ok', 'sealed_batches' => 0])
        ->and($report['checks']['rollup_lag'])->toMatchArray(['status' => 'ok', 'lag_seconds' => 0]);
});

it('an open breaker is critical; drops are reported with their reasons', function () {
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'disk_reserve_bytes' => 0]);
    @mkdir($this->root, 0775, true);
    touch($config->breakerPath(), time() + 300);
    (new SpoolWriter($config))->write(['t' => microtime(true), 'ev' => 'download_accepted', 'mod' => 'khotab']);

    $report = dmMonitor($this)->collect(time());

    expect($report['checks']['circuit_breaker']['status'])->toBe('critical')
        ->and($report['checks']['telemetry_drops'])->toMatchArray(['status' => 'warn', 'dropped_events' => 1, 'reasons' => ['breaker_open']])
        ->and($report['overall'])->toBe('critical');
});

it('sealed batches waiting too long: pending and rollup lag escalate by count and age', function () {
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root]);
    @mkdir($config->sealedDir(), 0775, true);
    foreach (range(1, 6) as $i) {
        file_put_contents($config->sealedDir()."/b{$i}.ndjson", '');
        touch($config->sealedDir()."/b{$i}.ndjson", $this->now - 1200);
    }

    $report = dmMonitor($this)->collect($this->now);

    expect($report['checks']['pending_batches'])->toMatchArray(['status' => 'warn', 'sealed_batches' => 6, 'oldest_sealed_age_seconds' => 1200])
        ->and($report['checks']['rollup_lag'])->toMatchArray(['status' => 'warn', 'lag_seconds' => 1200, 'last_applied_at' => null]);

    touch($config->sealedDir().'/b1.ndjson', $this->now - 7200);
    expect(dmMonitor($this)->collect($this->now)['checks']['rollup_lag']['status'])->toBe('critical');
});

it('spool usage near its bound escalates', function () {
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root]);
    @mkdir($config->quarantineDir(), 0775, true);
    foreach (range(1, 9) as $i) {
        file_put_contents($config->quarantineDir()."/q{$i}.ndjson", '');
    }

    expect(dmMonitor($this)->collect($this->now)['checks']['spool'])->toMatchArray(['status' => 'critical', 'used_ratio' => 0.9]);
});

// --- Periods and growth ---------------------------------------------------------------------------------------

it('incomplete days in the last week are a warning, listed by date', function () {
    $day = intdiv($this->now, 86400) * 86400;
    foreach ([[$day - 86400, 'final'], [$day - 2 * 86400, 'incomplete'], [$day - 30 * 86400, 'incomplete']] as [$start, $state]) {
        DB::connection('main')->table(T::PERIODS)->insert(['granularity' => 'day', 'period_start' => $start, 'state' => $state, 'events' => 0,
            'batches' => 0, 'malformed' => 0, 'late_batches' => 0, 'coverage_minutes' => 0, 'dropped_events' => 0, 'reasons' => '[]', 'updated_at' => $this->now]);
    }

    $check = dmMonitor($this)->collect($this->now)['checks']['period_quality'];

    expect($check)->toMatchArray(['status' => 'warn', 'last_7_days' => ['final' => 1, 'incomplete' => 1], 'incomplete_days' => [gmdate('Y-m-d', $day - 2 * 86400)]]);
});

it('IP/UA growth reuses the bounded growth report', function () {
    expect(dmMonitor($this)->collect($this->now)['checks']['ip_ua_growth'])->toMatchArray(['status' => 'ok', 'warnings' => [], 'latest_full_hour_rows' => 0]);
});

// --- Isolation, read-only, cost -------------------------------------------------------------------------------

it('a failing check is "unknown" and never stops the others', function () {
    Schema::connection('main')->drop(DownloadRejectionRecorder::TABLE);
    $this->held = dmHold(1);

    $report = dmMonitor($this)->collect($this->now);

    expect($report['checks']['capacity_rejections'])->toMatchArray(['status' => 'unknown'])
        ->and($report['checks']['capacity_rejections']['error'])->toContain('Exception')
        ->and($report['checks']['slots']['occupied'])->toBe(1)
        ->and($report['overall'])->toBe('unknown');
});

it('is read-only: no database writes, no spool created, no slot file changed', function () {
    $this->held = dmHold(2);
    $writes = new ArrayObject;
    DB::listen(function ($q) use ($writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|create|drop|alter|truncate)/i', $q->sql) === 1) {
            $writes[] = $q->sql;
        }
    });
    clearstatcache();
    $before = array_map(fn ($f) => [filemtime($f), filesize($f)], glob($this->slotDir.'/*'));

    dmMonitor($this)->collect($this->now);

    clearstatcache();
    expect($writes)->toHaveCount(0)
        ->and(is_dir($this->root))->toBeFalse()
        ->and(array_map(fn ($f) => [filemtime($f), filesize($f)], glob($this->slotDir.'/*')))->toBe($before);
});

it('bounded cost: 400 held slots and a full report in well under a second', function () {
    config(['downloads.max_concurrent' => 400]);
    $this->held = dmHold(400);
    expect(array_filter($this->held, fn ($s) => $s?->isHeld()))->toHaveCount(400);

    $started = microtime(true);
    $report = dmMonitor($this)->collect($this->now);
    $elapsed = microtime(true) - $started;

    expect($report['checks']['slots'])->toMatchArray(['status' => 'critical', 'occupied' => 400])
        ->and($report['checks']['slots']['holders'])->toHaveCount(20)
        ->and($elapsed)->toBeLessThan(1.0);
    fwrite(STDERR, sprintf("DM_COST slots_ms=%.2f total_ms=%.2f\n", $report['checks']['slots']['ms'], $report['duration_ms']));
});

it('the command prints JSON and exits 0 ok / 1 warn / 2 critical / 3 unknown', function () {
    config(['download_telemetry' => ['events' => 'off', 'spool' => 'off', 'aggregate' => 'off']]);
    expect(Artisan::call('downloads:monitor', ['--json' => true]))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['overall'])->toBe('ok');

    dmRejection(time());
    expect(Artisan::call('downloads:monitor'))->toBe(1);

    $this->held = dmHold(4);
    expect(Artisan::call('downloads:monitor'))->toBe(2);
});
