<?php

/*
 * Stalled-source fix (production incident, 2026-11-10). 400/400 slots were held by
 * PHP-CGI processes whose remote sources had sent nothing for ~50 hours: the old loop
 * `while (! feof($h)) echo fread($h, 8192);` never ends when a source goes silent
 * without closing (fread() returns false per socket timeout, feof() stays false).
 *
 * Covered here, against a REAL local TCP server where the network matters:
 *  - a source that stalls mid-body is ended after the idle-read timeout; the slot is
 *    freed and the remote socket closed (the server observes the close);
 *  - a source that accepts but never answers fails within the connect timeout;
 *  - read failures, endless empty reads and stream exceptions end the transfer cleanly;
 *  - a slow but steady source is NOT cut off (no total-duration limit);
 *  - a client disconnect stops the loop and still frees everything;
 *  - the refusal warning is throttled while every refusal still gets its row.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejection;
use App\Support\Downloads\DownloadRejectionLogThrottle;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\DownloadSlot;
use App\Support\Downloads\ProxiedDownloadStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\LocalHttpSource;

uses(RefreshDatabase::class);

/** Scripted source: each read takes the next step — a string, false (stream error), '' (no data), or a Throwable. */
final class DsrScriptedStream
{
    /** @var list<string|false|Throwable|array{sleep: float, data: string}> */
    public static array $script = [];

    public static bool $repeatLast = false;

    public static int $reads = 0;

    public static bool $closed = false;

    public static bool $failOpen = false;

    public static ?bool $dbReleasedAtOpen = null;

    /** @var array<string, mixed>|null the stream context options fopen() was given */
    public static ?array $contextOptions = null;

    /** @var resource|null */
    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$contextOptions = is_resource($this->context) ? stream_context_get_options($this->context) : null;
        self::$dbReleasedAtOpen = DB::connection('main')->getRawPdo() === null;

        return ! self::$failOpen;
    }

    public function stream_read(int $count): string|false
    {
        self::$reads++;
        $step = self::$script[$this->position] ?? (self::$repeatLast ? end(self::$script) : '');
        $this->position++;

        if ($step instanceof Throwable) {
            throw $step;
        }
        if (is_array($step)) {
            usleep((int) ($step['sleep'] * 1_000_000));

            return $step['data'];
        }

        return $step;
    }

    public function stream_eof(): bool
    {
        return ! self::$repeatLast && $this->position >= count(self::$script);
    }

    public function stream_close(): void
    {
        self::$closed = true;
    }

    public function stream_stat(): array|false
    {
        return [];
    }

    public function url_stat(string $path, int $flags): array|false
    {
        return ['mode' => 0100644, 'size' => 1];
    }
}

function dsrSlotFree(): bool
{
    $slot = app(DownloadCapacityGuard::class)->tryAcquire();
    $free = $slot?->isHeld() === true;
    $slot?->release();

    return $free;
}

/** Run a streamed response's body the way Symfony would, capturing output and any exception. */
function dsrSend(object $response): array
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

/** Record every statement that changes the download counter, from now on (the in-memory DB itself is released mid-request). */
function dsrRecordIncrements(): object
{
    $log = new ArrayObject;
    DB::listen(function ($query) use ($log) {
        if (str_starts_with(strtolower($query->sql), 'update') && str_contains($query->sql, 'downcount')) {
            $log[] = $query->sql;
        }
    });

    return $log;
}

function dsrDowncount(int $id): int
{
    return (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $id)->value('downcount');
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());

    $this->scratch = sys_get_temp_dir().'/download-resilience-'.uniqid();
    mkdir($this->scratch);
    $this->slotDir = $this->scratch.'/slots';
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 1,
        'downloads.retry_after' => 60,
        'downloads.rejection_log' => true,
        'downloads.slot_directory' => $this->slotDir,
        'downloads.remote_connect_timeout' => 0.5,
        'downloads.remote_idle_timeout' => 0.5,
        'downloads.rejection_log_interval' => 60,
    ]);

    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'بعيدة', 'vedio' => 1, 'link' => 'dsr://archive/lecture.mp4', 'linksize' => 1000000, 'downcount' => 3]);

    stream_wrapper_register('dsr', DsrScriptedStream::class);
    DsrScriptedStream::$script = [];
    DsrScriptedStream::$repeatLast = false;
    DsrScriptedStream::$reads = 0;
    DsrScriptedStream::$closed = false;
    DsrScriptedStream::$contextOptions = null;
    DsrScriptedStream::$failOpen = false;
    DsrScriptedStream::$dbReleasedAtOpen = null;
    $this->source = null;
    $this->held = [];
});

afterEach(function () {
    stream_wrapper_unregister('dsr');
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    $this->source?->stop();
    foreach ([...(glob($this->slotDir.'/*') ?: []), ...(glob($this->scratch.'/*') ?: [])] as $path) {
        @unlink($path);
    }
    @rmdir($this->slotDir);
    @rmdir($this->scratch);
});

function dsrLink(object $test, string $link): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $test->item)->update(['link' => $link]);
}

// --- Real TCP sources (PHP's native http wrapper, LocalHttpSource) ---------------------------------------------

function dsrServe(object $test, array $replies): string
{
    $test->source = LocalHttpSource::start($replies);

    return $test->source->url();
}

it('a REAL remote source that stalls mid-body is ended after the idle timeout: slot freed, socket closed, counted once', function () {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 1000000\r\nConnection: close\r\n\r\nhello", 'after' => 'hold']]));
    $increments = dsrRecordIncrements();

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=lecture.mp4')
        ->assertHeader('Content-Length', '1000000');
    expect(dsrSlotFree())->toBeFalse()   // held until the transfer ends
        ->and($increments)->toHaveCount(1);   // counted once, before the transfer

    $started = microtime(true);
    [$output, $thrown] = dsrSend($response);
    $elapsed = microtime(true) - $started;

    expect($thrown)->toBeNull()
        ->and($output)->toBe('hello')   // shorter than the declared length: the visitor's client sees a failed download
        ->and($elapsed)->toBeGreaterThanOrEqual(0.45)->toBeLessThan(5.0)   // the old loop never returned
        ->and(dsrSlotFree())->toBeTrue()
        ->and($this->source->sawClientClose())->toBeTrue();   // the server saw our socket close
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_STALLED'
        && $c['bytes_sent'] === 5 && $c['declared_bytes'] === 1000000 && $c['idle_timeout_seconds'] === 0.5
        && str_starts_with($c['link'], 'http://127.0.0.1:'))->once();
});

it('a REAL remote that accepts the connection but never answers is a real 500 within the connect timeout, and frees the slot', function () {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => '', 'after' => 'hold']]));

    $started = microtime(true);
    $response = $this->get("/khotab-download-{$this->item}.htm");
    $elapsed = microtime(true) - $started;

    $response->assertStatus(500);
    expect($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and($elapsed)->toBeLessThan(5.0)
        ->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_OPEN_FAILED'
        && $c['connect_timeout_seconds'] === 0.5)->once();
});

it('a refused connection is a real 500 at once, logs one line (no ErrorException), and frees the slot', function () {
    Log::spy();
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);   // nothing listens on this port now
    dsrLink($this, "http://{$address}/gone.mp4");

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);

    expect(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_OPEN_FAILED')->once();
    Log::shouldNotHaveReceived('error');
});

it('gives remote links the configured connect timeout in their stream context — and refuses a source with no HTTP status line', function () {
    Log::spy();
    stream_wrapper_unregister('https');
    stream_wrapper_register('https', DsrScriptedStream::class);   // a userland wrapper reports no status line
    DsrScriptedStream::$script = ['data'];
    try {
        dsrLink($this, 'https://archive.example/f.mp4');
        config(['downloads.remote_connect_timeout' => 12.5]);
        $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);
    } finally {
        stream_wrapper_restore('https');
    }

    expect(DsrScriptedStream::$contextOptions)->toBe(['http' => ['timeout' => 12.5]])
        ->and(DsrScriptedStream::$dbReleasedAtOpen)->toBeTrue()   // a remote open never holds the database
        ->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_REJECTED' && $c['reason'] === 'no_http_status')->once();
});

// --- Source validation before the response is committed -------------------------------------------------------

it('an unsuccessful final status is a real 500 before any download header — slot freed, connection closed, counted once', function (string $statusLine) {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 {$statusLine}\r\nContent-Type: video/mp4\r\nContent-Length: 5\r\n\r\nerror", 'after' => 'hold']]));
    $increments = dsrRecordIncrements();

    $response = $this->get("/khotab-download-{$this->item}.htm");

    $response->assertStatus(500);
    expect($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and(dsrSlotFree())->toBeTrue()
        ->and($increments)->toHaveCount(1)   // the controller's one increment, unchanged — no double count
        ->and($this->source->sawClientClose())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => in_array($c['event'], ['DOWNLOAD_SOURCE_REJECTED', 'DOWNLOAD_SOURCE_OPEN_FAILED'], true))->once();
})->with([
    '403' => ['403 Forbidden'],
    '404' => ['404 Not Found'],
    '500' => ['500 Internal Server Error'],
    '204' => ['204 No Content'],
    '206' => ['206 Partial Content'],
    '304' => ['304 Not Modified'],
]);

it('an explicit text/html response is refused, whatever its status or parameters', function (string $type) {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Type: {$type}\r\nContent-Length: 13\r\n\r\n<h1>gone</h1>"]]));

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);

    expect(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_REJECTED'
        && $c['reason'] === 'html' && $c['status'] === 200)->once();
})->with(['text/html', 'text/html; charset=UTF-8', 'TEXT/HTML ;charset=utf-8']);

it('there is no MIME allowlist: any other content type, or none, is streamed', function (?string $type) {
    $header = $type === null ? '' : "Content-Type: {$type}\r\n";
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\n{$header}Content-Length: 4\r\n\r\nDATA"]]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Length', '4');

    expect($response->streamedContent())->toBe('DATA');
})->with(['application/octet-stream', 'text/plain', 'audio/mpeg', 'none' => [null]]);

it('validates the FINAL response after redirects, and takes its length from the final hop only', function () {
    dsrLink($this, dsrServe($this, [
        ['raw' => "HTTP/1.1 302 Found\r\nLocation: /final/lecture.mp4\r\nContent-Length: 0\r\nConnection: close\r\n\r\n"],
        LocalHttpSource::ok('FINAL-BYTES'),
    ]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()
        ->assertHeader('Content-Length', '11')
        ->assertHeader('Content-Disposition', 'attachment; filename=lecture.mp4');   // the stored link's name, as before

    expect($response->streamedContent())->toBe('FINAL-BYTES')
        ->and($this->source->requests())->toBe(['GET /archive/lecture.mp4 HTTP/1.1', 'GET /final/lecture.mp4 HTTP/1.1']);
});

it('a redirect that ends in an error is refused', function () {
    Log::spy();
    dsrLink($this, dsrServe($this, [
        ['raw' => "HTTP/1.1 301 Moved\r\nLocation: /moved.mp4\r\nContent-Length: 0\r\n\r\n"],
        ['raw' => "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\n\r\n"],
    ]));

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);

    expect(dsrSlotFree())->toBeTrue();
});

// --- Declared length ------------------------------------------------------------------------------------------

it('a chunked response is streamed whole, with no Content-Length', function () {
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n5\r\nhello\r\n6\r\n world\r\n0\r\n\r\n"]]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk();

    expect($response->headers->has('Content-Length'))->toBeFalse()->and($response->streamedContent())->toBe('hello world');
});

it('passes on Content-Length only when it is one plain integer with no transfer or content encoding', function (string $headers, ?string $expected) {
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\n{$headers}\r\n\r\nABCD"]]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk();

    expect($response->headers->get('Content-Length'))->toBe($expected);
    $response->streamedContent();
})->with([
    'plain' => ['Content-Length: 4', '4'],
    'identity encoding' => ["Content-Length: 4\r\nContent-Encoding: identity", '4'],
    'gzip encoding' => ["Content-Length: 4\r\nContent-Encoding: gzip", null],
    'two lengths' => ["Content-Length: 4\r\nContent-Length: 4", null],
    'list value' => ['Content-Length: 4, 4', null],
    'negative' => ['Content-Length: -4', null],
    'not a number' => ['Content-Length: four', null],
    'none' => ['X-Other: 1', null],
]);

it('a source that ends before its declared size is logged once as DOWNLOAD_SOURCE_INCOMPLETE, and the slot is freed', function () {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 1000\r\nConnection: close\r\n\r\nonly-this"]]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Length', '1000');
    [$output, $thrown] = dsrSend($response);

    expect($thrown)->toBeNull()->and($output)->toBe('only-this')->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_INCOMPLETE'
        && $c['bytes_sent'] === 9 && $c['declared_bytes'] === 1000)->once();
    Log::shouldHaveReceived('warning')->once();   // and nothing else
});

it('never sends more than the declared size when the source sends extra bytes', function () {
    Log::spy();
    dsrLink($this, dsrServe($this, [['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 4\r\nConnection: close\r\n\r\nDONE-AND-EXTRA-BYTES"]]));

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Length', '4');

    expect($response->streamedContent())->toBe('DONE')->and(dsrSlotFree())->toBeTrue();
    Log::shouldNotHaveReceived('warning');
});

it('local files: Content-Length is the opened file\'s real size, never the stored size, and matches the bytes sent', function () {
    $file = $this->scratch.'/local lecture.mp3';
    file_put_contents($file, str_repeat('ab', 10_000));
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['link' => $file, 'linksize' => 3]);

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()
        ->assertHeader('Content-Length', '20000')
        ->assertHeader('Content-Disposition', 'attachment; filename="local lecture.mp3"');

    expect(strlen($response->streamedContent()))->toBe(20_000);
});

it('local files: a file that grows after the headers are sent is cut at the size declared', function () {
    Log::spy();
    $file = $this->scratch.'/growing.mp3';
    file_put_contents($file, 'original');
    dsrLink($this, $file);

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Length', '8');
    file_put_contents($file, '-appended', FILE_APPEND);

    expect($response->streamedContent())->toBe('original')->and(dsrSlotFree())->toBeTrue();
    Log::shouldNotHaveReceived('warning');
});

it('local files: a file that shrinks after the headers are sent is logged once as incomplete', function () {
    Log::spy();
    $file = $this->scratch.'/shrinking.mp3';
    file_put_contents($file, 'original');
    dsrLink($this, $file);

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Length', '8');
    file_put_contents($file, 'orig');

    expect($response->streamedContent())->toBe('orig')->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_INCOMPLETE'
        && $c['declared_bytes'] === 8 && $c['bytes_sent'] === 4)->once();
});

it('a local file that cannot be opened is a real 500 before any header, and frees the slot', function () {
    DsrScriptedStream::$failOpen = true;

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);

    expect(dsrSlotFree())->toBeTrue();
});

// --- HEAD -----------------------------------------------------------------------------------------------------

it('HEAD is unchanged: the source is never contacted, no Content-Length is added, the slot is freed with the response', function () {
    dsrLink($this, dsrServe($this, [LocalHttpSource::ok('never-sent')]));

    $response = $this->call('HEAD', "/khotab-download-{$this->item}.htm");

    $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=lecture.mp4');
    expect($response->headers->has('Content-Length'))->toBeFalse()
        ->and($response->streamedContent())->toBe('')
        ->and($this->source->requests())->toBe([]);
    unset($response);
    gc_collect_cycles();
    expect(dsrSlotFree())->toBeTrue();
});

it('HEAD on a local file adds no Content-Length either', function () {
    $file = $this->scratch.'/head.mp3';
    file_put_contents($file, 'bytes');
    dsrLink($this, $file);

    $response = $this->call('HEAD', "/khotab-download-{$this->item}.htm")->assertOk();

    expect($response->headers->has('Content-Length'))->toBeFalse();
});

// --- Read results ---------------------------------------------------------------------------------------------

it('a stream read error (false, no timeout, no EOF) ends the transfer at once: slot freed, handle closed', function () {
    Log::spy();
    DsrScriptedStream::$script = ['part1', false, 'never-sent'];

    [$output, $thrown] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($thrown)->toBeNull()->and($output)->toBe('part1')
        ->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_READ_FAILED' && $c['bytes_sent'] === 5)->once();
});

it('endless empty reads are a stall: ended after the idle timeout, without spinning the CPU', function () {
    Log::spy();
    DsrScriptedStream::$script = ['part1', ''];
    DsrScriptedStream::$repeatLast = true;

    $started = microtime(true);
    [$output, $thrown] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($thrown)->toBeNull()->and($output)->toBe('part1')
        ->and(microtime(true) - $started)->toBeLessThan(3.0)
        ->and(DsrScriptedStream::$reads)->toBeLessThan(60)   // ~25 with the 20 ms back-off, not millions
        ->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_STALLED')->once();
});

it('an exception from the source ends the transfer as a read failure — not re-thrown, everything freed', function () {
    Log::spy();
    DsrScriptedStream::$script = ['part1', new RuntimeException('TLS connection reset')];

    [$output, $thrown] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($thrown)->toBeNull()->and($output)->toBe('part1')
        ->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_SOURCE_READ_FAILED'
        && $c['exception'] === RuntimeException::class && $c['exception_message'] === 'TLS connection reset')->once();
});

it('a slow but steady source is never cut off: there is no total-duration limit', function () {
    Log::spy();
    config(['downloads.remote_idle_timeout' => 0.3]);
    DsrScriptedStream::$script = array_fill(0, 8, ['sleep' => 0.15, 'data' => 'x']);   // ~1.2 s in all, 4× the idle timeout

    $started = microtime(true);
    [$output, $thrown] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($thrown)->toBeNull()->and($output)->toBe('xxxxxxxx')
        ->and(microtime(true) - $started)->toBeGreaterThan(1.0)
        ->and(dsrSlotFree())->toBeTrue();
    Log::shouldNotHaveReceived('warning');
});

it('a source that completes normally is sent in full and logs nothing', function () {
    Log::spy();
    DsrScriptedStream::$script = ['a', '', 'b', 'c'];   // an empty read mid-stream is not an error

    [$output] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($output)->toBe('abc')->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldNotHaveReceived('warning');
});

// --- Client disconnection -------------------------------------------------------------------------------------

it('stops reading as soon as the client is gone, and frees the slot and the source', function () {
    Log::spy();
    app()->instance(ProxiedDownloadStreamer::class, new ProxiedDownloadStreamer(fn (): bool => true));
    DsrScriptedStream::$script = ['part1', 'part2', 'part3'];

    [$output] = dsrSend($this->get("/khotab-download-{$this->item}.htm")->assertOk());

    expect($output)->toBe('part1')->and(DsrScriptedStream::$reads)->toBe(1)
        ->and(DsrScriptedStream::$closed)->toBeTrue()->and(dsrSlotFree())->toBeTrue();
    Log::shouldNotHaveReceived('warning');   // an ordinary cancel is not an incident
});

it('pump() reports each outcome', function () {
    $run = function (array $script, bool $repeat = false, bool $clientGone = false): string {
        DsrScriptedStream::$script = $script;
        DsrScriptedStream::$repeatLast = $repeat;
        $streamer = $clientGone ? new ProxiedDownloadStreamer(fn (): bool => true) : new ProxiedDownloadStreamer;
        $handle = fopen('dsr://x', 'rb');
        ob_start();
        try {
            return $streamer->pump($handle, 'dsr://x', 0.2);
        } finally {
            ob_end_clean();
            fclose($handle);
        }
    };

    expect($run(['a', 'b']))->toBe(ProxiedDownloadStreamer::OUTCOME_COMPLETE)
        ->and($run(['a', false, 'b']))->toBe(ProxiedDownloadStreamer::OUTCOME_SOURCE_READ_FAILED)
        ->and($run(['a', ''], repeat: true))->toBe(ProxiedDownloadStreamer::OUTCOME_SOURCE_STALLED)
        ->and($run([new LogicException('x')]))->toBe(ProxiedDownloadStreamer::OUTCOME_SOURCE_READ_FAILED)
        ->and($run(['a', 'b'], clientGone: true))->toBe(ProxiedDownloadStreamer::OUTCOME_CLIENT_ABORTED);
});

// --- Configuration --------------------------------------------------------------------------------------------

it('falls back to the default timeouts for missing, zero, negative or non-numeric values', function (mixed $value) {
    config(['downloads.remote_connect_timeout' => $value, 'downloads.remote_idle_timeout' => $value]);

    expect(ProxiedDownloadStreamer::connectTimeout())->toBe(15.0)->and(ProxiedDownloadStreamer::idleTimeout())->toBe(60.0);
})->with([null, '', 0, -5, 'abc']);

// --- Rejection log volume -------------------------------------------------------------------------------------

it('throttles the DOWNLOAD_CAPACITY_FULL warning, while EVERY refusal still gets its row', function () {
    Log::spy();
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();

    foreach (range(1, 5) as $attempt) {
        $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503)->assertHeader('Retry-After', '60');
    }

    expect(DownloadRejection::query()->count())->toBe(5)->and(dsrDowncount($this->item))->toBe(3);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_CAPACITY_FULL'
        && $c['suppressed_since_last_log'] === 0 && $c['log_interval_seconds'] === 60)->once();
});

it('interval 0 logs every refusal, as before', function () {
    Log::spy();
    config(['downloads.rejection_log_interval' => 0]);
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();

    foreach (range(1, 3) as $attempt) {
        $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503);
    }

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $c['event'] === 'DOWNLOAD_CAPACITY_FULL')->times(3);
});

it('the first warning of a new window reports how many were suppressed in the previous one', function () {
    $throttle = new DownloadRejectionLogThrottle;

    expect($throttle->admit(1000))->toBe(0)
        ->and($throttle->admit(1001))->toBeNull()
        ->and($throttle->admit(1059))->toBeNull()
        ->and($throttle->admit(1060))->toBe(2)     // window over: log, with 2 suppressed
        ->and($throttle->admit(1061))->toBeNull()
        ->and($throttle->admit(500))->toBe(1);      // a clock that went backwards opens a new window
});

it('fails toward visibility: an unusable state file means every refusal is logged', function () {
    file_put_contents($this->scratch.'/not-a-directory', 'x');
    config(['downloads.slot_directory' => $this->scratch.'/not-a-directory/slots']);
    $throttle = new DownloadRejectionLogThrottle;

    expect($throttle->admit(1000))->toBe(0)->and($throttle->admit(1001))->toBe(0);
});

it('the throttle state lives beside the slot locks and never looks like a slot', function () {
    (new DownloadRejectionLogThrottle)->admit(1000);

    expect(is_file($this->slotDir.'/'.DownloadRejectionLogThrottle::STATE_FILE))->toBeTrue()
        ->and(glob($this->slotDir.'/slot-*.lock'))->toBe([])
        ->and($this->held[] = app(DownloadCapacityGuard::class)->tryAcquire())->toBeInstanceOf(DownloadSlot::class);
});
