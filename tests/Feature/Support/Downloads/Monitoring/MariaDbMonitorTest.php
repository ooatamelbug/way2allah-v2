<?php

/*
 * Phase 3B.1 — the download monitor against a DISPOSABLE MariaDB 10.11 (W2A_MARIADB_TEST_*; skipped otherwise):
 * every query it sends is EXPLAINed (no full table scan of a monitored table), the counting query is capped, it writes
 * nothing (statement listener + CHECKSUM TABLE before/after), growth warnings and period quality are reported, and the
 * command's exit codes hold. Never run against production.
 */

use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Monitoring\DownloadMonitor;
use App\Support\Downloads\Telemetry\Aggregation\BatchAggregate;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;

function mmConfig(): ?array
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

function mmSettings(object $test, array $extra = []): array
{
    return $extra + ['events' => 'on', 'spool' => 'on', 'aggregate' => 'on', 'connection' => 'main', 'root' => $test->root, 'disk_reserve_bytes' => 0];
}

function mmMonitor(object $test, array $extra = []): DownloadMonitor
{
    $s = mmSettings($test, $extra);
    config(['download_telemetry' => $s]);

    return new DownloadMonitor(DB::connection('main'), SpoolConfig::fromArray($s), RollupConfig::fromArray($s), [], $test->slotDir, 4, true);
}

/** Realistic volume: rejections over 3 days, 5-minute metrics over 3 days, IP/UA rows over 30 hours, 40 day periods. */
function mmSeed(int $now): void
{
    $db = DB::connection('main');
    $rows = [];
    for ($i = 0; $i < 3000; $i++) {
        $rows[] = ['created_at' => $now - $i * 86, 'reason' => 'capacity', 'pool' => 'global', 'module' => 'khotab', 'resource_type' => 'khotab_item',
            'resource_id' => $i, 'variant' => 'primary', 'requested_url' => '/k', 'file_size_source' => 'unknown', 'client_ip' => '192.0.2.'.($i % 250),
            'active_downloads' => 400, 'download_limit' => 400, 'retry_after_seconds' => 60];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        $db->table(DownloadRejectionRecorder::TABLE)->insert($chunk);
    }
    $rows = [];
    for ($b = intdiv($now, 300) * 300, $n = 0; $n < 864; $b -= 300, $n++) {
        foreach (['completed', 'stalled', 'client_aborted'] as $outcome) {
            $rows[] = ['bucket' => $b, 'module' => 'khotab', 'event' => 'transfer_finished', 'outcome' => $outcome, 'variant' => 'primary',
                'source_host' => 'archive.org'] + array_merge(BatchAggregate::zeroMetrics(), ['events' => $outcome === 'completed' ? 50 : 1]);
        }
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        $db->table(T::METRICS_5M)->insert($chunk);
    }
    $rows = [];
    for ($h = intdiv($now, 3600) * 3600 - 30 * 3600; $h <= intdiv($now, 3600) * 3600; $h += 3600) {
        for ($i = 0; $i < 40; $i++) {
            $rows[] = ['hour' => $h, 'ip' => '198.51.100.'.$i, 'ua_hash' => str_repeat('a', 32), 'ua_family' => 'browser', 'events' => 1,
                'capacity_rejected' => 0, 'transfers_started' => 1, 'transfers_finished' => 1, 'redirects' => 0, 'head_requests' => 0,
                'range_requests' => 0, 'resources' => '[]', 'resources_overflow' => 0, 'first_t' => $h, 'last_t' => $h];
        }
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        $db->table(T::IP_UA_HOURLY)->insert($chunk);
    }
    $day = intdiv($now, 86400) * 86400;
    for ($d = 0; $d < 40; $d++) {
        foreach (['day' => $day - $d * 86400, 'hour' => $now - $d * 3600] as $granularity => $start) {
            $db->table(T::PERIODS)->insert(['granularity' => $granularity, 'period_start' => $start, 'state' => $d === 3 ? 'incomplete' : 'final',
                'events' => 1, 'batches' => 1, 'malformed' => 0, 'late_batches' => 0, 'coverage_minutes' => 0, 'dropped_events' => 0,
                'reasons' => '[]', 'updated_at' => $now]);
        }
    }
    $db->table(T::BATCHES)->insert(['batch_id' => 'b1', 'sha256' => str_repeat('0', 64), 'schema_version' => 1, 'batch_minute' => intdiv($now, 60) - 5,
        'lines' => 1, 'events' => 1, 'malformed' => 0, 'late_periods' => 0, 'applied_at' => $now - 120]);
    foreach ([DownloadRejectionRecorder::TABLE, ...array_keys(T::tables())] as $table) {
        $db->statement('ANALYZE TABLE `'.$table.'`');
    }
}

/** @return array<string, string> table => checksum */
function mmChecksums(): array
{
    $sums = [];
    foreach ([DownloadRejectionRecorder::TABLE, ...array_keys(T::tables())] as $table) {
        $sums[$table] = (string) DB::connection('main')->selectOne('CHECKSUM TABLE `'.$table.'`')->Checksum;
    }

    return $sums;
}

beforeEach(function () {
    if (mmConfig() === null) {
        $this->markTestSkipped('No disposable MariaDB configured (W2A_MARIADB_TEST_*).');
    }
    config(['database.connections.main' => mmConfig()]);
    DB::purge('main');
    expect(DB::connection('main')->selectOne('select version() as v')->v)->toContain('MariaDB');

    foreach ([DownloadRejectionRecorder::TABLE, ...array_reverse(array_keys(T::tables()))] as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    // The migration's up() is a no-op under unit tests: the fixture gives the columns, and the migration's four
    // indexes are added exactly as it declares them (kept in sync by the first test below).
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());
    Schema::connection('main')->table(DownloadRejectionRecorder::TABLE, function ($table) {
        $table->index('created_at', 'idx_dr_created');
        $table->index(['module', 'resource_id', 'created_at'], 'idx_dr_resource');
        $table->index(['client_ip', 'created_at'], 'idx_dr_ip');
        $table->index(['reason', 'created_at'], 'idx_dr_reason');
    });
    foreach (T::tables() as $table => $definition) {
        Schema::connection('main')->create($table, $definition);
    }

    $this->root = sys_get_temp_dir().'/mm-'.bin2hex(random_bytes(5));
    $this->slotDir = $this->root.'-slots';
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 4, 'downloads.slot_directory' => $this->slotDir]);
    $this->now = time();
    mmSeed($this->now);
});

afterEach(function () {
    if (! isset($this->root)) {
        return;
    }
    foreach ([DownloadRejectionRecorder::TABLE, ...array_reverse(array_keys(T::tables()))] as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    DB::purge('main');
    foreach ([$this->root, $this->slotDir] as $dir) {
        foreach (glob($dir.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
            is_file($f) && @unlink($f);
        }
        @rmdir($dir);
    }
});

it('the production migration declares the index the rejection count relies on', function () {
    expect((string) file_get_contents(base_path('database/migrations/2026_10_22_130000_create_download_rejections_table.php')))
        ->toContain("\$table->index('created_at', 'idx_dr_created');");
});

it('every monitoring query uses an index — no full scan of a monitored table — and the count is capped', function () {
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = [$q->sql, $q->bindings];
    });

    $report = mmMonitor($this)->collect($this->now);

    $plans = [];
    foreach ($queries as [$sql, $bindings]) {
        if (! preg_match('/^\s*select/i', $sql) || str_contains($sql, 'information_schema') || str_contains($sql, 'version()')) {
            continue;
        }
        foreach (DB::connection('main')->select('EXPLAIN '.$sql, $bindings) as $row) {
            $row = (array) $row;
            $plans[] = $row + ['sql' => $sql];
            if (in_array($row['table'] ?? null, [DownloadRejectionRecorder::TABLE, ...array_keys(T::tables())], true)) {
                expect($row['type'])->not->toBe('ALL', "full scan: {$sql}");
            }
        }
    }
    fwrite(STDERR, 'MM_EXPLAIN '.json_encode(array_map(fn ($p) => ['table' => $p['table'] ?? null, 'type' => $p['type'] ?? null,
        'key' => $p['key'] ?? null, 'rows' => $p['rows'] ?? null, 'Extra' => $p['Extra'] ?? null], $plans)).PHP_EOL);

    expect(collect($queries)->pluck(0)->first(fn ($s) => str_contains($s, DownloadRejectionRecorder::TABLE) && str_contains($s, 'COUNT')))
        ->toContain('LIMIT 100000')
        ->and($report['checks']['capacity_rejections'])->toMatchArray(['status' => 'warn', 'in_window' => 42])
        ->and($report['checks']['transfers']['status'])->toBe('ok')
        ->and($report['checks']['transfers']['completed'])->toBe(13 * 50)
        ->and(collect($report['checks'])->pluck('status')->unique()->values()->all())->not->toContain('unknown');
});

it('is read-only on MariaDB: no write statement, identical table checksums before and after', function () {
    $before = mmChecksums();
    $writes = [];
    DB::listen(function ($q) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|create|drop|alter|truncate|lock|set)/i', $q->sql) === 1) {
            $writes[] = $q->sql;
        }
    });

    mmMonitor($this)->collect($this->now);
    mmMonitor($this)->collect($this->now);

    expect($writes)->toBe([])->and(mmChecksums())->toBe($before);
});

it('reports period quality (one incomplete day in the last week) and the IP/UA growth warnings', function () {
    $quiet = mmMonitor($this)->collect($this->now)['checks'];
    $loud = mmMonitor($this, ['ip_ua_warn_rows_per_hour' => 30, 'ip_ua_critical_rows_per_hour' => 1000])->collect($this->now)['checks'];
    $critical = mmMonitor($this, ['ip_ua_warn_rows_per_hour' => 10, 'ip_ua_critical_rows_per_hour' => 35])->collect($this->now)['checks'];

    expect($quiet['period_quality'])->toMatchArray(['status' => 'warn', 'incomplete_days' => [gmdate('Y-m-d', intdiv($this->now, 86400) * 86400 - 3 * 86400)]])
        ->and($quiet['period_quality']['last_7_days'])->toBe(['final' => 7, 'incomplete' => 1])
        ->and($quiet['ip_ua_growth'])->toMatchArray(['status' => 'ok', 'latest_full_hour_rows' => 40])
        ->and($quiet['ip_ua_growth']['actual_table_bytes'])->toBeInt()
        ->and($loud['ip_ua_growth'])->toMatchArray(['status' => 'warn', 'warnings' => ['rows_per_hour_warning']])
        ->and($critical['ip_ua_growth'])->toMatchArray(['status' => 'critical', 'warnings' => ['rows_per_hour_critical']]);
});

it('the command against MariaDB: JSON report and exit codes', function () {
    config(['download_telemetry' => mmSettings($this), 'download_monitor.thresholds.rejections_critical' => 40]);
    expect(Artisan::call('downloads:monitor', ['--json' => true]))->toBe(2);   // 42 rejections in the hour ≥ 40 → critical
    $report = json_decode(Artisan::output(), true);
    expect($report['overall'])->toBe('critical')->and($report['checks']['capacity_rejections']['in_window'])->toBe(42);

    DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->delete();
    DB::connection('main')->table(T::PERIODS)->update(['state' => 'final']);
    expect(Artisan::call('downloads:monitor'))->toBe(0);

    DB::connection('main')->table(T::PERIODS)->where('granularity', 'day')->where('period_start', intdiv($this->now, 86400) * 86400)->update(['state' => 'incomplete']);
    expect(Artisan::call('downloads:monitor'))->toBe(1);
});
