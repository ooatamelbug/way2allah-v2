<?php

/*
 * Global download capacity — the refusal and its telemetry (2026-10-22).
 * When the pool is full: 503 + Retry-After (configured, default 60) with the
 * Arabic message naming that interval; a structured warning in the
 * application log; one generic `download_rejections` row from data the
 * request already had — no counter, no file and no network work. A telemetry
 * or logging failure never changes the refusal. Nothing here depends on a
 * particular limit. (Ported from the earlier Khotab-only telemetry tests.)
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejection;
use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

/**
 * Stands in for PHP's http:// and https:// stream wrappers while a test
 * runs: any fopen / file_get_contents / get_headers / is_file of a remote URL
 * is recorded and fails, so "no remote connection" is observed, not assumed.
 */
final class DrRemoteProbe
{
    /** @var list<string> */
    public static array $touched = [];

    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$touched[] = $path;

        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        self::$touched[] = $path;

        return false;
    }
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());

    $this->slotDir = sys_get_temp_dir().'/download-rejection-slots-'.uniqid();
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 2,
        'downloads.retry_after' => 60,
        'downloads.rejection_log' => true,
        'downloads.slot_directory' => $this->slotDir,
    ]);

    $this->file = tempnam(sys_get_temp_dir(), 'download-dl');
    file_put_contents($this->file, 'file-bytes');
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    // A remote link: were anything to open it, the test would see the attempt.
    $this->remote = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'بعيدة', 'vedio' => 1, 'link' => 'https://archive.invalid/big.mp4', 'linksize' => 123456789, 'downcount' => 5]);
    $this->unsized = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'بلا حجم', 'vedio' => 1, 'link' => 'https://archive.invalid/x.mp4', 'linksize' => 0, 'downcount' => 1]);
    $this->local = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'محلية', 'vedio' => 1, 'link' => $this->file, 'linksize' => 10, 'downcount' => 3]);
    $this->mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->remote, 'link' => 'https://archive.invalid/m.mp4', 'linksize' => 4567, 'hits' => 2]);

    $this->held = [];
    DrRemoteProbe::$touched = [];
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    @unlink($this->file);
    foreach (glob($this->slotDir.'/*') ?: [] as $path) {
        @chmod($path, 0644);
        @unlink($path);
    }
    @rmdir($this->slotDir);
});

/** Occupy every configured slot, as other in-flight downloads would. */
function drFillSlots($test): void
{
    $guard = app(DownloadCapacityGuard::class);
    for ($i = 0; $i < DownloadCapacityGuard::limit(); $i++) {
        $test->held[] = $guard->tryAcquire();
    }
}

function drRows(): array
{
    return DownloadRejection::query()->orderBy('id')->get()->all();
}

function drCounter(string $table, int $id, string $column): int
{
    return (int) DB::connection('main')->table($table)->where('id', $id)->value($column);
}

it('below the limit: a download proceeds and is counted exactly as before, with no rejection recorded', function () {
    $response = $this->get("/khotab-download-{$this->local}.htm");

    expect(drCounter('nuke_islamic_khotab', $this->local, 'downcount'))->toBe(4);   // read before the stream disconnects `main`
    expect(drRows())->toBe([]);
    $response->assertOk();
    expect($response->streamedContent())->toBe('file-bytes');
});

it('at capacity: 503, Retry-After 60, the Arabic message with 60, no Refresh, no counter, no stream', function () {
    Http::preventStrayRequests();
    drFillSlots($this);

    $response = $this->get("/khotab-download-{$this->remote}.htm");

    $response->assertStatus(503)->assertHeader('Retry-After', '60')->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe('عذرًا، يوجد ضغط حاليًا على خدمة التحميل. يرجى المحاولة مرة أخرى بعد 60 ثانية.')
        ->and($response->headers->has('Refresh'))->toBeFalse()
        ->and($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class)
        ->and(drCounter('nuke_islamic_khotab', $this->remote, 'downcount'))->toBe(5);
});

it('makes no remote connection of any kind while refusing and recording', function () {
    Http::preventStrayRequests();
    drFillSlots($this);

    stream_wrapper_unregister('https');
    stream_wrapper_unregister('http');
    stream_wrapper_register('https', DrRemoteProbe::class);
    stream_wrapper_register('http', DrRemoteProbe::class);
    try {
        $this->get("/khotab-download-{$this->remote}.htm")->assertStatus(503);
        $this->get("/khotab-mirror-{$this->remote}-{$this->mirror}.htm")->assertStatus(503);
    } finally {
        stream_wrapper_restore('https');
        stream_wrapper_restore('http');
    }

    expect(DrRemoteProbe::$touched)->toBe([])
        ->and(drRows())->toHaveCount(2);
});

it('at capacity: records one generic row with module, resource, variant, URLs, size, client, pool state and reason', function () {
    drFillSlots($this);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->withHeaders(['User-Agent' => 'TestAgent/1.0', 'Referer' => 'https://example.test/khotab-item-1.htm', 'X-Forwarded-For' => '6.6.6.6', 'Cookie' => 'secret=1', 'Authorization' => 'Bearer secret'])
        ->get("/khotab-download-{$this->remote}.htm?src=home")->assertStatus(503);

    $rows = drRows();
    expect($rows)->toHaveCount(1);
    $row = $rows[0];
    expect($row->reason)->toBe('DOWNLOAD_CAPACITY_FULL')
        ->and($row->pool)->toBe('global')
        ->and($row->module)->toBe('khotab')
        ->and($row->resource_type)->toBe('khotab_item')
        ->and($row->resource_id)->toBe($this->remote)
        ->and($row->variant)->toBe('primary')
        ->and($row->variant_id)->toBeNull()
        ->and($row->requested_url)->toBe('http://localhost/khotab-download-'.$this->remote.'.htm?src=home')
        ->and($row->resolved_download_url)->toBe('https://archive.invalid/big.mp4')
        ->and($row->file_size)->toBe(123456789)->and($row->file_size_source)->toBe('db')
        // no trusted proxy is configured: the resolved IP is the peer; the forged header is only a diagnostic
        ->and($row->client_ip)->toBe('10.1.2.3')->and($row->forwarded_for)->toBe('6.6.6.6')
        ->and($row->user_agent)->toBe('TestAgent/1.0')->and($row->referer)->toBe('https://example.test/khotab-item-1.htm')
        ->and($row->user_id)->toBeNull()->and($row->admin_id)->toBeNull()->and($row->is_contextual_admin)->toBeFalse()
        ->and($row->active_downloads)->toBe(2)->and($row->download_limit)->toBe(2)
        ->and($row->retry_after_seconds)->toBe(60)
        ->and($row->request_id)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($row->created_at)->toBeGreaterThan(time() - 60);
    // Cookies and authorization headers are not stored anywhere in the row.
    expect(json_encode($row->getAttributes()))->not->toContain('secret');
});

it('records an unknown size as NULL / unknown, without any remote lookup', function () {
    Http::preventStrayRequests();
    drFillSlots($this);

    $this->get("/khotab-download-{$this->unsized}.htm")->assertStatus(503);

    $row = drRows()[0];
    expect($row->file_size)->toBeNull()->and($row->file_size_source)->toBe('unknown');
});

it('records a refused mirror download against its parent item, as the mirror variant with its stored size', function () {
    drFillSlots($this);

    $this->get("/khotab-mirror-{$this->remote}-{$this->mirror}.htm")->assertStatus(503)->assertHeader('Retry-After', '60');

    $row = drRows()[0];
    expect($row->module)->toBe('khotab')->and($row->resource_type)->toBe('khotab_item')
        ->and($row->resource_id)->toBe($this->remote)
        ->and($row->variant)->toBe('mirror')->and($row->variant_id)->toBe($this->mirror)
        ->and($row->resolved_download_url)->toBe('https://archive.invalid/m.mp4')
        ->and($row->file_size)->toBe(4567)
        ->and(drCounter('nuke_islamic_mirror', $this->mirror, 'hits'))->toBe(2);
});

it('records a contextual admin who was refused', function () {
    $admin = H::superAdmin();
    drFillSlots($this);

    $this->withSession(H::session($admin))->get("/khotab-download-{$this->remote}.htm")->assertStatus(503);

    $row = drRows()[0];
    expect($row->admin_id)->toBe((int) $admin->getKey())->and($row->is_contextual_admin)->toBeTrue();
});

it('writes the structured DOWNLOAD_CAPACITY_FULL warning to the application log, without headers or cookies', function () {
    Log::spy();
    drFillSlots($this);

    $this->withHeaders(['User-Agent' => 'TestAgent/1.0'])->get("/khotab-mirror-{$this->remote}-{$this->mirror}.htm")->assertStatus(503);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        return $context['event'] === 'DOWNLOAD_CAPACITY_FULL'
            && $context['pool'] === 'global' && $context['module'] === 'khotab'
            && $context['resource_type'] === 'khotab_item' && $context['resource_id'] === $this->remote
            && $context['variant'] === 'mirror' && $context['variant_id'] === $this->mirror
            && $context['active_downloads'] === 2 && $context['download_limit'] === 2
            && $context['retry_after_seconds'] === 60 && $context['file_size'] === 4567
            && $context['client_ip'] === '127.0.0.1' && is_string($context['request_id'])
            && ! array_key_exists('user_agent', $context) && ! array_key_exists('cookie', $context);
    })->once();
});

it('still refuses with 503 + Retry-After, and logs the failure, when the telemetry row cannot be stored', function () {
    Log::spy();
    Schema::connection('main')->drop(DownloadRejectionRecorder::TABLE);
    drFillSlots($this);

    $response = $this->get("/khotab-download-{$this->remote}.htm");

    $response->assertStatus(503)->assertHeader('Retry-After', '60');
    expect($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class)
        ->and(drCounter('nuke_islamic_khotab', $this->remote, 'downcount'))->toBe(5);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $context['event'] === 'DOWNLOAD_REJECTION_TELEMETRY_FAILED'
        && $context['module'] === 'khotab' && $context['resource_id'] === $this->remote
        && ! array_key_exists('message', $context))->once();
});

it('still refuses with 503 + Retry-After when the application log itself is broken, and when the row fails too', function () {
    $log = Log::partialMock();
    $log->shouldReceive('warning')->andThrow(new RuntimeException('log channel down'));
    $log->shouldReceive('error')->andThrow(new RuntimeException('log channel down'));
    drFillSlots($this);

    $this->get("/khotab-download-{$this->remote}.htm")->assertStatus(503)->assertHeader('Retry-After', '60');
    expect(drRows())->toHaveCount(1);

    Schema::connection('main')->drop(DownloadRejectionRecorder::TABLE);
    $response = $this->get("/khotab-mirror-{$this->remote}-{$this->mirror}.htm");

    $response->assertStatus(503)->assertHeader('Retry-After', '60');
    expect($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class)
        ->and(drCounter('nuke_islamic_mirror', $this->mirror, 'hits'))->toBe(2);
});

it('uses whatever limit and retry interval are configured', function () {
    config(['downloads.max_concurrent' => 3, 'downloads.retry_after' => 45]);
    drFillSlots($this);
    expect($this->held)->toHaveCount(3)->and(collect($this->held)->every(fn ($slot) => $slot?->isHeld()))->toBeTrue();

    $response = $this->get("/khotab-download-{$this->remote}.htm");

    $response->assertStatus(503)->assertHeader('Retry-After', '45');
    expect($response->getContent())->toBe(DownloadCapacityGuard::message(45))->toContain('بعد 45 ثانية');
    $row = drRows()[0];
    expect($row->download_limit)->toBe(3)->and($row->active_downloads)->toBe(3)->and($row->retry_after_seconds)->toBe(45);
});

it('reports as active only the slots actually held by other downloads', function () {
    config(['downloads.max_concurrent' => 3]);
    @mkdir($this->slotDir, 0775, true);
    // One slot file that this process cannot open (permissions) …
    touch($this->slotDir.'/slot-002.lock');
    chmod($this->slotDir.'/slot-002.lock', 0000);
    if (is_writable($this->slotDir.'/slot-002.lock')) {
        $this->markTestSkipped('running as a user that ignores file permissions');
    }
    // … and the other two held by in-flight downloads.
    $guard = app(DownloadCapacityGuard::class);
    $this->held[] = $guard->tryAcquire();
    $this->held[] = $guard->tryAcquire();

    $this->get("/khotab-download-{$this->remote}.htm")->assertStatus(503);

    $row = drRows()[0];
    expect($row->active_downloads)->toBe(2)->and($row->download_limit)->toBe(3);
});

it('keeps only the warning when the telemetry table is switched off', function () {
    config(['downloads.rejection_log' => false]);
    drFillSlots($this);

    $this->get("/khotab-download-{$this->remote}.htm")->assertStatus(503);

    expect(drRows())->toBe([]);
});
