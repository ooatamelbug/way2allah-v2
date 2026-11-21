<?php

/*
 * Phase 1 — the writer/sealer protocol across REAL processes (tests/Support/Telemetry/spool-child.php).
 *
 * Interleavings that matter are forced deterministically: a child stops at a named protocol point and only
 * continues (or is SIGKILLed) when the test says so. Timeouts only guard against a hung test; no sleep
 * decides an outcome. The bulk tests (32 writers) additionally check the invariants under real contention.
 */

use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Tests\Support\Telemetry\SpoolProcess;

const TSC_MINUTE = 29_379_840; // 2025-11-10 16:00 UTC

function tscConfigArray(string $root, array $overrides = []): array
{
    return $overrides + [
        'spool' => 'on', 'root' => $root, 'file_max_bytes' => 4 * 1024 * 1024, 'max_files' => 1000, 'disk_reserve_bytes' => 0,
        'lock_budget_us' => 2000, 'ledger_lock_budget_us' => 50_000, 'rotation_retries' => 3, 'ledger_slots' => 60, 'ledger_shards' => 16, 'slow_write_us' => 60_000_000,
        'breaker_fault_threshold' => 1_000_000, 'breaker_open_seconds' => 300, 'seal_grace_minutes' => 0, 'seal_lock_budget_us' => 100_000,
    ];
}

function tscWriter(object $test, string $id, array $spec = []): SpoolProcess
{
    return SpoolProcess::start($spec + ['role' => 'writer', 'id' => $id, 'config' => $test->config, 'count' => 1, 'minute' => TSC_MINUTE], $test->barrier);
}

function tscLocalWriter(object $test, array $overrides = []): SpoolWriter
{
    return new SpoolWriter(SpoolConfig::fromArray(tscConfigArray($test->root, $overrides)), fn (): float => TSC_MINUTE * 60 + 1.0);
}

function tscEvent(string $id): array
{
    return ['t' => TSC_MINUTE * 60 + 1.0, 'ev' => 'capacity_rejected', 'id' => $id, 'mod' => 'khotab'];
}

/** @return array{ids: list<string>, malformed: int, trailing: int, files: int, bytes: int} across active, sealed and quarantine */
function tscSpool(SpoolConfig $config): array
{
    $out = ['ids' => [], 'malformed' => 0, 'trailing' => 0, 'files' => 0, 'bytes' => 0];
    foreach ([$config->activeDir(), $config->sealedDir(), $config->quarantineDir()] as $dir) {
        foreach (glob($dir.'/*.ndjson') ?: [] as $file) {
            $stats = BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$out) {
                $out['ids'][] = (string) ($e->toArray()['id'] ?? '?');
            });
            $out['malformed'] += $stats['malformed'];
            $out['trailing'] += $stats['trailing_fragment'] ? 1 : 0;
            $out['files']++;
            $out['bytes'] += $stats['bytes'];
        }
    }

    return $out;
}

function tscLedger(SpoolConfig $config): array
{
    $entries = (new DropLedger($config))->snapshot()['entries'];

    return [
        'dropped' => array_sum(array_column($entries, 'dropped_events')),
        'reasons' => array_reduce($entries, fn (int $m, array $s) => $m | $s['reasons'], 0),
        'torn' => array_sum(array_column($entries, 'torn_repaired')),
    ];
}

/**
 * The accounting invariant: every attempted event is either persisted exactly once or counted as dropped.
 *
 * @param  list<array<string, mixed>>  $results
 */
function tscAssertExactAccounting(SpoolConfig $config, array $results): array
{
    $spool = tscSpool($config);
    $written = array_merge(...array_map(fn ($r) => $r['written'], $results));
    $attempted = array_sum(array_column($results, 'attempted'));
    $dropped = array_sum(array_column($results, 'dropped'));

    expect(count($spool['ids']))->toBe(count(array_unique($spool['ids'])))   // no duplicates
        ->and($spool['ids'])->toEqualCanonicalizing($written)                      // exactly the events reported written
        ->and(count($written) + $dropped)->toBe($attempted)                        // nothing unaccounted
        ->and(tscLedger($config)['dropped'])->toBe($dropped)                       // every drop recorded in the ledger
        ->and(array_sum(array_column($results, 'unrecorded')))->toBe(0)
        ->and($spool['malformed'])->toBe(0);

    return $spool;
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-spoolc-'.bin2hex(random_bytes(5));
    $this->barrier = $this->root.'-barrier';
    mkdir($this->barrier, 0775, true);
    $this->config = tscConfigArray($this->root);
});

afterEach(function () {
    foreach ([$this->root, $this->barrier] as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
});

// --- Deterministic rotation -------------------------------------------------------------------------------------

it('rotation between open and lock: the writer detects the sealed inode, reopens, and writes once into the NEW file', function () {
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this)->write(tscEvent('before'));                        // the file exists, so the child opens it

    $writer = tscWriter($this, 'w', ['pause' => ['after_open']]);
    $writer->waitReached('after_open');                                       // the child holds an fd, no lock yet

    $sealed = (new SpoolSealer($config))->seal(true)['sealed'];               // seal it under the child
    $sealedBytes = filesize($sealed[0]['path']);
    $writer->release('after_open');
    $result = $writer->wait();

    expect($result['written'])->toBe(['w-1'])
        ->and($result['stats']['rotations'])->toBe(1)
        ->and(filesize($sealed[0]['path']))->toBe($sealedBytes)              // nothing appended to the sealed batch
        ->and(BatchInspector::inspect($sealed[0]['path'])['valid'])->toBe(1)
        ->and(BatchInspector::inspect($config->activePath(TSC_MINUTE))['valid'])->toBe(1);
    tscAssertExactAccounting($config, [$result + ['attempted' => 1], ['written' => ['before'], 'attempted' => 1, 'dropped' => 0, 'unrecorded' => 0]]);
});

it('rotation attempted after the writer has the lock: the sealer cannot rename it and defers; the write completes, then it seals', function () {
    $config = SpoolConfig::fromArray($this->config);
    $writer = tscWriter($this, 'w', ['pause' => ['after_lock']]);
    $writer->waitReached('after_lock');                                       // the child holds the file's lock

    $first = (new SpoolSealer($config))->seal(true);                          // budget 100 ms
    expect($first['sealed'])->toBe([])
        ->and($first['deferred'])->toHaveCount(1)
        ->and(file_exists($config->activePath(TSC_MINUTE)))->toBeTrue();

    $writer->release('after_lock');
    $result = $writer->wait();
    $second = (new SpoolSealer($config))->seal(true);

    expect($result['written'])->toBe(['w-1'])->and($result['stats']['rotations'])->toBe(0)
        ->and($second['sealed'])->toHaveCount(1)
        ->and(BatchInspector::inspect($second['sealed'][0]['path'])['valid'])->toBe(1);
});

it('a writer that keeps finding its file sealed gives up after the retry limit and counts the drop — never writing into a sealed file', function () {
    $this->config = tscConfigArray($this->root, ['rotation_retries' => 0]);
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this, ['rotation_retries' => 0])->write(tscEvent('before'));

    $writer = tscWriter($this, 'w', ['pause' => ['after_open']]);
    $writer->waitReached('after_open');
    $sealed = (new SpoolSealer($config))->seal(true)['sealed'];
    $writer->release('after_open');
    $result = $writer->wait();

    expect($result['dropped'])->toBe(1)->and($result['written'])->toBe([])
        ->and(BatchInspector::inspect($sealed[0]['path'])['valid'])->toBe(1)
        ->and(tscLedger($config)['reasons'] & DropReason::ROTATION_RETRIES)->not->toBe(0);
});

it('lock timeout across processes: a writer blocked by another holder gives up within its budget and counts the drop', function () {
    $config = SpoolConfig::fromArray($this->config);
    $holder = tscWriter($this, 'holder', ['pause' => ['after_lock']]);
    $holder->waitReached('after_lock');

    $blocked = tscWriter($this, 'blocked')->wait();                           // budget 2 ms
    $holder->release('after_lock');
    $held = $holder->wait();

    expect($blocked['dropped'])->toBe(1)
        ->and($blocked['last_latency_ns'] / 1e6)->toBeLessThan(30.0)
        ->and(tscLedger($config)['reasons'] & DropReason::LOCK_TIMEOUT)->not->toBe(0);
    tscAssertExactAccounting($config, [$blocked, $held]);
});

// --- Bulk concurrency -----------------------------------------------------------------------------------------

it('32 concurrent writers while a sealer seals continuously: no duplicates, nothing written into a sealed file, exact accounting', function (int $lockBudgetUs, int $fileMaxBytes, int $maxFiles) {
    $this->config = tscConfigArray($this->root, ['lock_budget_us' => $lockBudgetUs, 'file_max_bytes' => $fileMaxBytes, 'max_files' => $maxFiles]);
    $config = SpoolConfig::fromArray($this->config);
    $writers = [];
    for ($i = 0; $i < 32; $i++) {
        $writers[] = tscWriter($this, "w{$i}", ['count' => 1000, 'gate' => true]);
    }
    $sealer = SpoolProcess::start(['role' => 'sealer', 'id' => 'sealer', 'config' => $this->config, 'loop' => true, 'all' => true,
        'interval_us' => 1000, 'gate' => true], $this->barrier);
    foreach ([...$writers, $sealer] as $p) {
        $p->waitReady();                                                     // everyone booted before anyone starts
    }
    SpoolProcess::go($this->barrier);

    $results = array_map(fn (SpoolProcess $w) => $w->wait(), $writers);
    touch($this->barrier.'/stop');
    $sealed = $sealer->wait()['sealed'];

    $spool = tscAssertExactAccounting($config, $results);
    foreach ($sealed as $batch) {
        expect(filesize($batch['path']))->toBe($batch['bytes']);           // sealed files never grew afterwards
    }
    expect(count($sealed))->toBeGreaterThan(1);                              // the sealer really rotated during writing

    $this->rotations = array_sum(array_map(fn ($r) => $r['stats']['rotations'], $results));
    $this->spins = array_sum(array_map(fn ($r) => $r['stats']['lock_spins'], $results));
    fwrite(STDERR, sprintf("\n[32 writers × 1000 + sealer, budget %d µs, cap %d MiB × %d] written %d, dropped %d, batches %d, rotations %d, lock spins %d\n",
        $lockBudgetUs, $fileMaxBytes / 1048576, $maxFiles, count($spool['ids']), array_sum(array_column($results, 'dropped')), count($sealed), $this->rotations, $this->spins));
})->with([
    'A: 2 ms, 8 MiB × 128' => [2000, 8 * 1024 * 1024, 128],
    'B: 5 ms, 8 MiB × 128' => [5000, 8 * 1024 * 1024, 128],
    'C: 2 ms, 32 MiB × 32 (default)' => [2000, 32 * 1024 * 1024, 32],
    'D: 5 ms, 32 MiB × 32' => [5000, 32 * 1024 * 1024, 32],
]);

it('capacity exhaustion under 32 writers: the bound holds exactly and every overflow is counted', function () {
    $this->config = tscConfigArray($this->root, ['file_max_bytes' => 16 * 1024, 'max_files' => 2]);
    $config = SpoolConfig::fromArray($this->config);
    $writers = [];
    for ($i = 0; $i < 32; $i++) {
        $writers[] = tscWriter($this, "w{$i}", ['count' => 100, 'gate' => true, 'pad' => 100]);
    }
    foreach ($writers as $w) {
        $w->waitReady();
    }
    SpoolProcess::go($this->barrier);
    $results = array_map(fn (SpoolProcess $w) => $w->wait(), $writers);

    $spool = tscAssertExactAccounting($config, $results);
    expect(filesize($config->activePath(TSC_MINUTE)))->toBeLessThanOrEqual(16 * 1024)
        ->and($spool['bytes'])->toBeLessThanOrEqual($config->spoolBoundBytes())
        ->and(tscLedger($config)['reasons'] & DropReason::FILE_FULL)->not->toBe(0);
});

it('file-count limit with concurrent creation of several new minutes: exactly max_files files exist, the rest is counted', function () {
    $this->config = tscConfigArray($this->root, ['max_files' => 2, 'lock_budget_us' => 500_000]);
    $config = SpoolConfig::fromArray($this->config);
    $writers = [];
    for ($i = 0; $i < 32; $i++) {
        $writers[] = tscWriter($this, "w{$i}", ['count' => 20, 'gate' => true, 'minute' => TSC_MINUTE + ($i % 4)]);
    }
    foreach ($writers as $w) {
        $w->waitReady();
    }
    SpoolProcess::go($this->barrier);
    $results = array_map(fn (SpoolProcess $w) => $w->wait(), $writers);

    tscAssertExactAccounting($config, $results);
    expect(glob($config->activeDir().'/*.ndjson'))->toHaveCount(2)
        ->and(tscLedger($config)['reasons'] & DropReason::FILE_COUNT)->not->toBe(0);
});

it('concurrent creation of the same new minute file: exactly one file, no event lost', function () {
    $this->config = tscConfigArray($this->root, ['lock_budget_us' => 1_000_000]);
    $config = SpoolConfig::fromArray($this->config);

    $first = tscWriter($this, 'first', ['pause' => ['create_locked']]);        // holds create.lock, file not yet created
    $first->waitReached('create_locked');
    $others = [];
    for ($i = 0; $i < 31; $i++) {
        $others[] = tscWriter($this, "w{$i}", ['count' => 10, 'gate' => true]);
    }
    foreach ($others as $w) {
        $w->waitReady();
    }
    SpoolProcess::go($this->barrier);                                          // all race for create.lock while it is held
    $first->release('create_locked');
    $results = array_merge([$first->wait()], array_map(fn (SpoolProcess $w) => $w->wait(), $others));

    $spool = tscAssertExactAccounting($config, $results);
    expect(glob($config->activeDir().'/*.ndjson'))->toHaveCount(1)
        ->and(count($spool['ids']))->toBe(1 + 31 * 10);
});

it('scheduler/sealer stopped: the spool fills to its configured limit, then every further event is refused and counted — the bound never moves', function () {
    // 8 writers, each advancing one simulated minute every 25 events, no sealer at all. Bound: 4 files × 32 KiB.
    $this->config = tscConfigArray($this->root, ['file_max_bytes' => 32 * 1024, 'max_files' => 4]);
    $config = SpoolConfig::fromArray($this->config);
    $writers = [];
    for ($i = 0; $i < 8; $i++) {
        $writers[] = tscWriter($this, "w{$i}", ['count' => 400, 'gate' => true, 'pad' => 60, 'minute_step' => 25]);
    }
    foreach ($writers as $w) {
        $w->waitReady();
    }
    SpoolProcess::go($this->barrier);
    $results = array_map(fn (SpoolProcess $w) => $w->wait(), $writers);

    $spool = tscAssertExactAccounting($config, $results);
    $files = glob($config->activeDir().'/*.ndjson');
    expect($files)->toHaveCount(4)                                              // the limit was reached …
        ->and(max(array_map('filesize', $files)))->toBeLessThanOrEqual(32 * 1024)
        ->and($spool['bytes'])->toBeLessThanOrEqual($config->spoolBoundBytes())   // … and never exceeded
        ->and(tscLedger($config)['reasons'] & DropReason::FILE_COUNT)->not->toBe(0)
        ->and(collect($results)->every(fn ($r) => $r['survived'] === true))->toBeTrue();   // fail-open: every writer carried on

    // Sealing alone frees nothing (sealed batches still count until Phase 2 consumes them): still bounded, still refusing new minutes.
    (new SpoolSealer($config))->seal(true);
    $late = tscWriter($this, 'late', ['count' => 50, 'minute' => TSC_MINUTE + 999])->wait();
    expect($late['dropped'])->toBe(50)
        ->and(SpoolWriter::spoolFileCount($config))->toBe(4)
        ->and(tscSpool($config)['bytes'])->toBeLessThanOrEqual($config->spoolBoundBytes());
});

// --- Crash recovery ---------------------------------------------------------------------------------------------

it('writer SIGKILLed mid-write: the lock is released, the fragment is isolated and reported, nothing else is lost', function () {
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this)->write(tscEvent('before'));

    $victim = tscWriter($this, 'victim', ['pause' => ['mid_write']]);
    $victim->waitReached('mid_write');                                         // half a record is on disk, lock held
    $victim->kill();

    expect(tscLocalWriter($this)->write(tscEvent('after')))->toBe(SpoolWriter::WRITTEN);   // no wait: the OS dropped the lock
    $stats = BatchInspector::inspect($config->activePath(TSC_MINUTE));

    expect($stats)->toMatchArray(['valid' => 2, 'malformed' => 1, 'trailing_fragment' => false])
        ->and(tscSpool($config)['ids'])->toBe(['before', 'after'])
        ->and(tscLedger($config)['torn'])->toBe(1);
});

it('partial final record: a writer killed during the file\'s last write leaves a trailing fragment, reported on sealing', function () {
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this)->write(tscEvent('before'));
    $victim = tscWriter($this, 'victim', ['pause' => ['mid_write']]);
    $victim->waitReached('mid_write');
    $victim->kill();

    $sealed = (new SpoolSealer($config))->seal(true)['sealed'];

    expect(BatchInspector::inspect($sealed[0]['path']))->toMatchArray(['valid' => 1, 'malformed' => 1, 'trailing_fragment' => true]);
});

it('sealer SIGKILLed before rename: the file stays active and unlocked; writing and sealing continue normally', function () {
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this)->write(tscEvent('before'));
    $sealer = SpoolProcess::start(['role' => 'sealer', 'id' => 's', 'config' => $this->config, 'pause' => ['sealer_before_rename']], $this->barrier);
    $sealer->waitReached('sealer_before_rename');                               // holds the file lock and seal.lock
    $sealer->kill();

    expect(file_exists($config->activePath(TSC_MINUTE)))->toBeTrue()
        ->and(glob($config->sealedDir().'/*.ndjson'))->toBe([])
        ->and(tscLocalWriter($this)->write(tscEvent('after')))->toBe(SpoolWriter::WRITTEN);

    $report = (new SpoolSealer($config))->seal(true);                          // seal.lock was released by the OS too
    expect($report['busy'])->toBeFalse()->and($report['sealed'])->toHaveCount(1)
        ->and(tscSpool($config)['ids'])->toBe(['before', 'after']);
});

it('sealer SIGKILLed after rename: the batch is sealed, writers move to a new file, nothing is lost or duplicated', function () {
    $config = SpoolConfig::fromArray($this->config);
    tscLocalWriter($this)->write(tscEvent('before'));
    $sealer = SpoolProcess::start(['role' => 'sealer', 'id' => 's', 'config' => $this->config, 'pause' => ['sealer_after_rename']], $this->barrier);
    $sealer->waitReached('sealer_after_rename');                                // renamed, lock still held
    $sealer->kill();

    expect(glob($config->sealedDir().'/*.ndjson'))->toHaveCount(1)
        ->and(file_exists($config->activePath(TSC_MINUTE)))->toBeFalse()
        ->and(tscLocalWriter($this)->write(tscEvent('after')))->toBe(SpoolWriter::WRITTEN);

    $spool = tscSpool($config);
    expect($spool['ids'])->toEqualCanonicalizing(['before', 'after'])->and($spool['files'])->toBe(2);
});

it('filesystem write failure (RLIMIT_FSIZE → EFBIG): failed writes are rolled back and counted, the process carries on', function () {
    // 16 blocks of 512 bytes (dash) = 8 KiB for every file this child writes; the 2 KiB ledger fits.
    $this->config = tscConfigArray($this->root, ['breaker_fault_threshold' => 1_000_000]);
    $config = SpoolConfig::fromArray($this->config);
    $result = SpoolProcess::start(['role' => 'writer', 'id' => 'w', 'config' => $this->config, 'count' => 200, 'minute' => TSC_MINUTE,
        'pad' => 60, 'no_xfsz' => true], $this->barrier, 16)->wait();

    $file = $config->activePath(TSC_MINUTE);
    expect($result['survived'])->toBeTrue()                                    // a "download" continues after telemetry failed
        ->and($result['dropped'])->toBeGreaterThan(100)
        ->and(filesize($file))->toBeLessThanOrEqual(8192)
        ->and(substr((string) file_get_contents($file), -1))->toBe("\n")       // no torn record left by the failures
        ->and(tscLedger($config)['reasons'] & DropReason::IO_ERROR)->not->toBe(0);
    tscAssertExactAccounting($config, [$result]);
});

it('disk-full simulation: below the reserve no new file is created, every event is counted, the ledger still records', function () {
    $this->config = tscConfigArray($this->root, ['disk_reserve_bytes' => 1024 * 1024]);
    $config = SpoolConfig::fromArray($this->config);
    $results = [];
    for ($i = 0; $i < 4; $i++) {
        $results[] = tscWriter($this, "w{$i}", ['count' => 25, 'disk_free' => 0, 'minute' => TSC_MINUTE + $i])->wait();
    }

    expect(glob($config->activeDir().'/*.ndjson'))->toBe([])
        ->and(tscLedger($config)['reasons'] & DropReason::DISK_RESERVE)->not->toBe(0);
    tscAssertExactAccounting($config, $results);
});
