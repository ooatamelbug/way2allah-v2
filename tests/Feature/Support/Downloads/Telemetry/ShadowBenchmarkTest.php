<?php

/*
 * Phase 3C — bounded local performance measurements (runs only with W2A_SHADOW_BENCH=1; prints BENCH JSON lines).
 *
 *  1. recorder overhead: DownloadTelemetry::emit() disabled / enabled / breaker open / spool full, per event;
 *  2. lock contention: 8 processes writing to one spool minute at once;
 *  3. download throughput and latency through the real server, telemetry off vs on, alternated, after warm-up,
 *     at the realistic concurrency (4 = CPU count) and under stress (16 clients against 8 slots);
 *  4. slot occupancy duration, spool growth and drop rate from the events themselves;
 *  5. rollup processing time for 100,000 events (SQLite file; MariaDB when W2A_MARIADB_TEST_* is set);
 *  6. monitoring overhead with a populated spool and 400 slot files.
 *
 * Local, bounded, never production. Host load is printed with the results: treat absolute numbers as indicative.
 */

use App\Support\Downloads\Monitoring\DownloadMonitor;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DownloadTelemetry;
use App\Support\Downloads\Telemetry\DownloadTelemetryContext;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Telemetry\ShadowServer;

function sbPct(array $values, float $p): ?float
{
    if ($values === []) {
        return null;
    }
    sort($values);

    return round($values[min(count($values) - 1, (int) floor(count($values) * $p))], 3);
}

function sbOut(string $name, array $data): void
{
    fwrite(STDERR, 'BENCH '.json_encode(['bench' => $name] + $data + ['load' => sys_getloadavg()[0]]).PHP_EOL);
}

beforeEach(function () {
    if (getenv('W2A_SHADOW_BENCH') !== '1') {
        $this->markTestSkipped('Benchmarks run only with W2A_SHADOW_BENCH=1.');
    }
    DropLedger::resetProcessStateForTesting();
    $this->tmp = sys_get_temp_dir().'/sb-'.bin2hex(random_bytes(5));
    mkdir($this->tmp);
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    if (isset($this->tmp) && is_dir($this->tmp)) {
        exec('rm -rf '.escapeshellarg($this->tmp));   // this benchmark's own scratch directory only
    }
});

it('1. recorder overhead per event', function () {
    $request = Request::create('/khotab-download-1.htm', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Bench', 'REMOTE_ADDR' => '203.0.113.7']);
    $context = new DownloadTelemetryContext('khotab', 'khotab_item', 1, 'primary');
    $cases = [
        'disabled' => fn () => new DownloadTelemetry(null, false),
        'enabled' => fn () => new DownloadTelemetry(new SpoolWriter(SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->tmp.'/a', 'disk_reserve_bytes' => 0])), true),
        'breaker_open' => function () {
            @mkdir($this->tmp.'/b', 0750, true);
            touch($this->tmp.'/b/breaker', time() + 3600);

            return new DownloadTelemetry(new SpoolWriter(SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->tmp.'/b', 'disk_reserve_bytes' => 0])), true);
        },
        'spool_full' => function () {
            @mkdir($this->tmp.'/c/quarantine', 0750, true);
            touch($this->tmp.'/c/quarantine/x.ndjson');

            return new DownloadTelemetry(new SpoolWriter(SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->tmp.'/c', 'max_files' => 1, 'disk_reserve_bytes' => 0])), true);
        },
    ];
    foreach ($cases as $name => $make) {
        $recorder = $make();
        $samples = [];
        for ($i = 0; $i < 20_000; $i++) {
            $t = hrtime(true);
            $recorder->emit($request, 'transfer_finished', $context, ['st' => 'completed', 'b' => 2097152, 'd' => 1234]);
            $samples[] = (hrtime(true) - $t) / 1000;
        }
        sbOut('recorder_overhead', ['case' => $name, 'events' => 20_000, 'us_mean' => round(array_sum($samples) / count($samples), 2),
            'us_p50' => sbPct($samples, 0.5), 'us_p99' => sbPct($samples, 0.99), 'us_max' => round(max($samples), 1)]);
    }
    expect(true)->toBeTrue();
});

it('2. lock contention: 8 processes × 5,000 events into one spool minute', function () {
    $code = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $c = App\Support\Downloads\Telemetry\SpoolConfig::fromArray(['spool' => 'on', 'root' => $argv[2], 'disk_reserve_bytes' => 0]);
        $w = new App\Support\Downloads\Telemetry\SpoolWriter($c);
        while (! file_exists($argv[3])) { usleep(1000); }
        $lat = []; $t0 = hrtime(true);
        for ($i = 0; $i < 5000; $i++) { $t = hrtime(true); $w->write(['t' => microtime(true), 'ev' => 'transfer_started', 'mod' => 'khotab', 'rid' => $i, 'ip' => '203.0.113.9', 'uah' => str_repeat('a', 32), 'sh' => 'archive.org']); $lat[] = (hrtime(true) - $t) / 1000; }
        sort($lat);
        echo json_encode(['s' => (hrtime(true) - $t0) / 1e9, 'p50' => $lat[2500], 'p99' => $lat[4950], 'max' => end($lat)] + $w->stats()), "\n";
        PHP;
    $procs = [];
    foreach (range(1, 8) as $i) {
        $procs[] = proc_open([PHP_BINARY, '-r', $code, base_path(), $this->tmp.'/spool', $this->tmp.'/go'], [1 => ['pipe', 'w']], $pipes);
        $outs[] = $pipes[1];
    }
    usleep(500_000);
    $t = microtime(true);
    touch($this->tmp.'/go');
    $results = array_map(fn ($p) => json_decode((string) stream_get_contents($p), true), $outs);
    $wall = microtime(true) - $t;
    array_map('proc_close', $procs);

    $written = array_sum(array_column($results, 'written'));
    $dropped = array_sum(array_column($results, 'dropped'));
    sbOut('lock_contention', ['processes' => 8, 'events' => 40_000, 'wall_s' => round($wall, 3), 'events_per_s' => (int) ($written / $wall),
        'written' => $written, 'dropped' => $dropped, 'drop_rate' => round($dropped / 40_000, 5), 'lock_spins' => array_sum(array_column($results, 'lock_spins')),
        'us_p50_worst_process' => round(max(array_column($results, 'p50')), 1), 'us_p99_worst_process' => round(max(array_column($results, 'p99')), 1),
        'us_max' => round(max(array_column($results, 'max')), 1), 'dropped_by_reason' => array_merge_recursive(...array_column($results, 'dropped_by_reason'))]);
    expect($written + $dropped)->toBe(40_000);
});

it('3–4. download throughput, latency, slot occupancy, spool growth and drop rate — telemetry off vs on', function () {
    $s = new ShadowServer(workers: 4);
    try {
        $file = $s->scratch.'/lecture.mp3';
        $fh = fopen($file, 'wb');
        for ($i = 0; $i < 16; $i++) {
            fwrite($fh, random_bytes(65536));   // 1 MiB
        }
        fclose($fh);
        $item = $s->addItem($file);
        $s->configure(null);
        $s->start();
        $s->concurrent(array_fill(0, 16, "/khotab-download-{$item}.htm"), 4);   // warm-up (opcache, autoload)

        $round = function (string $mode, int $parallel, int $requests, int $slots) use ($s, $item) {
            $root = $s->scratch.'/spool-'.$mode.'-'.$parallel.'-'.bin2hex(random_bytes(3));
            $s->configure($mode === 'on' ? ['root' => $root] : null, ['downloads.max_concurrent' => $slots]);
            $s->root = $root;
            $before = $s->processStats();
            $t = microtime(true);
            $responses = $s->concurrent(array_fill(0, $requests, "/khotab-download-{$item}.htm"), $parallel);
            $wall = microtime(true) - $t;
            $after = $s->processStats();
            $ok = array_filter($responses, fn ($r) => $r['status'] === 200);
            $events = $mode === 'on' ? $s->events() : [];
            $occupancy = array_column(array_filter($events, fn ($e) => $e['ev'] === 'transfer_finished'), 'd');
            $dropped = $mode === 'on' ? array_sum(array_column((new DropLedger(SpoolConfig::fromArray($s->telemetry)))->snapshot()['entries'], 'dropped_events')) : 0;

            return ['mode' => $mode, 'parallel' => $parallel, 'slots' => $slots, 'requests' => $requests, 'ok' => count($ok),
                'rejected_503' => count(array_filter($responses, fn ($r) => $r['status'] === 503)),
                'req_per_s' => round(count($responses) / $wall, 1), 'mib_per_s' => round(array_sum(array_column($ok, 'bytes')) / 1048576 / $wall, 1),
                'ms_p50' => sbPct(array_column($ok, 'ms'), 0.5), 'ms_p95' => sbPct(array_column($ok, 'ms'), 0.95), 'ms_p99' => sbPct(array_column($ok, 'ms'), 0.99),
                'server_cpu_ms_per_request' => round(($after['cpu_s'] - $before['cpu_s']) * 1000 / count($responses), 2),
                'slot_held_ms_p50' => sbPct($occupancy, 0.5), 'slot_held_ms_p95' => sbPct($occupancy, 0.95),
                'events' => count($events), 'spool_bytes' => $s->spoolBytes(), 'spool_bytes_per_request' => $mode === 'on' ? (int) ($s->spoolBytes() / count($responses)) : 0,
                'drop_rate' => count($events) + $dropped > 0 ? round($dropped / (count($events) + $dropped), 5) : 0.0];
        };

        foreach ([[4, 120, 16], [16, 160, 8]] as [$parallel, $requests, $slots]) {
            foreach (['off', 'on', 'off', 'on'] as $mode) {   // alternated, so drift and load affect both
                sbOut('download', $round($mode, $parallel, $requests, $slots));
            }
        }
    } finally {
        $s->destroy();
    }
    expect(true)->toBeTrue();
});

it('5–6. rollup processing time for 100,000 events, and monitoring overhead', function () {
    $targets = ['sqlite' => ['driver' => 'sqlite', 'database' => $this->tmp.'/rollup.sqlite', 'prefix' => '', 'foreign_key_constraints' => false]];
    if (getenv('W2A_MARIADB_TEST_HOST')) {
        $targets['mariadb'] = ['driver' => 'mysql', 'host' => getenv('W2A_MARIADB_TEST_HOST'), 'port' => (string) getenv('W2A_MARIADB_TEST_PORT'),
            'database' => (string) getenv('W2A_MARIADB_TEST_DATABASE'), 'username' => (string) getenv('W2A_MARIADB_TEST_USERNAME'),
            'password' => (string) getenv('W2A_MARIADB_TEST_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
    }
    foreach ($targets as $name => $connection) {
        touch($this->tmp.'/rollup.sqlite');
        config(['database.connections.main' => $connection]);
        DB::purge('main');
        foreach (array_reverse(array_keys(T::tables())) as $table) {
            Schema::connection('main')->dropIfExists($table);
        }
        foreach (T::tables() as $table => $definition) {
            Schema::connection('main')->create($table, $definition);
        }
        $root = $this->tmp.'/spool-'.$name;
        $settings = ['spool' => 'on', 'root' => $root, 'aggregate' => 'on', 'connection' => 'main', 'seal_grace_minutes' => 0, 'disk_reserve_bytes' => 0, 'rollup_max_batches' => 1000, 'rollup_time_budget_seconds' => 600];
        $minute0 = intdiv(time(), 60) - 10;
        $tw = microtime(true);
        for ($m = 0; $m < 5; $m++) {
            $writer = new SpoolWriter(SpoolConfig::fromArray($settings), fn (): float => ($minute0 + $m) * 60 + 1.0);
            for ($i = 0; $i < 20_000; $i++) {
                $writer->write(['t' => ($minute0 + $m) * 60 + 1.0 + $i / 1000, 'ev' => $i % 3 === 0 ? 'download_accepted' : ($i % 3 === 1 ? 'transfer_started' : 'transfer_finished'),
                    'mod' => 'khotab', 'rt' => 'khotab_item', 'rid' => $i % 5000, 'var' => 'primary', 'ip' => '198.51.'.intdiv($i % 2000, 250).'.'.($i % 250),
                    'uah' => substr(md5((string) ($i % 300)), 0, 32), 'uaf' => 'browser', 'sh' => 'ia800'.($i % 20).'.us.archive.org', 'st' => 'completed', 'b' => 1048576, 'd' => 4000]);
            }
        }
        $writeS = microtime(true) - $tw;
        $bytes = array_sum(array_map('filesize', glob($root.'/active/*.ndjson')));
        (new SpoolSealer(SpoolConfig::fromArray($settings)))->seal(true);
        $t = microtime(true);
        $report = (new RollupRunner(SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings)))->run();
        $rollupS = microtime(true) - $t;
        sbOut('rollup', ['database' => $name, 'events' => 100_000, 'batches' => $report['applied'], 'errors' => $report['errors'],
            'rollup_s' => round($rollupS, 2), 'events_per_s' => (int) (100_000 / $rollupS), 'spool_bytes' => $bytes, 'bytes_per_event' => (int) ($bytes / 100_000),
            'write_s_single_process' => round($writeS, 2), 'ip_ua_rows' => DB::connection('main')->table(T::IP_UA_HOURLY)->count(),
            'php_peak_mib_test_process' => round(memory_get_peak_usage(true) / 1048576, 1)]);
        expect($report['errors'])->toBe([]);

        // Monitoring overhead on the populated database and spool, with 400 slot files.
        $slotDir = $this->tmp.'/slots-'.$name;
        mkdir($slotDir);
        foreach (range(1, 400) as $n) {
            touch(sprintf('%s/slot-%03d.lock', $slotDir, $n));
        }
        config(['downloads.guard' => true, 'download_telemetry' => $settings + ['events' => 'on']]);
        $monitor = new DownloadMonitor(DB::connection('main'), SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings), [], $slotDir, 400, true);
        $runs = [];
        foreach (range(1, 10) as $i) {
            $runs[] = $monitor->collect(time())['duration_ms'];
        }
        sbOut('monitor', ['database' => $name, 'runs' => 10, 'ms_p50' => sbPct($runs, 0.5), 'ms_max' => round(max($runs), 2)]);
    }
});
