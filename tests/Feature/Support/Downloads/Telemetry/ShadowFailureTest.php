<?php

/*
 * Phase 3C — failure scenarios against a real server (PHP built-in server, 2 workers, the real HTTP kernel). In every
 * case the download must be exactly as available as with telemetry disabled, and every telemetry loss must be visible.
 *
 *  - sealer AND rollup stopped until the spool's file bound is reached, then resumed;
 *  - the rollup's database unavailable (and back);
 *  - the serving process SIGKILLed mid-transfer;
 *  - a failed drop ledger must not add latency (regression for the Phase 3C finding).
 *
 * Covered elsewhere (cited in the report): source timeout / client disconnect (DownloadHttpIntegrationTest), capacity
 * exhaustion (ShadowValidationTest, DownloadRecorderTest), spool exhaustion / breaker / ledger failure
 * (ShadowValidationTest), late batches and unsupported versions (RollupTest, CoverageAndLateEvidenceTest), rollup
 * crashes (RollupCrashTest, MariaDbIntegrationTest).
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\Monitoring\SlotInspector;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocalHttpSource;
use Tests\Support\Telemetry\ShadowServer;

function sfSettings(ShadowServer $s, array $extra = []): array
{
    return $extra + ['spool' => 'on', 'root' => $s->root, 'aggregate' => 'on', 'connection' => 'main', 'seal_grace_minutes' => 0, 'disk_reserve_bytes' => 0, 'ledger_shards' => 4, 'ledger_slots' => 120];
}

function sfDropped(ShadowServer $s): int
{
    return array_sum(array_column((new DropLedger(SpoolConfig::fromArray(sfSettings($s))))->snapshot()['entries'], 'dropped_events'));
}

beforeEach(function () {
    DropLedger::resetProcessStateForTesting();
    $this->s = new ShadowServer(workers: 2);
    file_put_contents($this->s->scratch.'/talk.mp3', str_repeat('A', 100_000));
    $this->item = $this->s->addItem($this->s->scratch.'/talk.mp3');
    $this->s->configure(['root' => $this->s->root]);
    $this->s->start();
    config(['database.connections.main' => config('database.connections.shadow')]);
    DB::purge('main');
});

afterEach(function () {
    $this->s->destroy();
    DropLedger::resetProcessStateForTesting();
});

it('sealer and rollup stopped: the spool fills to its bound, downloads stay identical, drops are counted; resuming both recovers', function () {
    $s = $this->s;
    $s->configure(['root' => $s->root, 'max_files' => 3]);
    // Three earlier minutes left unsealed (the sealer "stopped"): the file bound is reached.
    $config = SpoolConfig::fromArray(sfSettings($s, ['max_files' => 3]));
    @mkdir($config->activeDir(), 0750, true);
    foreach ([5, 4, 3] as $ago) {
        file_put_contents($config->activePath(intdiv(time(), 60) - $ago), '{"v":1,"t":'.((intdiv(time(), 60) - $ago) * 60 + 1).',"ev":"download_accepted","mod":"khotab"}'."\n");
    }

    $blocked = array_map(fn () => $s->request("/khotab-download-{$this->item}.htm"), range(1, 3));

    expect(array_column($blocked, 'status'))->toBe([200, 200, 200])
        ->and(array_column($blocked, 'bytes'))->toBe([100_000, 100_000, 100_000])
        ->and(count($s->events()))->toBe(3)               // only the three pre-existing lines
        ->and(sfDropped($s))->toBe(9);                     // 3 downloads × 3 events, all counted

    // Resume: seal everything, roll it up (moves batches to done/, freeing the bound).
    (new SpoolSealer($config))->seal(true);
    $report = (new RollupRunner($config, RollupConfig::fromArray(sfSettings($s, ['max_files' => 3]))))->run();
    $after = $s->request("/khotab-download-{$this->item}.htm");

    expect($report['errors'])->toBe([])->and($report['applied'])->toBe(3)
        ->and($after['status'])->toBe(200)
        ->and(count(glob($config->activeDir().'/*.ndjson')))->toBe(1)   // new events written again
        ->and(DB::connection('main')->table(T::PERIODS)->where('state', 'final')->count())->toBe(0);   // drops keep periods non-final
});

it('the rollup database unavailable: the run reports a sanitized error, batches stay sealed, nothing is lost; recovery applies them once', function () {
    $s = $this->s;
    $s->request("/khotab-download-{$this->item}.htm");
    $config = SpoolConfig::fromArray(sfSettings($s));
    (new SpoolSealer($config))->seal(true);

    config(['database.connections.main' => ['driver' => 'sqlite', 'database' => $s->scratch.'/no-such-dir/db.sqlite', 'prefix' => '']]);
    DB::purge('main');
    $down = (new RollupRunner($config, RollupConfig::fromArray(sfSettings($s))))->run();

    expect($down['errors'])->not->toBe([])
        ->and(implode(' ', $down['errors']))->not->toContain('127.0.0.1')->not->toContain('no-such-dir')   // class and code only
        ->and(glob($config->sealedDir().'/*.ndjson'))->toHaveCount(1)
        ->and($s->request("/khotab-download-{$this->item}.htm")['status'])->toBe(200);   // downloads never touch the rollup

    config(['database.connections.main' => config('database.connections.shadow')]);
    DB::purge('main');
    (new SpoolSealer($config))->seal(true);
    $up = (new RollupRunner($config, RollupConfig::fromArray(sfSettings($s))))->run();

    expect($up['errors'])->toBe([])->and($up['applied'])->toBe(2)
        ->and((int) DB::connection('main')->table(T::METRICS_5M)->where('event', 'transfer_finished')->sum('events'))->toBe(2);
});

it('the serving process is SIGKILLed mid-transfer: the slot is freed by the kernel, the spool stays valid, the unterminated transfer is visible', function () {
    $s = $this->s;
    $chunk = str_repeat('k', 65536);
    $source = LocalHttpSource::start([[
        'raw' => "HTTP/1.1 200 OK\r\nContent-Type: video/mp4\r\nContent-Length: ".(65536 * 200)."\r\nConnection: close\r\n\r\n",
        'drip' => ['chunk' => $chunk, 'count' => 200, 'interval' => 0.05],
    ]]);
    $slow = $s->addItem($source->url('/slow.mp4'));

    $client = stream_socket_client('tcp://127.0.0.1:'.$s->port, $e, $m, 5);
    fwrite($client, "GET /khotab-download-{$slow}.htm HTTP/1.0\r\nHost: x\r\n\r\n");
    fread($client, 8192);   // the transfer is under way
    usleep(300_000);

    $holders = SlotInspector::inspect($s->slotDir, 16, (float) time())['holders'];
    expect($holders)->toHaveCount(1);
    posix_kill($holders[0]['pid'], SIGKILL);   // this test's own isolated server worker
    usleep(300_000);
    fclose($client);
    $source->stop();

    $events = $s->events();
    $byEvent = array_count_values(array_column($events, 'ev'));
    expect(SlotInspector::inspect($s->slotDir, 16, (float) time()))->toMatchArray(['occupied' => 0, 'visibility' => 'probe_visible'])
        ->and(($byEvent['download_accepted'] ?? 0))->toBe(1)
        ->and(($byEvent['transfer_started'] ?? 0))->toBe(1)
        ->and(($byEvent['transfer_finished'] ?? 0))->toBe(0);     // no terminal event: SIGKILL runs no PHP code

    // PHP's built-in server does not survive the loss of a worker (a development-server artefact; production PHP-CGI
    // uses one process per request). Restart it, as a supervisor would: downloads work at once — no stale slot.
    $s->stop();
    $s->start();
    expect($s->request("/khotab-download-{$this->item}.htm")['status'])->toBe(200);
    // The spool parses cleanly and the rollup shows the unterminated transfer: started − finished = 1.
    $config = SpoolConfig::fromArray(sfSettings($s));
    (new SpoolSealer($config))->seal(true);
    $report = (new RollupRunner($config, RollupConfig::fromArray(sfSettings($s))))->run();
    $m5 = DB::connection('main')->table(T::METRICS_5M)->groupBy('event')->selectRaw('event, SUM(events) AS n')->pluck('n', 'event')->map(fn ($n) => (int) $n)->all();
    $malformed = (int) DB::connection('main')->table(T::BATCHES)->sum('malformed');

    expect($report['errors'])->toBe([])->and($malformed)->toBe(0)
        ->and($m5['transfer_started'] - $m5['transfer_finished'])->toBe(1);
});

it('a drop ledger that cannot be opened fails fast: no per-event retry budget is spent (Phase 3C regression)', function () {
    $root = $this->s->scratch.'/ledger-ro';
    mkdir($root, 0750, true);
    mkdir($root.'/drops', 0555);
    if (is_writable($root.'/drops')) {
        $this->markTestSkipped('running as a user that ignores file permissions');
    }
    $ledger = new DropLedger(SpoolConfig::fromArray(['spool' => 'on', 'root' => $root, 'ledger_shards' => 4, 'ledger_lock_budget_us' => 50_000]));

    $t = hrtime(true);
    for ($i = 0; $i < 20; $i++) {
        $recorded = $ledger->record(intdiv(time(), 60), ['dropped_events' => 1, 'dropped_bytes' => 1, 'reason' => DropReason::FILE_COUNT]);
    }
    $ms = (hrtime(true) - $t) / 1e6;

    expect($recorded)->toBeFalse()
        ->and(DropLedger::pendingEvents())->toBe(20)    // held for the shutdown report, not lost
        ->and($ms)->toBeLessThan(50.0);                 // was ≥ 20 × 50 ms before the fix
    DropLedger::resetProcessStateForTesting();
});

it('the download path with the main database unavailable fails identically with telemetry on or off', function () {
    $s = $this->s;
    $results = [];
    foreach (['off' => null, 'on' => ['root' => $s->root]] as $mode => $telemetry) {
        $s->configure($telemetry);
        $cfg = json_decode((string) file_get_contents($s->scratch.'/config.json'), true);
        $cfg['config']['database.connections.main']['database'] = $s->scratch.'/no-such-dir/db.sqlite';
        file_put_contents($s->scratch.'/config.json', json_encode($cfg));
        $r = $s->request("/khotab-download-{$this->item}.htm");
        unset($r['ms']);
        $results[$mode] = $r;
    }

    expect($results['on'])->toBe($results['off'])
        ->and($results['on']['status'])->toBe(500)
        ->and(app(DownloadCapacityGuard::class)->tryAcquire()?->isHeld())->toBeTrue();   // no slot leaked
});
