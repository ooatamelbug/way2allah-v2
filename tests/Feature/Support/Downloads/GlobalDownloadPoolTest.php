<?php

/*
 * The download-capacity pool is GLOBAL by architecture, not by Khotab naming:
 * any caller of DownloadCapacityGuard + ProxiedDownloadStreamer — here a
 * fake second module registered only for the test — competes for the same
 * slots as the Khotab endpoints; the slot is held for the whole transfer and
 * released when it ends or fails; the slot directory and file names are the
 * earlier Khotab guard's, so both guards share one pool during the
 * transition; the generic settings fall back to the KHOTAB_DOWNLOAD_* ones;
 * and redirect-only downloads never take a slot.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejection;
use App\Support\Downloads\DownloadRejectionContext;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\DownloadSlot;
use App\Support\Downloads\ProxiedDownloadStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

/**
 * A local "file" served through a stream wrapper, so the test can look at the
 * pool WHILE the transfer is running (from inside its reads), and can make a
 * transfer fail half-way.
 */
final class GdpProbeStream
{
    /** @var list<DownloadSlot|null> what tryAcquire() returned during reads */
    public static array $duringTransfer = [];

    public static bool $failMidway = false;

    /** @var resource|null */
    public $context;

    private int $reads = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string|false
    {
        $this->reads++;
        $probe = app(DownloadCapacityGuard::class)->tryAcquire();
        self::$duringTransfer[] = $probe;
        $probe?->release();

        if (self::$failMidway && $this->reads === 2) {
            throw new RuntimeException('remote transfer broke');
        }

        return $this->reads <= 2 ? "chunk{$this->reads}" : '';
    }

    public function stream_eof(): bool
    {
        return $this->reads >= 3;
    }

    public function stream_stat(): array|false
    {
        return [];
    }

    public function url_stat(string $path, int $flags): array|false
    {
        return ['mode' => 0100644, 'size' => 12];
    }
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());

    $this->slotDir = sys_get_temp_dir().'/download-pool-'.uniqid();
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 1,
        'downloads.retry_after' => 60,
        'downloads.rejection_log' => true,
        'downloads.slot_directory' => $this->slotDir,
    ]);

    $this->file = tempnam(sys_get_temp_dir(), 'download-pool');
    file_put_contents($this->file, 'file-bytes');
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'محلية', 'vedio' => 1, 'link' => $this->file, 'linksize' => 10, 'downcount' => 3, 'pdf' => 2]);
    $this->mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => $this->file, 'linksize' => 10, 'hits' => 4]);

    // A second, non-Khotab module that proxies a file — the shape any future
    // long-lived download must take.
    Route::get('/__test/fake-proxied-{id}.bin', function (int $id, Request $request, DownloadCapacityGuard $guard, ProxiedDownloadStreamer $streamer) {
        $link = test()->fakeLink ?? test()->file;
        $slot = $guard->tryAcquire();
        if ($slot === null) {
            return $guard->refuse($request, new DownloadRejectionContext(
                module: 'fake', resourceType: 'fake_file', resourceId: $id, variant: 'primary',
                resolvedDownloadUrl: $link, storedSize: 10,
            ));
        }

        return $streamer->stream($slot, $link);
    });

    stream_wrapper_register('gdpprobe', GdpProbeStream::class);
    GdpProbeStream::$duringTransfer = [];
    GdpProbeStream::$failMidway = false;
    $this->fakeLink = null;
    $this->held = [];
});

afterEach(function () {
    stream_wrapper_unregister('gdpprobe');
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    @unlink($this->file);
    foreach (glob($this->slotDir.'/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($this->slotDir);
});

function gdpFreeSlot(): bool
{
    $slot = app(DownloadCapacityGuard::class)->tryAcquire();
    $free = $slot?->isHeld() === true;
    $slot?->release();

    return $free;
}

function gdpValue(string $table, int $id, string $column): int
{
    return (int) DB::connection('main')->table($table)->where('id', $id)->value($column);
}

/** Load config/downloads.php afresh with exactly the given environment variables set. */
function gdpConfigWithEnv(array $vars): array
{
    $names = ['DOWNLOAD_CAPACITY_GUARD', 'DOWNLOAD_CAPACITY_MAX_CONCURRENT', 'DOWNLOAD_CAPACITY_RETRY_AFTER', 'DOWNLOAD_REJECTION_LOG', 'DOWNLOAD_CAPACITY_SLOT_DIRECTORY',
        'KHOTAB_DOWNLOAD_CONCURRENCY_GUARD', 'KHOTAB_DOWNLOAD_MAX_CONCURRENT', 'KHOTAB_DOWNLOAD_RETRY_AFTER', 'KHOTAB_DOWNLOAD_REJECTION_LOG', 'KHOTAB_DOWNLOAD_SLOT_DIRECTORY'];
    $set = function (string $name, ?string $value): void {
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        } else {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    };

    try {
        foreach ($names as $name) {
            $set($name, $vars[$name] ?? null);
        }

        return require config_path('downloads.php');
    } finally {
        foreach ($names as $name) {
            $set($name, null);
        }
    }
}

it('one pool across modules: a fake second caller in flight blocks both Khotab endpoints, and is recorded as such', function () {
    $fake = $this->get('/__test/fake-proxied-99.bin')->assertOk();   // slot taken, transfer not yet sent

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503)->assertHeader('Retry-After', '60');
    $this->get("/khotab-mirror-{$this->item}-{$this->mirror}.htm")->assertStatus(503);
    expect(gdpValue('nuke_islamic_khotab', $this->item, 'downcount'))->toBe(3)
        ->and(gdpValue('nuke_islamic_mirror', $this->mirror, 'hits'))->toBe(4);

    $rows = DownloadRejection::query()->orderBy('id')->get();
    expect($rows->pluck('variant')->all())->toBe(['primary', 'mirror'])
        ->and($rows->pluck('module')->unique()->all())->toBe(['khotab'])
        ->and($rows->every(fn ($row) => $row->active_downloads === 1 && $row->download_limit === 1))->toBeTrue();

    expect($fake->streamedContent())->toBe('file-bytes');
    expect(gdpFreeSlot())->toBeTrue();   // the fake transfer ended → its slot is free again
});

it('one pool across modules: a Khotab transfer in flight blocks the fake caller, which records its own module', function () {
    $khotab = $this->get("/khotab-download-{$this->item}.htm")->assertOk();
    expect(gdpValue('nuke_islamic_khotab', $this->item, 'downcount'))->toBe(4);

    $this->get('/__test/fake-proxied-99.bin')->assertStatus(503)->assertHeader('Retry-After', '60');

    $row = DownloadRejection::query()->sole();
    expect($row->module)->toBe('fake')->and($row->resource_type)->toBe('fake_file')->and($row->resource_id)->toBe(99)
        ->and($row->variant)->toBe('primary')->and($row->variant_id)->toBeNull()
        ->and($row->file_size)->toBe(10)->and($row->file_size_source)->toBe('db')
        ->and($row->active_downloads)->toBe(1)->and($row->download_limit)->toBe(1);

    expect($khotab->streamedContent())->toBe('file-bytes');
});

it('never lets the callers together exceed the global limit', function () {
    config(['downloads.max_concurrent' => 3]);

    $inFlight = [
        $this->get('/__test/fake-proxied-1.bin')->assertOk(),
        $this->get("/khotab-download-{$this->item}.htm")->assertOk(),
        $this->get("/khotab-mirror-{$this->item}-{$this->mirror}.htm")->assertOk(),
    ];

    $this->get('/__test/fake-proxied-2.bin')->assertStatus(503);
    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503);
    expect(count(glob($this->slotDir.'/slot-*.lock')))->toBe(3);

    foreach ($inFlight as $response) {
        $response->streamedContent();
    }
});

it('holds the slot WHILE the bytes are moving (Khotab endpoint), and frees it when the transfer completes', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['link' => 'gdpprobe://archive/file.mp4']);

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk();

    expect($response->streamedContent())->toBe('chunk1chunk2')
        ->and(GdpProbeStream::$duringTransfer)->not->toBeEmpty()
        ->and(collect(GdpProbeStream::$duringTransfer)->every(fn ($probe) => $probe === null))->toBeTrue()  // full while transferring
        ->and(gdpFreeSlot())->toBeTrue();                                                                    // free once done
});

it('releases the slot when the transfer fails half-way', function () {
    GdpProbeStream::$failMidway = true;
    $this->fakeLink = 'gdpprobe://archive/broken.mp4';

    $response = $this->get('/__test/fake-proxied-5.bin')->assertOk();

    ob_start();
    try {
        $response->baseResponse->sendContent();
        $thrown = null;
    } catch (RuntimeException $e) {
        $thrown = $e;
    } finally {
        ob_end_clean();
    }

    expect($thrown?->getMessage())->toBe('remote transfer broke')
        ->and(GdpProbeStream::$duringTransfer[0])->toBeNull()   // was held mid-transfer
        ->and(gdpFreeSlot())->toBeTrue();                        // and released by the failure
});

it('shares slots with the earlier Khotab guard: same directory default, same slot-NNN.lock files', function () {
    expect(gdpConfigWithEnv([])['slot_directory'])->toBe(storage_path('app/download-concurrency/khotab'));

    // A download still running under the earlier guard holds slot-001.lock in
    // the shared directory — exactly the file name that guard used.
    @mkdir($this->slotDir, 0775, true);
    $legacyHandle = fopen(sprintf('%s/slot-%03d.lock', $this->slotDir, 1), 'c');
    expect(flock($legacyHandle, LOCK_EX | LOCK_NB))->toBeTrue();

    try {
        $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503);
        expect(DownloadRejection::query()->sole()->active_downloads)->toBe(1);
    } finally {
        flock($legacyHandle, LOCK_UN);
        fclose($legacyHandle);
    }

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk();
    $response->streamedContent();
});

it('falls back to the KHOTAB_DOWNLOAD_* variables, prefers the generic ones, and treats an empty value as unset', function () {
    $defaults = gdpConfigWithEnv([]);
    expect($defaults)->toBe([
        'guard' => false,
        'max_concurrent' => 100,
        'retry_after' => 60,
        'rejection_log' => true,
        'slot_directory' => storage_path('app/download-concurrency/khotab'),
    ]);

    $legacyOnly = gdpConfigWithEnv([
        'KHOTAB_DOWNLOAD_CONCURRENCY_GUARD' => 'true',
        'KHOTAB_DOWNLOAD_MAX_CONCURRENT' => '100',
        'KHOTAB_DOWNLOAD_RETRY_AFTER' => '30',
        'KHOTAB_DOWNLOAD_REJECTION_LOG' => 'false',
        'KHOTAB_DOWNLOAD_SLOT_DIRECTORY' => '/srv/slots/khotab',
    ]);
    expect($legacyOnly)->toBe([
        'guard' => true, 'max_concurrent' => 100, 'retry_after' => 30, 'rejection_log' => false, 'slot_directory' => '/srv/slots/khotab',
    ]);

    $both = gdpConfigWithEnv([
        'KHOTAB_DOWNLOAD_CONCURRENCY_GUARD' => 'true', 'DOWNLOAD_CAPACITY_GUARD' => 'false',
        'KHOTAB_DOWNLOAD_MAX_CONCURRENT' => '100', 'DOWNLOAD_CAPACITY_MAX_CONCURRENT' => '400',
        'KHOTAB_DOWNLOAD_RETRY_AFTER' => '30', 'DOWNLOAD_CAPACITY_RETRY_AFTER' => '60',
        'KHOTAB_DOWNLOAD_REJECTION_LOG' => 'false', 'DOWNLOAD_REJECTION_LOG' => 'true',
        'KHOTAB_DOWNLOAD_SLOT_DIRECTORY' => '/srv/slots/khotab', 'DOWNLOAD_CAPACITY_SLOT_DIRECTORY' => '/srv/slots/other',
    ]);
    expect($both)->toBe([
        'guard' => false, 'max_concurrent' => 400, 'retry_after' => 60, 'rejection_log' => true, 'slot_directory' => '/srv/slots/other',
    ]);

    $emptyGeneric = gdpConfigWithEnv([
        'KHOTAB_DOWNLOAD_CONCURRENCY_GUARD' => 'true', 'DOWNLOAD_CAPACITY_GUARD' => '',
        'KHOTAB_DOWNLOAD_MAX_CONCURRENT' => '400', 'DOWNLOAD_CAPACITY_MAX_CONCURRENT' => '',
        'KHOTAB_DOWNLOAD_SLOT_DIRECTORY' => '/srv/slots/khotab', 'DOWNLOAD_CAPACITY_SLOT_DIRECTORY' => '',
    ]);
    expect($emptyGeneric['guard'])->toBeTrue()->and($emptyGeneric['max_concurrent'])->toBe(400)
        ->and($emptyGeneric['slot_directory'])->toBe('/srv/slots/khotab');
});

it('redirect-only downloads never take a slot: they still redirect and count while the pool is full', function () {
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();   // the pool (1) is full
    expect($this->held[0]?->isHeld())->toBeTrue();

    $anasheed = H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'link' => 'http://archive.invalid/n.mp3', 'downcount' => 1]);
    $anasheedMirror = H::insert('nuke_anasheed_mirror', ['khid' => $anasheed, 'link' => 'http://archive.invalid/nm.mp3', 'hits' => 1]);
    $telawah = H::insert('nuke_telawah_telawah', ['title' => 'تلاوة', 'link' => 'https://archive.invalid/t.mp3', 'downcount' => 1]);

    $this->get("/var-download-{$anasheed}.htm")->assertRedirect('https://archive.invalid/n.mp3');
    $this->get("/var-mirror-{$anasheed}-{$anasheedMirror}.htm")->assertRedirect('https://archive.invalid/nm.mp3');
    $this->get("/recite-download-{$telawah}.htm")->assertRedirect('https://archive.invalid/t.mp3');
    $this->get("/khotab-item-pdf-{$this->item}.htm")->assertRedirect();
    $this->get("/lesson-download-{$this->item}.htm")->assertRedirect($this->file);

    expect(gdpValue('nuke_anasheed_anasheed', $anasheed, 'downcount'))->toBe(2)
        ->and(gdpValue('nuke_anasheed_mirror', $anasheedMirror, 'hits'))->toBe(2)
        ->and(gdpValue('nuke_telawah_telawah', $telawah, 'downcount'))->toBe(2)
        ->and(gdpValue('nuke_islamic_khotab', $this->item, 'pdf'))->toBe(3)
        ->and(DownloadRejection::query()->count())->toBe(0)
        ->and(count(glob($this->slotDir.'/slot-*.lock')))->toBe(1);   // no other slot was ever touched
});
