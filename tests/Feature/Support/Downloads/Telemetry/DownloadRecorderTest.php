<?php

/*
 * Phase 3A — the request-path download recorder, integrated with the Khotab proxied downloads only.
 *
 * Covered: disabled by default (nothing touched); the full lifecycle of a successful transfer; every terminal outcome
 * (completed, incomplete, stalled, read_failed, client_aborted) exactly once; source failures; capacity refusals; HEAD
 * recorded separately and never as an attempt or a transfer; spool full, breaker open and an unusable spool/ledger never
 * affecting the download; concurrent transfers keep their own events; no slot leaks; no sensitive data in events.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejection;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\ProxiedDownloadStreamer;
use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\DownloadTelemetry;
use App\Support\Downloads\Telemetry\DownloadTelemetryContext;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolWriter;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use App\Support\Performance\RequestMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\LocalHttpSource;

uses(RefreshDatabase::class);

const DTR_UA = 'Mozilla/5.0 (X11; Linux x86_64) SecretBrowser/1.0';

/** Point the recorder at this test's spool, then forget the per-request instance so the next request rebuilds it. */
function dtrConfigure(object $test, array $overrides = []): void
{
    config(['download_telemetry' => $overrides + [
        'events' => 'on', 'spool' => 'on', 'root' => $test->root, 'file_max_bytes' => 64 * 1024, 'max_files' => 10,
        'disk_reserve_bytes' => 0, 'lock_budget_us' => 200_000, 'ledger_lock_budget_us' => 200_000, 'rotation_retries' => 3,
        'ledger_slots' => 60, 'ledger_shards' => 2, 'slow_write_us' => 60_000_000, 'breaker_fault_threshold' => 50,
        'breaker_open_seconds' => 300, 'seal_grace_minutes' => 0, 'seal_lock_budget_us' => 200_000,
    ]]);
    app()->forgetInstance(DownloadTelemetry::class);
    app()->forgetScopedInstances();
}

/** @return list<array<string, mixed>> every event in the spool, in write order */
function dtrEvents(object $test): array
{
    $events = [];
    foreach (glob($test->root.'/active/*.ndjson') ?: [] as $file) {
        BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$events) {
            $events[] = $e->toArray();
        });
    }
    usort($events, fn ($a, $b) => $a['t'] <=> $b['t']);

    return $events;
}

/** @return list<string> */
function dtrNames(object $test): array
{
    return array_column(dtrEvents($test), 'ev');
}

function dtrSlotFree(): bool
{
    $slot = app(DownloadCapacityGuard::class)->tryAcquire();
    $free = $slot?->isHeld() === true;
    $slot?->release();

    return $free;
}

/** @return array{0: string, 1: ?Throwable} */
function dtrSend(object $response): array
{
    ob_start();
    try {
        $response->baseResponse->sendContent();
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e;
    } finally {
        $output = (string) ob_get_clean();
    }

    return [$output, $thrown];
}

function dtrLink(object $test, string $link, ?int $id = null): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $id ?? $test->item)->update(['link' => $link]);
}

function dtrServe(object $test, array $replies, string $path = '/archive/lecture.mp4'): string
{
    $test->source = LocalHttpSource::start($replies);

    return $test->source->url($path);
}

function dtrGet(object $test, string $method = 'GET', ?int $id = null, array $server = []): object
{
    return $test->call($method, '/khotab-download-'.($id ?? $test->item).'.htm', [], [], [], ['HTTP_USER_AGENT' => DTR_UA] + $server);
}

function dtrLedgerDropped(object $test): int
{
    return array_sum(array_column((new DropLedger(SpoolConfig::fromLaravel()))->snapshot()['entries'], 'dropped_events'));
}

function dtrLedgerReasons(object $test): int
{
    return array_reduce((new DropLedger(SpoolConfig::fromLaravel()))->snapshot()['entries'], fn (int $m, array $s) => $m | $s['reasons'], 0);
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());

    $this->scratch = sys_get_temp_dir().'/dtr-'.bin2hex(random_bytes(5));
    mkdir($this->scratch);
    $this->root = $this->scratch.'/spool';
    $this->slotDir = $this->scratch.'/slots';
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 2,
        'downloads.retry_after' => 60,
        'downloads.rejection_log' => true,
        'downloads.slot_directory' => $this->slotDir,
        'downloads.remote_connect_timeout' => 0.5,
        'downloads.remote_idle_timeout' => 0.5,
        'downloads.rejection_log_interval' => 60,
    ]);
    DropLedger::resetProcessStateForTesting();
    dtrConfigure($this);

    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'بعيدة', 'vedio' => 1, 'link' => 'x', 'linksize' => 1000000, 'downcount' => 3]);
    $this->source = null;
    $this->held = [];
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    $this->source?->stop();
    DropLedger::resetProcessStateForTesting();
    if (is_dir($this->scratch)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            @chmod($file->getPathname(), 0775);
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->scratch);
    }
});

// --- Off by default -------------------------------------------------------------------------------------------

it('is off by default: the shipped config emits nothing and creates no spool', function () {
    expect(require base_path('config/download_telemetry.php'))->toMatchArray(['events' => 'off', 'spool' => 'off']);

    dtrConfigure($this, ['events' => 'off']);
    $file = $this->scratch.'/a.mp3';
    file_put_contents($file, 'local-bytes');
    dtrLink($this, $file);

    expect(dtrGet($this)->assertOk()->streamedContent())->toBe('local-bytes')
        ->and(is_dir($this->root))->toBeFalse()
        ->and(dtrSlotFree())->toBeTrue();
});

it('events on but the spool off still emits nothing', function () {
    dtrConfigure($this, ['spool' => 'off']);
    $file = $this->scratch.'/a.mp3';
    file_put_contents($file, 'local-bytes');
    dtrLink($this, $file);

    expect(dtrGet($this)->assertOk()->streamedContent())->toBe('local-bytes')
        ->and(is_dir($this->root))->toBeFalse()
        ->and(app(DownloadTelemetry::class)->enabled())->toBeFalse();
});

// --- Lifecycle ------------------------------------------------------------------------------------------------

it('a successful remote download: accepted → started → finished(completed), one request id, nothing sensitive', function () {
    $link = dtrServe($this, [LocalHttpSource::ok('0123456789')], '/archive/lecture.mp4?token=SECRET-TOKEN');
    dtrLink($this, $link);

    $response = dtrGet($this, server: ['HTTP_COOKIE' => 'w2a_session=SECRET-COOKIE', 'HTTP_AUTHORIZATION' => 'Bearer SECRET-AUTH', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']);
    $response->assertOk()->assertHeader('Content-Length', '10');
    expect(dtrNames($this))->toBe(['download_accepted']);   // nothing about the transfer until the body runs

    [$output] = dtrSend($response);
    $events = dtrEvents($this);
    [$accepted, $started, $finished] = $events;

    expect($output)->toBe('0123456789')
        ->and(array_column($events, 'ev'))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and(array_unique(array_column($events, 'rq')))->toHaveCount(1)
        ->and(array_unique(array_column($events, 'id')))->toHaveCount(3)
        ->and($accepted)->toMatchArray(['mod' => 'khotab', 'rt' => 'khotab_item', 'rid' => $this->item, 'var' => 'primary',
            'm' => 'GET', 'head' => false, 'rng' => false, 'ip' => '127.0.0.1', 'xff' => true, 'uaf' => 'browser'])
        ->and($accepted['uah'])->toMatch('/^[0-9a-f]{32}$/')
        ->and($started)->toMatchArray(['sh' => '127.0.0.1', 'ss' => 200, 'dl' => 10])
        ->and($started['ttfb'])->toBeInt()
        ->and($finished)->toMatchArray(['st' => 'completed', 'b' => 10])
        ->and($finished['d'])->toBeInt()
        ->and(dtrSlotFree())->toBeTrue();

    $raw = implode("\n", array_map(fn ($f) => (string) file_get_contents($f), glob($this->root.'/active/*.ndjson')));
    foreach (['SECRET-TOKEN', 'SECRET-COOKIE', 'SECRET-AUTH', 'SecretBrowser', '203.0.113.9', '/archive/lecture.mp4', 'token='] as $secret) {
        expect($raw)->not->toContain($secret);
    }
    expect(array_keys($accepted))->not->toContain('ua');
});

it('a mirror download is labelled as the mirror variant with its id', function () {
    $file = $this->scratch.'/m.mp3';
    file_put_contents($file, 'mirror');
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => $file, 'linksize' => 6, 'hits' => 0]);

    $response = $this->call('GET', "/khotab-mirror-{$this->item}-{$mirror}.htm");
    expect($response->assertOk()->streamedContent())->toBe('mirror');

    $events = dtrEvents($this);
    expect(array_column($events, 'ev'))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and($events[0])->toMatchArray(['var' => 'mirror', 'vid' => $mirror, 'rid' => $this->item])
        ->and($events[1]['sh'])->toBe('local')
        ->and($events[2])->toMatchArray(['st' => 'completed', 'b' => 6]);
});

it('a truncated source: finished(incomplete) with the bytes actually sent', function () {
    dtrLink($this, dtrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 1000\r\nConnection: close\r\n\r\nonly-this"]]));

    dtrSend(dtrGet($this)->assertOk());

    expect(dtrNames($this))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and(dtrEvents($this)[2])->toMatchArray(['st' => 'incomplete', 'b' => 9])
        ->and(dtrEvents($this)[1]['dl'])->toBe(1000)
        ->and(dtrSlotFree())->toBeTrue();
});

it('a source read timeout: finished(stalled), slot freed, remote socket closed', function () {
    config(['downloads.max_concurrent' => 1]);
    dtrLink($this, dtrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 1000000\r\nConnection: close\r\n\r\nhello", 'after' => 'hold']]));

    $response = dtrGet($this)->assertOk();
    expect(dtrSlotFree())->toBeFalse();
    dtrSend($response);

    expect(dtrEvents($this)[2])->toMatchArray(['ev' => 'transfer_finished', 'st' => 'stalled', 'b' => 5])
        ->and(dtrSlotFree())->toBeTrue()
        ->and($this->source->sawClientClose())->toBeTrue();
});

it('a client disconnect: finished(client_aborted), never also completed', function () {
    app()->instance(ProxiedDownloadStreamer::class, new ProxiedDownloadStreamer(fn (): bool => true));
    $file = $this->scratch.'/big.mp3';
    file_put_contents($file, str_repeat('x', ProxiedDownloadStreamer::CHUNK_BYTES * 3));
    dtrLink($this, $file);

    [$output] = dtrSend(dtrGet($this)->assertOk());

    expect(strlen($output))->toBe(ProxiedDownloadStreamer::CHUNK_BYTES)
        ->and(dtrNames($this))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and(dtrEvents($this)[2])->toMatchArray(['st' => 'client_aborted', 'b' => ProxiedDownloadStreamer::CHUNK_BYTES])
        ->and(dtrSlotFree())->toBeTrue();
});

it('a source that cannot be reached: source_open_failed is the only terminal event — no transfer events', function () {
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);
    dtrLink($this, "http://{$address}/gone.mp4?sig=SECRET");

    dtrGet($this)->assertStatus(500);

    $events = dtrEvents($this);
    expect(array_column($events, 'ev'))->toBe(['download_accepted', 'source_open_failed'])
        ->and($events[1])->toMatchArray(['k' => 'open_failed', 'sh' => '127.0.0.1'])
        ->and(dtrSlotFree())->toBeTrue();
});

it('a rejected source (an HTML page, e.g. a YouTube mirror) is source_rejected — never a completed download', function () {
    dtrLink($this, dtrServe($this, [LocalHttpSource::ok('<html>video page</html>', 'text/html; charset=utf-8')]));

    dtrGet($this)->assertStatus(500);

    $events = dtrEvents($this);
    expect(array_column($events, 'ev'))->toBe(['download_accepted', 'source_rejected'])
        ->and($events[1])->toMatchArray(['k' => 'html', 'ss' => 200])
        ->and(dtrSlotFree())->toBeTrue();
});

it('a missing local file is a 404 recorded as source_open_failed(not_found)', function () {
    dtrLink($this, $this->scratch.'/missing.mp3');

    dtrGet($this)->assertNotFound();

    expect(dtrNames($this))->toBe(['download_accepted', 'source_open_failed'])
        ->and(dtrEvents($this)[1]['k'])->toBe('not_found')
        ->and(dtrSlotFree())->toBeTrue();
});

// --- Capacity -------------------------------------------------------------------------------------------------

it('a capacity refusal is one capacity_rejected event with the pool numbers, and the database row is still written', function () {
    config(['downloads.max_concurrent' => 1]);
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();

    dtrGet($this)->assertStatus(503)->assertHeader('Retry-After', '60');

    expect(dtrEvents($this))->toHaveCount(1)
        ->and(dtrEvents($this)[0])->toMatchArray(['ev' => 'capacity_rejected', 'st' => 'rejected', 'pa' => 1, 'pl' => 1, 'mod' => 'khotab', 'rid' => $this->item])
        ->and(DownloadRejection::query()->count())->toBe(1)
        ->and((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->value('downcount'))->toBe(3);
});

// --- HEAD -----------------------------------------------------------------------------------------------------

it('HEAD is one head_answered event — never download_accepted or a transfer — and the source is never contacted', function () {
    dtrLink($this, dtrServe($this, [LocalHttpSource::ok('never-sent')]));

    $response = dtrGet($this, 'HEAD')->assertOk();
    expect($response->streamedContent())->toBe('');

    $events = dtrEvents($this);
    expect(array_column($events, 'ev'))->toBe(['head_answered'])
        ->and($events[0])->toMatchArray(['m' => 'HEAD', 'head' => true, 'st' => 'head'])
        ->and($this->source->requests())->toBe([]);
    unset($response);
    gc_collect_cycles();
    expect(dtrSlotFree())->toBeTrue();
});

it('HEAD takes no slot, counts nothing and contacts no source — even when the pool is full', function () {
    config(['downloads.max_concurrent' => 1]);
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();   // pool full
    dtrLink($this, dtrServe($this, [LocalHttpSource::ok('never-sent')]));

    $response = dtrGet($this, 'HEAD');

    $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=lecture.mp4');
    expect($response->headers->has('Content-Length'))->toBeFalse()
        ->and((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->value('downcount'))->toBe(3)
        ->and(DownloadRejection::query()->count())->toBe(0)
        ->and($this->source->requests())->toBe([])
        ->and(dtrNames($this))->toBe(['head_answered']);
});

it('HEAD on a mirror does not increment hits; a HEAD for a missing local file is still a 404', function () {
    $file = $this->scratch.'/m.mp3';
    file_put_contents($file, 'mirror');
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => $file, 'linksize' => 6, 'hits' => 5]);

    $this->call('HEAD', "/khotab-mirror-{$this->item}-{$mirror}.htm")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=m.mp3');
    expect((int) DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->value('hits'))->toBe(5);

    dtrLink($this, $this->scratch.'/missing.mp3');
    $this->call('HEAD', "/khotab-download-{$this->item}.htm")->assertNotFound();
    expect(dtrNames($this))->toBe(['head_answered', 'source_open_failed'])
        ->and(dtrSlotFree())->toBeTrue();
});

// --- Failure isolation ----------------------------------------------------------------------------------------

it('spool full: every event is dropped and counted, and the download is byte-for-byte unaffected', function () {
    dtrConfigure($this, ['max_files' => 1]);
    $config = SpoolConfig::fromLaravel();
    @mkdir($config->quarantineDir(), 0775, true);
    file_put_contents($config->quarantineDir().'/occupied.ndjson', '');
    $file = $this->scratch.'/a.mp3';
    file_put_contents($file, 'still-delivered');
    dtrLink($this, $file);

    expect(dtrGet($this)->assertOk()->streamedContent())->toBe('still-delivered')
        ->and(dtrEvents($this))->toBe([])
        ->and(dtrLedgerDropped($this))->toBe(3)
        ->and(dtrSlotFree())->toBeTrue();
});

it('breaker open: events are shed and counted, the download is unaffected', function () {
    @mkdir($this->root, 0775, true);
    touch(SpoolConfig::fromLaravel()->breakerPath(), time() + 300);
    $file = $this->scratch.'/a.mp3';
    file_put_contents($file, 'still-delivered');
    dtrLink($this, $file);

    expect(dtrGet($this)->assertOk()->streamedContent())->toBe('still-delivered')
        ->and(dtrEvents($this))->toBe([])
        ->and(dtrLedgerDropped($this))->toBe(3)
        ->and(dtrLedgerReasons($this) & DropReason::BREAKER_OPEN)->not->toBe(0)
        ->and(dtrSlotFree())->toBeTrue();
});

it('an unusable spool AND ledger (root is a plain file): nothing recorded, no exception, the download is unaffected', function () {
    file_put_contents($this->scratch.'/not-a-dir', 'x');
    dtrConfigure($this, ['root' => $this->scratch.'/not-a-dir']);

    // the refusal path first (a streamed download releases the in-memory test database)
    config(['downloads.max_concurrent' => 1]);
    $blocker = app(DownloadCapacityGuard::class)->tryAcquire();
    dtrGet($this)->assertStatus(503);
    expect(DownloadRejection::query()->count())->toBe(1);
    $blocker->release();

    $file = $this->scratch.'/a.mp3';
    file_put_contents($file, 'still-delivered');
    dtrLink($this, $file);

    $response = dtrGet($this)->assertOk();
    [$output, $thrown] = dtrSend($response);

    expect($thrown)->toBeNull()->and($output)->toBe('still-delivered')->and(dtrSlotFree())->toBeTrue();
});

it('a recorder whose writer throws never throws to the caller', function () {
    $broken = new SpoolWriter(SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root]), fn (): float => throw new RuntimeException('clock exploded'));
    $recorder = new DownloadTelemetry($broken, true);
    $request = Request::create('/x', 'GET', server: ['HTTP_USER_AGENT' => DTR_UA]);

    $transfer = $recorder->transfer($request, new DownloadTelemetryContext('khotab', 'khotab_item', 1, 'primary'));
    $transfer->accepted();
    $transfer->started('local', null, 3, 1);
    $transfer->finished('completed');

    expect($transfer->isTerminal())->toBeTrue();
});

// --- Exactly one terminal outcome -----------------------------------------------------------------------------

it('emits at most one terminal event per transfer, whatever is called after the first', function () {
    $writer = new SpoolWriter(SpoolConfig::fromLaravel());
    $recorder = new DownloadTelemetry($writer, true);
    $request = Request::create('/x', 'GET');
    $context = new DownloadTelemetryContext('khotab', 'khotab_item', 1, 'primary');

    $a = $recorder->transfer($request, $context);
    $a->accepted();
    $a->started('local', null, 3, 0);
    $a->finished('completed');
    $a->finished('client_aborted');     // e.g. the shutdown fallback after a normal end
    $a->finished('interrupted');
    $a->sourceFailed('source_open_failed', 'x', null, null);
    $a->head();

    $b = $recorder->transfer($request, $context);
    $b->sourceFailed('source_rejected', 'html', 200, 'h');
    $b->finished('completed');

    $terminal = array_filter(dtrNames($this), fn ($ev) => $ev !== 'download_accepted' && $ev !== 'transfer_started');
    expect(array_values($terminal))->toBe(['transfer_finished', 'source_rejected'])
        ->and(dtrEvents($this)[2]['st'])->toBe('completed');
});

it('maps every streamer outcome to a telemetry outcome; no pump result is "interrupted", never "completed"', function () {
    expect(ProxiedDownloadStreamer::telemetryOutcome(ProxiedDownloadStreamer::OUTCOME_COMPLETE))->toBe('completed')
        ->and(ProxiedDownloadStreamer::telemetryOutcome(ProxiedDownloadStreamer::OUTCOME_SOURCE_INCOMPLETE))->toBe('incomplete')
        ->and(ProxiedDownloadStreamer::telemetryOutcome(ProxiedDownloadStreamer::OUTCOME_SOURCE_STALLED))->toBe('stalled')
        ->and(ProxiedDownloadStreamer::telemetryOutcome(ProxiedDownloadStreamer::OUTCOME_SOURCE_READ_FAILED))->toBe('read_failed')
        ->and(ProxiedDownloadStreamer::telemetryOutcome(ProxiedDownloadStreamer::OUTCOME_CLIENT_ABORTED))->toBe('client_aborted')
        ->and(ProxiedDownloadStreamer::telemetryOutcome(null))->toBe('interrupted');
});

// --- Concurrency ----------------------------------------------------------------------------------------------

it('two concurrent downloads keep separate request ids and each ends exactly once; both slots freed', function () {
    $second = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ثانية', 'vedio' => 1, 'link' => 'x', 'linksize' => 1, 'downcount' => 0]);
    file_put_contents($this->scratch.'/one.mp3', 'one');
    file_put_contents($this->scratch.'/two.mp3', 'twotwo');
    dtrLink($this, $this->scratch.'/one.mp3');
    dtrLink($this, $this->scratch.'/two.mp3', $second);

    $r1 = dtrGet($this)->assertOk();
    app()->forgetScopedInstances();   // a new request
    app()->forgetInstance(RequestMetrics::class);
    $r2 = dtrGet($this, id: $second)->assertOk();
    expect(dtrSlotFree())->toBeFalse();   // both slots held (limit 2)

    [$o2] = dtrSend($r2);
    [$o1] = dtrSend($r1);

    $finished = array_values(array_filter(dtrEvents($this), fn ($e) => $e['ev'] === 'transfer_finished'));
    expect([$o1, $o2])->toBe(['one', 'twotwo'])
        ->and($finished)->toHaveCount(2)
        ->and(array_column($finished, 'rid'))->toEqualCanonicalizing([$this->item, $second])
        ->and(array_column($finished, 'b'))->toEqualCanonicalizing([3, 6])
        ->and(array_unique(array_column(dtrEvents($this), 'rq')))->toHaveCount(2)
        ->and(dtrSlotFree())->toBeTrue();
});

// --- Privacy and classification -------------------------------------------------------------------------------

it('classifies the User-Agent into a coarse family only', function (string $ua, string $family) {
    expect(DownloadTelemetry::userAgentFamily($ua))->toBe($family);
})->with([
    ['', 'none'],
    ['Mozilla/5.0 (compatible; Googlebot/2.1)', 'bot'],
    ['Wget/1.21', 'tool'],
    ['Mozilla/4.0 (compatible; MSIE 8.0; IDM 6.41)', 'tool'],
    ['Mozilla/5.0 (Windows NT 10.0) Chrome/120', 'browser'],
    ['SomethingElse/1', 'other'],
]);

it('hashes the User-Agent with the app key (stable per key, not the plain sha256 of the string)', function () {
    $request = Request::create('/x', 'GET', server: ['HTTP_USER_AGENT' => DTR_UA]);
    (new DownloadTelemetry(new SpoolWriter(SpoolConfig::fromLaravel()), true))
        ->emit($request, 'download_accepted', new DownloadTelemetryContext('khotab', 'khotab_item', 1, 'primary'));

    $uah = dtrEvents($this)[0]['uah'];
    expect($uah)->toBe(substr(hash_hmac('sha256', DTR_UA, (string) config('app.key')), 0, 32))
        ->and($uah)->not->toBe(substr(hash('sha256', DTR_UA), 0, 32));
});
