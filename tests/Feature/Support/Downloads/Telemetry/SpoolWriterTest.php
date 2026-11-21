<?php

/*
 * Phase 1 — the bounded spool writer and sealer in one process: capacity, disk reserve, lock budget, breaker,
 * the fixed-size drop ledger, torn-record repair, sealing, quarantine, fail-open, and no database or network.
 */

use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolStatus;
use App\Support\Downloads\Telemetry\SpoolWriter;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const TSW_MINUTE = 29_379_840; // 2025-11-10 16:00 UTC

function tswConfig(string $root, array $overrides = []): SpoolConfig
{
    return SpoolConfig::fromArray($overrides + [
        'spool' => 'on', 'root' => $root, 'file_max_bytes' => 64 * 1024, 'max_files' => 10, 'disk_reserve_bytes' => 0,
        'lock_budget_us' => 2000, 'ledger_lock_budget_us' => 50_000, 'rotation_retries' => 3, 'ledger_slots' => 60, 'ledger_shards' => 4, 'slow_write_us' => 5_000_000,
        'breaker_fault_threshold' => 20, 'breaker_open_seconds' => 300, 'seal_grace_minutes' => 0, 'seal_lock_budget_us' => 200_000,
    ]);
}

function tswEvent(int $i = 1, array $extra = []): array
{
    return ['t' => TSW_MINUTE * 60 + 1.0, 'ev' => 'capacity_rejected', 'id' => "e{$i}", 'mod' => 'khotab', 'rid' => $i] + $extra;
}

function tswWriter(SpoolConfig $config, int $minute = TSW_MINUTE, ?Closure $diskFree = null, ?Closure $hook = null): SpoolWriter
{
    return new SpoolWriter($config, fn (): float => $minute * 60 + 1.0, $hook, $diskFree);
}

/** @return list<string> event ids in every spool file */
function tswPersistedIds(SpoolConfig $config): array
{
    $ids = [];
    foreach ([$config->activeDir(), $config->sealedDir(), $config->quarantineDir()] as $dir) {
        foreach (glob($dir.'/*.ndjson') ?: [] as $file) {
            BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$ids) {
                $ids[] = $e->toArray()['id'] ?? '?';
            });
        }
    }

    return $ids;
}

function tswLedgerDropped(SpoolConfig $config): int
{
    return array_sum(array_column((new DropLedger($config))->snapshot()['entries'], 'dropped_events'));
}

function tswLedgerReasons(SpoolConfig $config): int
{
    return array_reduce((new DropLedger($config))->snapshot()['entries'], fn (int $m, array $s) => $m | $s['reasons'], 0);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-spool-'.bin2hex(random_bytes(5));
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
    // Only this test's own isolated directory.
    if (is_dir($this->root)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->root);
    }
});

it('is off by default and then touches nothing', function () {
    $config = SpoolConfig::fromArray(['root' => $this->root]);
    $writer = new SpoolWriter($config);

    expect($config->enabled)->toBeFalse()
        ->and($writer->write(tswEvent()))->toBe(SpoolWriter::DISABLED)
        ->and(file_exists($this->root))->toBeFalse()
        ->and(SpoolConfig::fromLaravel()->enabled)->toBeFalse();   // the shipped configuration
});

it('ships the approved defaults: 2 ms writer budget, 32 MiB × 32 files = a 1 GiB hard bound, 16 ledger shards, breaker at 50 health faults/minute', function () {
    $shipped = SpoolConfig::fromLaravel();

    expect($shipped->lockBudgetUs)->toBe(2000)
        ->and($shipped->fileMaxBytes)->toBe(32 * 1024 * 1024)
        ->and($shipped->maxFiles)->toBe(32)
        ->and($shipped->spoolBoundBytes())->toBe(1024 * 1024 * 1024)
        ->and($shipped->ledgerLockBudgetUs)->toBe(50_000)
        ->and($shipped->ledgerShards)->toBe(16)
        ->and($shipped->ledgerBytes())->toBe(16 * (96 + 40 * 10080))
        ->and($shipped->breakerFaultThreshold)->toBe(50);
});

it('writes complete lines into the minute file and seals them under a stable batch id', function () {
    $config = tswConfig($this->root);
    $writer = tswWriter($config);
    foreach (range(1, 3) as $i) {
        expect($writer->write(tswEvent($i)))->toBe(SpoolWriter::WRITTEN);
    }
    $active = $config->activePath(TSW_MINUTE);
    expect(file_exists($active))->toBeTrue()->and(substr_count((string) file_get_contents($active), "\n"))->toBe(3);

    $report = (new SpoolSealer($config, fn (): float => (TSW_MINUTE + 1) * 60 + 5.0))->seal();

    expect($report['sealed'])->toHaveCount(1)
        ->and($report['sealed'][0]['batch_id'])->toMatch('/^202511101600-\d{16}-[0-9a-f]{8}$/')
        ->and(file_exists($active))->toBeFalse()
        ->and(file_exists($report['sealed'][0]['path']))->toBeTrue()
        ->and(BatchInspector::inspect($report['sealed'][0]['path']))->toMatchArray(['lines' => 3, 'valid' => 3, 'malformed' => 0, 'trailing_fragment' => false]);
});

it('does not seal the current minute (or one still inside its grace period) unless asked to', function () {
    $config = tswConfig($this->root, ['seal_grace_minutes' => 2]);
    tswWriter($config)->write(tswEvent());

    $at = fn (int $minutesLater) => (new SpoolSealer($config, fn (): float => (TSW_MINUTE + $minutesLater) * 60 + 5.0))->seal();

    expect($at(0)['sealed'])->toBe([])
        ->and($at(2)['sealed'])->toBe([])        // ended 1 minute ago, grace is 2
        ->and($at(3)['sealed'])->toHaveCount(1); // ended 2 minutes ago
});

it('drops (and counts) an event that would grow the minute file past file_max_bytes', function () {
    $config = tswConfig($this->root, ['file_max_bytes' => 4096]);
    $writer = tswWriter($config);
    $written = 0;
    $dropped = 0;
    for ($i = 1; $i <= 200; $i++) {
        $writer->write(tswEvent($i, ['ua' => str_repeat('u', 200)])) === SpoolWriter::WRITTEN ? $written++ : $dropped++;
    }

    expect(filesize($config->activePath(TSW_MINUTE)))->toBeLessThanOrEqual(4096)
        ->and($written + $dropped)->toBe(200)->and($dropped)->toBeGreaterThan(0)
        ->and(count(tswPersistedIds($config)))->toBe($written)
        ->and(tswLedgerDropped($config))->toBe($dropped)
        ->and(tswLedgerReasons($config) & DropReason::FILE_FULL)->not->toBe(0);
});

it('refuses to create a file beyond max_files, counting active, sealed and quarantined files alike', function () {
    $config = tswConfig($this->root, ['max_files' => 3]);
    foreach ([0, 1, 2] as $offset) {
        expect(tswWriter($config, TSW_MINUTE + $offset)->write(tswEvent($offset)))->toBe(SpoolWriter::WRITTEN);
    }
    (new SpoolSealer($config, fn (): float => (TSW_MINUTE + 2) * 60 + 5.0))->seal();   // seals two of them; still 3 files

    expect(tswWriter($config, TSW_MINUTE + 3)->write(tswEvent(9)))->toBe(SpoolWriter::DROPPED)
        ->and(SpoolWriter::spoolFileCount($config))->toBe(3)
        ->and(tswWriter($config, TSW_MINUTE + 2)->write(tswEvent(10)))->toBe(SpoolWriter::WRITTEN)   // an existing file still accepts
        ->and(tswLedgerReasons($config) & DropReason::FILE_COUNT)->not->toBe(0);
});

it('creates no new file while free disk is below the reserve, but keeps appending to an existing one', function () {
    $config = tswConfig($this->root, ['disk_reserve_bytes' => 1_000_000]);
    $plenty = fn (string $dir): float => 5_000_000.0;
    $low = fn (string $dir): float => 10.0;

    expect(tswWriter($config, TSW_MINUTE, $plenty)->write(tswEvent(1)))->toBe(SpoolWriter::WRITTEN)
        ->and(tswWriter($config, TSW_MINUTE, $low)->write(tswEvent(2)))->toBe(SpoolWriter::WRITTEN)
        ->and(tswWriter($config, TSW_MINUTE + 1, $low)->write(tswEvent(3)))->toBe(SpoolWriter::DROPPED)
        ->and(tswLedgerReasons($config) & DropReason::DISK_RESERVE)->not->toBe(0);
});

it('never waits for a held file lock longer than the budget, and counts the drop (as contention, not a health fault)', function () {
    $config = tswConfig($this->root, ['lock_budget_us' => 3000]);
    tswWriter($config)->write(tswEvent(1));                   // create the file
    $holder = fopen($config->activePath(TSW_MINUTE), 'r+b');
    flock($holder, LOCK_EX);

    try {
        $writer = tswWriter($config);
        $outcome = $writer->write(tswEvent(2));
        $latencyMs = $writer->lastLatencyNs() / 1e6;
    } finally {
        flock($holder, LOCK_UN);
        fclose($holder);
    }

    $slot = (new DropLedger($config))->snapshot()['entries'][0];
    expect($outcome)->toBe(SpoolWriter::DROPPED)
        ->and($latencyMs)->toBeGreaterThanOrEqual(3.0)->toBeLessThan(25.0)   // the 3 ms budget, plus ledger update
        ->and($slot['reasons'] & DropReason::LOCK_TIMEOUT)->not->toBe(0)
        ->and($slot['health_faults'])->toBe(0);                               // contention is not a health fault
});

it('opens the breaker after enough faults in a minute, drops while open, and closes when it expires', function () {
    $config = tswConfig($this->root, ['slow_write_us' => 1, 'breaker_fault_threshold' => 3, 'breaker_open_seconds' => 120]);
    $writer = tswWriter($config);

    $outcomes = array_map(fn (int $i) => $writer->write(tswEvent($i)), range(1, 5));

    // Every write is "slow" (threshold 1 µs): the 3rd fault opens the breaker; later writes are dropped.
    expect(array_slice($outcomes, 0, 3))->toBe([SpoolWriter::WRITTEN, SpoolWriter::WRITTEN, SpoolWriter::WRITTEN])
        ->and(array_slice($outcomes, 3))->toBe([SpoolWriter::DROPPED, SpoolWriter::DROPPED])
        ->and(tswLedgerReasons($config) & DropReason::BREAKER_OPEN)->not->toBe(0)
        ->and(SpoolStatus::collect($config, 60, TSW_MINUTE * 60 + 2.0)['breaker_open_until'])->not->toBeNull();

    // 121 s later the marker lies in the past: writing resumes.
    $later = tswWriter(tswConfig($this->root, ['breaker_fault_threshold' => 3]), TSW_MINUTE + 3);
    expect($later->write(tswEvent(6)))->toBe(SpoolWriter::WRITTEN);
});

it('drops an invalid event without throwing', function () {
    $config = tswConfig($this->root);

    expect(tswWriter($config)->write(['t' => 1.0, 'ev' => 'nope', 'mod' => 'x']))->toBe(SpoolWriter::DROPPED)
        ->and(tswLedgerReasons($config) & DropReason::INVALID_EVENT)->not->toBe(0);
});

it('fails open when the spool root cannot be used at all', function () {
    file_put_contents($this->root, 'a plain file where the directory should be');
    $config = tswConfig($this->root);

    try {
        expect(tswWriter($config)->write(tswEvent()))->toBe(SpoolWriter::DROPPED)
            ->and(DropLedger::pendingEvents())->toBe(1);     // not even the ledger is available: held in memory …

        (new DropLedger($config))->flushPending();             // … the shutdown flush fails too: counted and reported
        expect(DropLedger::unrecorded())->toBe(1)->and(DropLedger::pendingEvents())->toBe(0);
    } finally {
        @unlink($this->root);
    }
});

it('isolates a torn record left by a killed writer: the next write starts on a new line, the repair is counted', function () {
    $config = tswConfig($this->root);
    tswWriter($config)->write(tswEvent(1));
    file_put_contents($config->activePath(TSW_MINUTE), '{"v":1,"t":17627', FILE_APPEND);   // what a SIGKILL mid-write leaves

    expect(tswWriter($config)->write(tswEvent(2)))->toBe(SpoolWriter::WRITTEN);

    $stats = BatchInspector::inspect($config->activePath(TSW_MINUTE));
    expect($stats)->toMatchArray(['lines' => 3, 'valid' => 2, 'malformed' => 1, 'trailing_fragment' => false])
        ->and(tswPersistedIds($config))->toBe(['e1', 'e2'])
        ->and((new DropLedger($config))->snapshot()['entries'][0]['torn_repaired'])->toBe(1);
});

it('reports a torn final record as a trailing fragment', function () {
    $config = tswConfig($this->root);
    tswWriter($config)->write(tswEvent(1));
    file_put_contents($config->activePath(TSW_MINUTE), '{"v":1,"t":176', FILE_APPEND);

    $report = (new SpoolSealer($config, fn (): float => (TSW_MINUTE + 1) * 60 + 5.0))->seal();

    expect(BatchInspector::inspect($report['sealed'][0]['path']))->toMatchArray(['valid' => 1, 'malformed' => 1, 'trailing_fragment' => true]);
});

it('quarantines a sealed batch containing a newer schema version, and keeps it within the file count', function () {
    $config = tswConfig($this->root);
    tswWriter($config)->write(tswEvent(1));
    file_put_contents($config->activePath(TSW_MINUTE), '{"v":2,"t":1,"ev":"future","mod":"k"}'."\n", FILE_APPEND);

    $report = (new SpoolSealer($config, fn (): float => (TSW_MINUTE + 1) * 60 + 5.0))->seal();

    expect($report['quarantined'])->toHaveCount(1)
        ->and(glob($config->quarantineDir().'/*.ndjson'))->toHaveCount(1)
        ->and(glob($config->sealedDir().'/*.ndjson'))->toBe([])
        ->and(SpoolWriter::spoolFileCount($config))->toBe(1);
});

it('keeps the drop ledger at a fixed size, and reports any count overwritten when the ring wraps', function () {
    $config = tswConfig($this->root, ['ledger_slots' => 4, 'ledger_shards' => 1, 'max_files' => 1]);
    tswWriter($config, TSW_MINUTE)->write(tswEvent(1));          // the one allowed file
    tswWriter($config, TSW_MINUTE + 1)->write(tswEvent(2));      // dropped (file count) → slot (M+1) % 4
    $size = filesize($config->ledgerShardPath(0));

    tswWriter($config, TSW_MINUTE + 5)->write(tswEvent(3));      // same slot, newer minute: the old count is reported as overwritten
    $snapshot = (new DropLedger($config))->snapshot();

    expect($size)->toBe(96 + 4 * 40)
        ->and($config->ledgerBytes())->toBe($size)
        ->and(filesize($config->ledgerShardPath(0)))->toBe($size)
        ->and($snapshot['overwritten_events'])->toBe(1)
        ->and(array_column($snapshot['entries'], 'minute'))->toBe([TSW_MINUTE + 5]);
});

it('performs no database query and no network access while writing, dropping or sealing', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    Http::preventStrayRequests();
    $probe = new class
    {
        public static int $opened = 0;

        public $context;

        public function stream_open(): bool
        {
            self::$opened++;

            return false;
        }
    };
    foreach (['http', 'https', 'ftp'] as $scheme) {
        stream_wrapper_unregister($scheme);
        stream_wrapper_register($scheme, $probe::class);
    }

    try {
        $config = tswConfig($this->root, ['file_max_bytes' => 4096, 'max_files' => 1]);
        for ($i = 1; $i <= 50; $i++) {
            tswWriter($config, TSW_MINUTE + ($i % 2))->write(tswEvent($i, ['ua' => str_repeat('z', 150)]));   // writes and drops
        }
        (new SpoolSealer($config, fn (): float => (TSW_MINUTE + 5) * 60.0))->seal();
    } finally {
        foreach (['http', 'https', 'ftp'] as $scheme) {
            stream_wrapper_restore($scheme);
        }
    }

    expect($queries)->toBe(0)->and($probe::$opened)->toBe(0);

    // And statically: the spool code references no database, HTTP client or socket API at all.
    $source = implode("\n", array_map('file_get_contents', glob(app_path('Support/Downloads/Telemetry/*.php'))));
    expect($source)->not->toMatch('/\b(DB::|Illuminate\\\\Support\\\\Facades\\\\(DB|Http)|PDO|mysqli|curl_|fsockopen|stream_socket_client|Http::|file_get_contents\(\s*[\'"]https?)/');
});

it('reports spool usage against the bound through the status command', function () {
    config(['download_telemetry.spool' => 'on', 'download_telemetry.root' => $this->root]);
    $config = SpoolConfig::fromLaravel();
    (new SpoolWriter($config))->write(['t' => microtime(true), 'ev' => 'capacity_rejected', 'mod' => 'khotab']);

    Artisan::call('downloads:telemetry-spool-status', ['--json' => true]);
    $status = json_decode(Artisan::output(), true);

    expect($status['enabled'])->toBeTrue()
        ->and($status['files']['active']['files'])->toBe(1)
        ->and($status['bound_bytes'])->toBe($config->maxFiles * $config->fileMaxBytes)
        ->and($status['total_bytes'])->toBeGreaterThan(0);

    expect(Artisan::call('downloads:telemetry-seal', ['--all' => true]))->toBe(0)
        ->and(glob($config->sealedDir().'/*.ndjson'))->toHaveCount(1);
});
