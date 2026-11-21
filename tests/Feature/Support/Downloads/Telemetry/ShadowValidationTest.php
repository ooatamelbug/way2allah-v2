<?php

/*
 * Phase 3C — local shadow validation. One real server (PHP built-in server, 8 worker processes, the real HTTP kernel),
 * the SAME workload under six telemetry configurations, compared response by response:
 *
 *   disabled · healthy spool · full spool · breaker open · failed ledger (drops cannot be recorded) · concurrency
 *
 * Compared: HTTP status, response headers (minus per-request Date / Set-Cookie), body SHA-256, download counters,
 * slot release, durations, server CPU / peak RSS / write counters, spool growth, telemetry event counts, and — for the
 * healthy runs — what the database rollup makes of the events. Results are printed as SHADOW_RESULT JSON lines.
 * NOT production-equivalent (CLI development server, not PHP-CGI/FastCGI).
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocalHttpSource;
use Tests\Support\Telemetry\ShadowServer;

function svSlotsFree(int $limit): bool
{
    $held = [];
    for ($i = 0; $i < $limit; $i++) {
        $held[] = app(DownloadCapacityGuard::class)->tryAcquire();
    }
    $free = count(array_filter($held, fn ($s) => $s?->isHeld())) === $limit;
    array_map(fn ($s) => $s?->release(), $held);

    return $free;
}

/** The fixed workload. @return array{responses: array<string, array<string, mixed>>, counters: array<string, int>, stats: array<string, mixed>} */
function svWorkload(ShadowServer $s, array $items, string $label): array
{
    $before = $s->processStats();
    $spoolBefore = $s->spoolBytes();
    $counters = array_map(fn ($id) => $s->downcount($id), $items);
    $responses = [];
    $t0 = microtime(true);

    foreach (range(1, 6) as $i) {
        $responses["local-{$i}"] = $s->request("/khotab-download-{$items['local']}.htm");
    }
    $source = LocalHttpSource::start([LocalHttpSource::ok(str_repeat('R', 300_000), 'audio/mpeg'), LocalHttpSource::ok(str_repeat('R', 300_000), 'audio/mpeg')]);
    DB::connection('shadow')->table('nuke_islamic_khotab')->where('id', $items['remote'])->update(['link' => $source->url('/archive/talk.mp3?sig=SECRET')]);
    $responses['remote-1'] = $s->request("/khotab-download-{$items['remote']}.htm");
    $responses['remote-2'] = $s->request("/khotab-download-{$items['remote']}.htm");
    $source->stop();
    $responses['head'] = $s->request("/khotab-download-{$items['local']}.htm", 'HEAD');
    $responses['missing'] = $s->request("/khotab-download-{$items['missing']}.htm");

    // Capacity exhaustion: every slot held by this (test) process.
    $held = [];
    for ($i = 0; $i < 16; $i++) {
        $held[] = app(DownloadCapacityGuard::class)->tryAcquire();
    }
    $responses['capacity'] = $s->request("/khotab-download-{$items['local']}.htm");
    array_map(fn ($h) => $h?->release(), $held);

    $concurrent = $s->concurrent(array_fill(0, 24, "/khotab-download-{$items['local']}.htm"), 8);
    $elapsed = microtime(true) - $t0;
    foreach ($concurrent as $i => $r) {
        $responses["concurrent-{$i}"] = $r;
    }

    $after = $s->processStats();
    $durations = array_column(array_filter($responses, fn ($r) => $r['status'] === 200 && $r['bytes'] > 0), 'ms');
    sort($durations);

    return [
        'responses' => $responses,
        'counters' => array_map(fn ($id, $k) => $s->downcount($id) - $counters[$k], $items, array_keys($items)),
        'stats' => [
            'label' => $label,
            'requests' => count($responses),
            'wall_s' => round($elapsed, 3),
            'download_ms_p50' => $durations[intdiv(count($durations), 2)] ?? null,
            'download_ms_p95' => $durations[(int) floor(count($durations) * 0.95)] ?? null,
            'server_cpu_s' => round($after['cpu_s'] - $before['cpu_s'], 2),
            'server_rss_peak_kib' => $after['rss_peak_kib'],
            'server_wchar_bytes' => $after['wchar'] - $before['wchar'],
            'server_block_write_bytes' => $after['write_bytes'] - $before['write_bytes'],
            'spool_growth_bytes' => $s->spoolBytes() - $spoolBefore,
            'slots_free_after' => svSlotsFree(16),
        ],
    ];
}

/** @return array<string, int> */
function svEventCounts(ShadowServer $s): array
{
    $counts = [];
    foreach ($s->events() as $e) {
        $key = $e['ev'].(isset($e['st']) ? ':'.$e['st'] : '');
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    ksort($counts);

    return $counts;
}

beforeEach(function () {
    DropLedger::resetProcessStateForTesting();
    $this->s = new ShadowServer(workers: 8);
    $file = $this->s->scratch.'/lecture.mp3';
    $fh = fopen($file, 'wb');
    for ($i = 0; $i < 32; $i++) {
        fwrite($fh, random_bytes(65536));   // 2 MiB of incompressible data
    }
    fclose($fh);
    $this->items = ['local' => $this->s->addItem($file), 'remote' => $this->s->addItem('x'), 'missing' => $this->s->addItem($this->s->scratch.'/missing.mp3')];
    $this->s->configure(null);
    $this->s->start();
});

afterEach(function () {
    $this->s->destroy();
    DropLedger::resetProcessStateForTesting();
});

it('every telemetry configuration produces the same responses, counters and slot behaviour as telemetry disabled', function () {
    $s = $this->s;
    $scenarios = [
        'disabled' => null,
        'healthy' => ['root' => $s->scratch.'/spool-healthy'],
        'spool_full' => ['root' => $s->scratch.'/spool-full', 'max_files' => 1],
        'breaker_open' => ['root' => $s->scratch.'/spool-breaker'],
        'ledger_failed' => ['root' => $s->scratch.'/spool-ledger', 'max_files' => 1],
    ];
    @mkdir($s->scratch.'/spool-full/quarantine', 0750, true);
    file_put_contents($s->scratch.'/spool-full/quarantine/occupied.ndjson', '');
    @mkdir($s->scratch.'/spool-breaker', 0750, true);
    touch($s->scratch.'/spool-breaker/breaker', time() + 3600);
    @mkdir($s->scratch.'/spool-ledger/quarantine', 0750, true);
    file_put_contents($s->scratch.'/spool-ledger/quarantine/occupied.ndjson', '');
    @mkdir($s->scratch.'/spool-ledger/drops', 0555, true);   // the drop ledger can be neither created nor written

    $runs = [];
    foreach ($scenarios as $name => $telemetry) {
        $s->configure($telemetry);
        $s->root = $telemetry['root'] ?? $s->scratch.'/spool-none';
        $runs[$name] = svWorkload($s, $this->items, $name);
        $runs[$name]['events'] = $telemetry === null ? [] : svEventCounts($s);
        $runs[$name]['ledger_dropped'] = $telemetry === null ? 0 : array_sum(array_column(
            (new DropLedger(SpoolConfig::fromArray($s->telemetry)))->snapshot()['entries'], 'dropped_events'));
        fwrite(STDERR, 'SHADOW_RESULT '.json_encode($runs[$name]['stats'] + ['events' => $runs[$name]['events'], 'ledger_dropped' => $runs[$name]['ledger_dropped'], 'counters' => $runs[$name]['counters']]).PHP_EOL);
    }

    $baseline = $runs['disabled'];
    // Baseline sanity: the workload exercised every path.
    expect($baseline['responses']['local-1'])->toMatchArray(['status' => 200, 'bytes' => 2 * 1024 * 1024])
        ->and($baseline['responses']['remote-1'])->toMatchArray(['status' => 200, 'bytes' => 300_000])
        ->and($baseline['responses']['head'])->toMatchArray(['status' => 200, 'bytes' => 0])
        ->and($baseline['responses']['missing']['status'])->toBe(404)
        ->and($baseline['responses']['capacity']['status'])->toBe(503)
        // GETs count; HEAD and 503 do not. The missing-file GET counts once BEFORE its 404 — existing behaviour
        // (the counter precedes the file check, as in legacy download_khotab()), identical in every scenario.
        ->and($baseline['counters'])->toBe([6 + 24, 2, 1]);

    foreach ($runs as $name => $run) {
        foreach ($baseline['responses'] as $key => $expected) {
            $actual = $run['responses'][$key];
            unset($expected['ms'], $actual['ms']);
            expect($actual)->toBe($expected, "{$name}: {$key} differs from telemetry disabled");
        }
        expect($run['counters'])->toBe($baseline['counters'], "{$name}: counters")
            ->and($run['stats']['slots_free_after'])->toBeTrue("{$name}: slots not all free");
    }

    // Telemetry outcomes per scenario — nothing hidden.
    $expectedHealthy = ['capacity_rejected:rejected' => 1, 'download_accepted' => 33, 'head_answered:head' => 1, 'source_open_failed' => 1,
        'transfer_finished:completed' => 32, 'transfer_started' => 32];
    expect($runs['healthy']['events'])->toBe($expectedHealthy)
        ->and($runs['healthy']['ledger_dropped'])->toBe(0)
        ->and($runs['spool_full']['events'])->toBe([])
        ->and($runs['spool_full']['ledger_dropped'])->toBe(array_sum($expectedHealthy))   // every event dropped AND counted
        ->and($runs['breaker_open']['events'])->toBe([])
        ->and($runs['breaker_open']['ledger_dropped'])->toBe(array_sum($expectedHealthy))
        ->and($runs['ledger_failed']['events'])->toBe([])
        ->and($runs['ledger_failed']['ledger_dropped'])->toBe(0)                            // could not be recorded …
        ->and((string) file_get_contents($s->scratch.'/server.err'))->toContain('W2A_TELEMETRY_UNRECORDED_DROPS'); // … but reported

    // The database rollup of the healthy run: every captured event is accounted for, bytes match the responses.
    config(['database.connections.main' => config('database.connections.shadow')]);
    DB::purge('main');
    $settings = $s->telemetry = ['spool' => 'on', 'root' => $s->scratch.'/spool-healthy', 'aggregate' => 'on', 'connection' => 'main', 'seal_grace_minutes' => 0, 'disk_reserve_bytes' => 0];
    (new SpoolSealer(SpoolConfig::fromArray($settings)))->seal(true);
    $t = microtime(true);
    $report = (new RollupRunner(SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings)))->run();
    $rollupMs = round((microtime(true) - $t) * 1000, 1);
    $byEvent = DB::connection('main')->table(T::METRICS_5M)->groupBy('event', 'outcome')
        ->selectRaw('event, outcome, SUM(events) AS n, SUM(bytes_sum) AS bytes')->get()
        ->mapWithKeys(fn ($r) => [$r->event.($r->outcome === '' ? '' : ':'.$r->outcome) => [(int) $r->n, (int) $r->bytes]])->all();
    $periods = DB::connection('main')->table(T::PERIODS)->groupBy('granularity', 'state')->selectRaw('granularity, state, COUNT(*) AS n')->get()
        ->map(fn ($r) => "{$r->granularity}:{$r->state}={$r->n}")->all();
    fwrite(STDERR, 'SHADOW_ROLLUP '.json_encode(['ms' => $rollupMs, 'applied' => $report['applied'], 'errors' => $report['errors'], 'metrics' => $byEvent, 'periods' => $periods]).PHP_EOL);

    $bytesSent = array_sum(array_column(array_filter($runs['healthy']['responses'], fn ($r) => $r['status'] === 200), 'bytes'));
    expect($report['errors'])->toBe([])
        ->and(array_sum(array_column($byEvent, 0)))->toBe(array_sum($expectedHealthy))
        ->and($byEvent['transfer_finished:completed'])->toBe([32, $bytesSent])
        ->and((int) DB::connection('main')->table(T::IP_UA_HOURLY)->sum('events'))->toBe(array_sum($expectedHealthy))
        ->and($periods)->not->toBe([]);   // reported as they are (provisional / incomplete included)
});
