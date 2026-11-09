<?php

/*
 * The global download-capacity pool (DownloadCapacityGuard), exercised
 * through its current protected endpoints: /khotab-download-* and
 * /khotab-mirror-*. One flock-based slot pool; when it is full a request is
 * refused at once with 503 + Retry-After and is not counted. No real remote
 * download happens here: the links are local temp files, which
 * ProxiedDownloadStreamer serves through the same code path.
 * (Ported from the earlier Khotab-only guard's tests.)
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadSlot;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * (Re)create the in-memory `main` fixture. A completed stream runs the
 * streamer's DB::disconnect(), which destroys an SQLite :memory: database
 * (see KhotabItemControllerTest's note) — so counters are read BEFORE a
 * stream is triggered, and a test that downloads again re-seeds.
 */
function dcgSeed(string $file): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'درس', 'vedio' => 1, 'hidden' => 0, 'link' => $file, 'downcount' => 5]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 10, 'khid' => 1, 'link' => $file, 'hits' => 7]);
}

beforeEach(function () {
    $this->slotDir = sys_get_temp_dir().'/download-slots-'.uniqid();
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 2,
        'downloads.retry_after' => 30,
        'downloads.rejection_log' => false,
        'downloads.slot_directory' => $this->slotDir,
    ]);

    $this->file = tempnam(sys_get_temp_dir(), 'download-guard');
    file_put_contents($this->file, 'file-bytes');
    dcgSeed($this->file);

    $this->held = [];
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot->release();
    }
    @unlink($this->file);
    foreach (glob($this->slotDir.'/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($this->slotDir);
});

/** Occupy slots from this test, as other in-flight downloads would. */
function dcgOccupy($test, int $count): void
{
    $guard = app(DownloadCapacityGuard::class);
    for ($i = 0; $i < $count; $i++) {
        $slot = $guard->tryAcquire();
        expect($slot?->isHeld())->toBeTrue();
        $test->held[] = $slot;
    }
}

function dcgDowncount(): int
{
    return (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('downcount');
}

function dcgMirrorHits(): int
{
    return (int) DB::connection('main')->table('nuke_islamic_mirror')->where('id', 10)->value('hits');
}

it('guard disabled: downloads exactly as before, even when every slot is taken', function () {
    dcgOccupy($this, 2);
    config(['downloads.guard' => false]);

    $response = $this->get('/khotab-download-1.htm')->assertOk();
    expect(dcgDowncount())->toBe(6)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain(basename($this->file))
        ->and($response->streamedContent())->toBe('file-bytes');

    dcgSeed($this->file);
    $mirror = $this->get('/khotab-mirror-1-10.htm')->assertOk();
    expect(dcgMirrorHits())->toBe(8)->and($mirror->streamedContent())->toBe('file-bytes');
    expect(is_dir($this->slotDir) ? count(glob($this->slotDir.'/slot-*.lock')) : 0)->toBe(2); // only the test's own two
});

it('slot available: the download proceeds, counted as before', function () {
    $response = $this->get('/khotab-download-1.htm')->assertOk();

    expect(dcgDowncount())->toBe(6)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->streamedContent())->toBe('file-bytes');
});

it('all slots occupied: the primary download is refused at once with 503 + Retry-After, and not counted', function () {
    dcgOccupy($this, 2);

    $started = microtime(true);
    $response = $this->get('/khotab-download-1.htm');
    $elapsed = microtime(true) - $started;

    $response->assertStatus(503)->assertHeader('Retry-After', '30');
    expect($response->getContent())->toBe(DownloadCapacityGuard::message(30))
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->has('Refresh'))->toBeFalse()
        ->and(dcgDowncount())->toBe(5)
        ->and($elapsed)->toBeLessThan(1.0); // no waiting for a slot
});

it('all slots occupied: the mirror download is refused with 503 and its hits are not incremented', function () {
    dcgOccupy($this, 2);

    $this->get('/khotab-mirror-1-10.htm')->assertStatus(503)->assertHeader('Retry-After', '30');

    expect(dcgMirrorHits())->toBe(7)->and(dcgDowncount())->toBe(5);
});

it('primary and mirror downloads share ONE pool', function () {
    config(['downloads.max_concurrent' => 1]);

    // A primary download is in flight: the controller has taken the slot, the transfer has not finished.
    $primary = $this->get('/khotab-download-1.htm')->assertOk();
    $this->get('/khotab-mirror-1-10.htm')->assertStatus(503);
    expect(dcgDowncount())->toBe(6)->and(dcgMirrorHits())->toBe(7);
    $primary->streamedContent();

    // And the other way round.
    dcgSeed($this->file);
    $mirror = $this->get('/khotab-mirror-1-10.htm')->assertOk();
    $this->get('/khotab-download-1.htm')->assertStatus(503);
    expect(dcgMirrorHits())->toBe(8)->and(dcgDowncount())->toBe(5);
    $mirror->streamedContent();
});

it('holds the slot for the whole transfer and frees it when the transfer ends', function () {
    config(['downloads.max_concurrent' => 1]);

    $response = $this->get('/khotab-download-1.htm')->assertOk();
    expect(app(DownloadCapacityGuard::class)->tryAcquire())->toBeNull(); // still transferring

    expect($response->streamedContent())->toBe('file-bytes');

    $after = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($after?->isHeld())->toBeTrue();
    $after->release();
});

it('a released slot can be used again, repeatedly', function () {
    config(['downloads.max_concurrent' => 1]);

    foreach (range(1, 3) as $round) {
        dcgSeed($this->file);
        $response = $this->get('/khotab-download-1.htm')->assertOk();
        expect(dcgDowncount())->toBe(6)->and($response->streamedContent())->toBe('file-bytes');
    }
});

it('frees the slot when the request 404s before streaming', function () {
    config(['downloads.max_concurrent' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->update(['link' => '']);

    $this->get('/khotab-download-1.htm')->assertNotFound();

    $slot = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($slot?->isHeld())->toBeTrue();
    $slot->release();
});

it('stale slot files do not mean occupied slots — only a held lock does', function () {
    @mkdir($this->slotDir, 0775, true);
    foreach ([1, 2] as $n) {
        file_put_contents(sprintf('%s/slot-%03d.lock', $this->slotDir, $n), 'left over');
    }

    $first = $this->get('/khotab-download-1.htm')->assertOk();
    $second = $this->get('/khotab-mirror-1-10.htm')->assertOk(); // both slots in use at once, despite the stale files
    $this->get('/khotab-download-1.htm')->assertStatus(503);
    expect($first->streamedContent())->toBe('file-bytes')->and($second->streamedContent())->toBe('file-bytes');
});

it('respects the configured limit', function () {
    config(['downloads.max_concurrent' => 3]);

    dcgOccupy($this, 2);
    $third = $this->get('/khotab-download-1.htm')->assertOk(); // the 3rd slot, held until the response is sent

    $this->get('/khotab-download-1.htm')->assertStatus(503); // a 4th is refused
    expect($third->streamedContent())->toBe('file-bytes');
    expect(count(glob($this->slotDir.'/slot-*.lock')))->toBe(3);
});

it('the OS releases a slot whose process was killed mid-download', function () {
    config(['downloads.max_concurrent' => 1]);
    @mkdir($this->slotDir, 0775, true);
    $slotFile = $this->slotDir.'/slot-001.lock';
    $ready = $this->slotDir.'/child-ready';

    // A separate PHP process takes the slot's lock, signals, then hangs (as a stuck transfer would).
    $code = '$h = fopen('.var_export($slotFile, true).', "c"); flock($h, LOCK_EX); touch('.var_export($ready, true).'); sleep(60);';
    $process = proc_open([PHP_BINARY, '-r', $code], [], $pipes);
    for ($i = 0; $i < 100 && ! file_exists($ready); $i++) {
        usleep(50_000);
    }
    expect(file_exists($ready))->toBeTrue();

    $this->get('/khotab-download-1.htm')->assertStatus(503);

    proc_terminate($process, 9); // SIGKILL — no cleanup code runs
    proc_close($process);

    $response = $this->get('/khotab-download-1.htm')->assertOk();
    expect(dcgDowncount())->toBe(6)->and($response->streamedContent())->toBe('file-bytes');
});

it('fails open (downloads proceed, warning logged) if the slot directory cannot be used', function () {
    config(['downloads.slot_directory' => '/proc/download-guard-cannot-create-this']);

    $response = $this->get('/khotab-download-1.htm')->assertOk();
    expect(dcgDowncount())->toBe(6)->and($response->streamedContent())->toBe('file-bytes');
});

it('an unguarded slot holds and releases nothing', function () {
    $slot = DownloadSlot::unguarded();
    expect($slot->isHeld())->toBeFalse();
    $slot->release();
    expect($slot->isHeld())->toBeFalse();
});
