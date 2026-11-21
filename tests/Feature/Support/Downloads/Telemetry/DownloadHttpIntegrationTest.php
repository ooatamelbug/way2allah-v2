<?php

/*
 * Phase 3B — Khotab proxied downloads through a REAL web-server execution path: PHP's built-in server (`php -S`)
 * running the real HTTP kernel (tests/Support/Telemetry/http-front.php), a real upstream (LocalHttpSource) and a real
 * client socket. Covers: a slow first byte then success, a slow (dripping) upstream with the CLIENT disconnecting
 * mid-transfer, a source read timeout, and a broken telemetry spool not changing the HTTP response.
 *
 * IMPORTANT: this is PHP's CLI development server, NOT PHP-CGI/FastCGI behind Apache as in production. Output
 * buffering, how a client disconnect is noticed, and when shutdown functions run differ between SAPIs. These results
 * do not establish production equivalence (see docs/operations/download-telemetry-recorder.md, "Validation gap").
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\LocalHttpSource;

function dhiWriteConfig(object $test, array $telemetry = []): void
{
    $sqlite = ['driver' => 'sqlite', 'database' => $test->db, 'prefix' => '', 'foreign_key_constraints' => false];
    file_put_contents($test->cfgFile, json_encode([
        'base' => base_path(),
        'env' => [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $test->db, 'DB_URL' => '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single',
            'BROADCAST_CONNECTION' => 'null', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
        ],
        'config' => [
            'database.connections.main' => $sqlite,
            'database.connections.sqlite' => $sqlite,
            'logging.channels.single.path' => $test->scratch.'/laravel.log',
            'downloads.guard' => true,
            'downloads.max_concurrent' => 1,
            'downloads.slot_directory' => $test->slotDir,
            'downloads.remote_connect_timeout' => 3.0,
            'downloads.remote_idle_timeout' => 1.0,
            'downloads.rejection_log' => true,
            'download_telemetry' => $telemetry + [
                'events' => 'on', 'spool' => 'on', 'root' => $test->root, 'file_max_bytes' => 1024 * 1024, 'max_files' => 10,
                'disk_reserve_bytes' => 0, 'lock_budget_us' => 200_000, 'ledger_lock_budget_us' => 200_000, 'ledger_slots' => 60,
                'ledger_shards' => 2, 'slow_write_us' => 60_000_000, 'breaker_fault_threshold' => 50, 'breaker_open_seconds' => 300,
            ],
        ],
    ], JSON_THROW_ON_ERROR));
}

/** @return array{status: string, headers: array<string, string>, body: string} */
function dhiRequest(object $test, string $path, ?int $readBytesThenClose = null, float $timeout = 20.0): array
{
    $socket = stream_socket_client('tcp://127.0.0.1:'.$test->port, $errno, $errstr, 5);
    stream_set_timeout($socket, (int) $timeout);
    fwrite($socket, "GET {$path} HTTP/1.0\r\nHost: 127.0.0.1\r\nUser-Agent: Mozilla/5.0 IntegrationTest\r\n\r\n");
    $raw = '';
    while (! feof($socket)) {
        $chunk = fread($socket, 8192);
        if ($chunk === false || ($chunk === '' && stream_get_meta_data($socket)['timed_out'])) {
            break;
        }
        $raw .= $chunk;
        $split = strpos($raw, "\r\n\r\n");
        if ($readBytesThenClose !== null && $split !== false && strlen($raw) - $split - 4 >= $readBytesThenClose) {
            break;   // the visitor leaves mid-transfer
        }
    }
    fclose($socket);

    [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    $lines = explode("\r\n", $head);
    $headers = [];
    foreach (array_slice($lines, 1) as $line) {
        [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
        $headers[strtolower(trim($name))] = trim($value);
    }

    return ['status' => $lines[0], 'headers' => $headers, 'body' => $body];
}

/** @return list<array<string, mixed>> */
function dhiEvents(object $test): array
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

/** Wait until the server process has written a terminal event (the transfer may still be running after the client left). */
function dhiAwaitTerminal(object $test, float $seconds = 20.0): array
{
    $until = microtime(true) + $seconds;
    do {
        $events = dhiEvents($test);
        if (array_filter($events, fn ($e) => in_array($e['ev'], ['transfer_finished', 'source_open_failed', 'source_rejected'], true)) !== []) {
            usleep(300_000);   // a second terminal event would arrive now, if there were one

            return dhiEvents($test);
        }
        usleep(50_000);
    } while (microtime(true) < $until);

    return dhiEvents($test);
}

function dhiSlotFree(float $waitSeconds = 5.0): bool
{
    $until = microtime(true) + $waitSeconds;
    do {
        $slot = app(DownloadCapacityGuard::class)->tryAcquire();
        $free = $slot?->isHeld() === true;
        $slot?->release();
        if ($free) {
            return true;
        }
        usleep(50_000);
    } while (microtime(true) < $until);

    return false;
}

function dhiLink(object $test, string $link): void
{
    DB::connection('dhi')->table('nuke_islamic_khotab')->where('id', 1)->update(['link' => $link]);
}

beforeEach(function () {
    $this->scratch = sys_get_temp_dir().'/dhi-'.bin2hex(random_bytes(5));
    mkdir($this->scratch);
    $this->root = $this->scratch.'/spool';
    $this->slotDir = $this->scratch.'/slots';
    $this->db = $this->scratch.'/main.sqlite';
    $this->cfgFile = $this->scratch.'/config.json';
    touch($this->db);
    $this->source = null;

    config(['database.connections.dhi' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => false]]);
    foreach ([
        'nuke_authors' => MainSchema::nukeAuthors(), 'nuke_config' => MainSchema::nukeConfig(), 'ips' => MainSchema::ips(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(), 'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(), DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections(),
    ] as $table => $definition) {
        Schema::connection('dhi')->create($table, $definition);
    }
    DB::connection('dhi')->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0, 'anonymous' => 'Anonymous']);
    DB::connection('dhi')->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    DB::connection('dhi')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 7, 'title' => 'بعيدة', 'vedio' => 1, 'link' => 'x', 'linksize' => 1, 'downcount' => 0]);

    // The test process checks slots in the same directory the server uses.
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 1, 'downloads.slot_directory' => $this->slotDir]);
    dhiWriteConfig($this);

    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $this->port = (int) substr((string) strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $this->server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:'.$this->port, '-t', base_path('public'), base_path('tests/Support/Telemetry/http-front.php')],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->scratch.'/server.out', 'a'], 2 => ['file', $this->scratch.'/server.err', 'a']],
        $pipes, base_path(), ['W2A_IT_CONFIG' => $this->cfgFile, 'PATH' => (string) getenv('PATH')],
    );
    $until = microtime(true) + 10;
    while (microtime(true) < $until && ! @stream_socket_client('tcp://127.0.0.1:'.$this->port, $e, $s, 0.2)) {
        usleep(50_000);
    }
});

afterEach(function () {
    $this->source?->stop();
    if (is_resource($this->server)) {
        proc_terminate($this->server);   // this test's own isolated `php -S` child only
        proc_close($this->server);
    }
    DB::purge('dhi');
    if (is_dir($this->scratch)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->scratch);
    }
});

it('a slow first byte, then a complete transfer: 200, exact body and length, completed once, slot freed', function () {
    $body = str_repeat('0123456789abcdef', 16 * 1024);   // 256 KiB
    $this->source = LocalHttpSource::start([LocalHttpSource::ok($body) + ['delay' => 1.0]]);
    dhiLink($this, $this->source->url());

    $response = dhiRequest($this, '/khotab-download-1.htm');
    $events = dhiAwaitTerminal($this);

    expect($response['status'])->toContain('200')
        ->and($response['headers']['content-length'])->toBe((string) strlen($body))
        ->and($response['body'])->toBe($body)
        ->and(array_column($events, 'ev'))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and($events[2])->toMatchArray(['st' => 'completed', 'b' => strlen($body)])
        ->and($events[1]['ttfb'])->toBeGreaterThanOrEqual(900)
        ->and(dhiSlotFree())->toBeTrue()
        ->and((int) DB::connection('dhi')->table('nuke_islamic_khotab')->where('id', 1)->value('downcount'))->toBe(1);
});

it('a slow upstream and a client that disconnects mid-transfer: exactly one client_aborted, slot freed', function () {
    $chunk = str_repeat('x', 64 * 1024);
    $count = 400;   // 25 MiB declared, dripped every 20 ms: the visitor leaves long before the end
    $this->source = LocalHttpSource::start([[
        'raw' => "HTTP/1.1 200 OK\r\nContent-Type: video/mp4\r\nContent-Length: ".(strlen($chunk) * $count)."\r\nConnection: close\r\n\r\n",
        'drip' => ['chunk' => $chunk, 'count' => $count, 'interval' => 0.02],
    ]]);
    dhiLink($this, $this->source->url());

    $response = dhiRequest($this, '/khotab-download-1.htm', readBytesThenClose: 256 * 1024);
    $events = dhiAwaitTerminal($this);
    $finished = array_values(array_filter($events, fn ($e) => $e['ev'] === 'transfer_finished'));

    expect($response['status'])->toContain('200')
        ->and(strlen($response['body']))->toBeGreaterThanOrEqual(256 * 1024)->toBeLessThan(strlen($chunk) * $count)
        ->and($finished)->toHaveCount(1)
        ->and($finished[0]['st'])->toBe('client_aborted')
        ->and($finished[0]['b'])->toBeLessThan(strlen($chunk) * $count)
        ->and(dhiSlotFree())->toBeTrue();

    // Which path ended it is recorded for the report: the in-loop check (no `k`) or the shutdown fallback.
    fwrite(STDERR, 'DHI_DISCONNECT_EVENT '.json_encode($finished[0]).PHP_EOL);
});

it('a source that stalls mid-body: the visitor gets a short body, one stalled outcome, slot freed', function () {
    $this->source = LocalHttpSource::start([['raw' => "HTTP/1.1 200 OK\r\nContent-Length: 1000000\r\nConnection: close\r\n\r\nhello", 'after' => 'hold']]);
    dhiLink($this, $this->source->url());

    $started = microtime(true);
    $response = dhiRequest($this, '/khotab-download-1.htm');
    $elapsed = microtime(true) - $started;
    $events = dhiAwaitTerminal($this);

    expect($response['headers']['content-length'])->toBe('1000000')
        ->and($response['body'])->toBe('hello')
        ->and($elapsed)->toBeGreaterThanOrEqual(0.9)->toBeLessThan(10.0)
        ->and(array_column($events, 'ev'))->toBe(['download_accepted', 'transfer_started', 'transfer_finished'])
        ->and($events[2])->toMatchArray(['st' => 'stalled', 'b' => 5])
        ->and(dhiSlotFree())->toBeTrue()
        ->and($this->source->sawClientClose())->toBeTrue();
});

it('a broken telemetry spool never changes the HTTP response (status, headers, body)', function () {
    $body = str_repeat('payload-', 4096);
    $this->source = LocalHttpSource::start([LocalHttpSource::ok($body), LocalHttpSource::ok($body)]);
    dhiLink($this, $this->source->url());

    $healthy = dhiRequest($this, '/khotab-download-1.htm');
    expect(dhiAwaitTerminal($this))->not->toBe([]);

    file_put_contents($this->scratch.'/not-a-dir', 'x');
    dhiWriteConfig($this, ['root' => $this->scratch.'/not-a-dir']);   // the router re-reads its config per request
    $broken = dhiRequest($this, '/khotab-download-1.htm');

    $strip = fn (array $h) => array_diff_key($h, ['date' => 1, 'set-cookie' => 1]);   // per-request values
    expect($broken['status'])->toBe($healthy['status'])
        ->and($strip($broken['headers']))->toBe($strip($healthy['headers']))
        ->and($broken['body'])->toBe($body)
        ->and(dhiSlotFree())->toBeTrue()
        ->and((string) @file_get_contents($this->scratch.'/server.err'))->not->toContain('Fatal');
});
