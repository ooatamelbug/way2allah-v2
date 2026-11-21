<?php

/*
 * Phase 1.1 — reliability corrections.
 *
 *  1. Circuit breaker: only genuine writer HEALTH faults (I/O errors, slow successful writes) can open it. Sustained
 *     capacity exhaustion and heavy lock contention are tested separately and must leave it closed.
 *  2. Drop ledger: sharded, per-event durable counters with a bounded in-memory fallback, an external error-log
 *     record for anything still unrecordable, and explicit uncertainty indicators (CRC-torn slots, reboot windows,
 *     unreadable shards). Counts are never silently understated: every drop is in the ledger, or reported.
 */

use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolStatus;
use App\Support\Downloads\Telemetry\SpoolWriter;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Telemetry\SpoolProcess;

const TRL_MINUTE = 29_379_840; // 2025-11-10 16:00 UTC

function trlConfigArray(string $root, array $overrides = []): array
{
    return $overrides + [
        'spool' => 'on', 'root' => $root, 'file_max_bytes' => 4 * 1024 * 1024, 'max_files' => 1000, 'disk_reserve_bytes' => 0,
        'lock_budget_us' => 2000, 'ledger_lock_budget_us' => 50_000, 'rotation_retries' => 3, 'ledger_slots' => 60, 'ledger_shards' => 16,
        'slow_write_us' => 60_000_000, 'breaker_fault_threshold' => 5, 'breaker_open_seconds' => 120, 'seal_grace_minutes' => 0,
        'seal_lock_budget_us' => 100_000,
    ];
}

function trlWriter(object $test, string $id, array $spec = []): SpoolProcess
{
    return SpoolProcess::start($spec + ['role' => 'writer', 'id' => $id, 'config' => $test->config, 'count' => 1, 'minute' => TRL_MINUTE], $test->barrier);
}

function trlRunAll(object $test, array $processes, bool $gate = true): array
{
    if ($gate) {
        foreach ($processes as $p) {
            $p->waitReady();
        }
        SpoolProcess::go($test->barrier);
    }

    return array_map(fn (SpoolProcess $p) => $p->wait(), $processes);
}

function trlLedger(SpoolConfig $config): array
{
    $snapshot = (new DropLedger($config))->snapshot();

    return $snapshot + [
        'dropped' => array_sum(array_column($snapshot['entries'], 'dropped_events')),
        'reasons' => array_reduce($snapshot['entries'], fn (int $m, array $s) => $m | $s['reasons'], 0),
        'health' => array_sum(array_column($snapshot['entries'], 'health_faults')),
    ];
}

function trlBreakerOpen(SpoolConfig $config, float $now): bool
{
    clearstatcache(true, $config->breakerPath());
    $until = @filemtime($config->breakerPath());

    return $until !== false && $until > $now;
}

/** Every drop is either in the ledger or explicitly reported as unrecorded — never silently missing. */
function trlAssertNoSilentUndercount(SpoolConfig $config, array $results): void
{
    $dropped = array_sum(array_column($results, 'dropped'));
    $unrecorded = array_sum(array_column($results, 'unrecorded'));

    expect(trlLedger($config)['dropped'] + $unrecorded)->toBe($dropped);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-spoolr-'.bin2hex(random_bytes(5));
    $this->barrier = $this->root.'-barrier';
    mkdir($this->barrier, 0775, true);
    $this->config = trlConfigArray($this->root);
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
    foreach ([$this->root, $this->barrier] as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        // Restore permissions on everything first (a test makes a directory read-only), then delete.
        @chmod($dir, 0775);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
            @chmod($file->getPathname(), 0775);
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
});

// --- 1. Circuit breaker classification ----------------------------------------------------------------------------

it('sustained file_full under 32 writers never opens the breaker (capacity is not a health fault)', function () {
    $this->config = trlConfigArray($this->root, ['file_max_bytes' => 8 * 1024]);
    $config = SpoolConfig::fromArray($this->config);
    $results = trlRunAll($this, array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 200, 'gate' => true, 'pad' => 60]), range(0, 31)));

    $ledger = trlLedger($config);
    expect(array_sum(array_column($results, 'dropped')))->toBeGreaterThan(5000)          // thousands of capacity drops …
        ->and($ledger['reasons'] & DropReason::FILE_FULL)->not->toBe(0)
        ->and($ledger['reasons'] & DropReason::BREAKER_OPEN)->toBe(0)                    // … and the breaker never opened
        ->and($ledger['health'])->toBe(0)
        ->and(trlBreakerOpen($config, TRL_MINUTE * 60 + 1.0))->toBeFalse()
        ->and(array_sum(array_map(fn ($r) => $r['stats']['breaker_trips'], $results)))->toBe(0);
    trlAssertNoSilentUndercount($config, $results);
});

it('global spool exhaustion (file_count) never opens the breaker', function () {
    $this->config = trlConfigArray($this->root, ['max_files' => 2]);
    $config = SpoolConfig::fromArray($this->config);
    $results = trlRunAll($this, array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 50, 'gate' => true, 'minute' => TRL_MINUTE + ($i % 8)]), range(0, 15)));

    $ledger = trlLedger($config);
    expect($ledger['reasons'] & DropReason::FILE_COUNT)->not->toBe(0)
        ->and(array_sum(array_column($results, 'dropped')))->toBeGreaterThan(5)
        ->and($ledger['reasons'] & DropReason::BREAKER_OPEN)->toBe(0)
        ->and($ledger['health'])->toBe(0)
        ->and(SpoolWriter::spoolFileCount($config))->toBe(2);
    trlAssertNoSilentUndercount($config, $results);
});

it('heavy lock contention (hundreds of lock timeouts) never opens the breaker', function () {
    $this->config = trlConfigArray($this->root, ['lock_budget_us' => 200]);
    $config = SpoolConfig::fromArray($this->config);
    $holder = trlWriter($this, 'holder', ['pause' => ['after_lock']]);
    $holder->waitReached('after_lock');                                         // the minute file is locked throughout

    $blocked = trlRunAll($this, array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 50, 'gate' => true]), range(0, 7)));
    $holder->release('after_lock');
    $results = [...$blocked, $holder->wait()];

    $ledger = trlLedger($config);
    expect(array_sum(array_map(fn ($r) => $r['stats']['dropped_by_reason']['lock_timeout'] ?? 0, $blocked)))->toBe(400)   // 80× the threshold
        ->and($ledger['health'])->toBe(0)
        ->and($ledger['reasons'] & DropReason::BREAKER_OPEN)->toBe(0)
        ->and(trlBreakerOpen($config, TRL_MINUTE * 60 + 1.0))->toBeFalse();
    trlAssertNoSilentUndercount($config, $results);
});

it('genuine I/O failures open the breaker; it drops while open and recovers when it expires', function () {
    // RLIMIT_FSIZE = 16 × 512 bytes for this child: spool writes beyond 8 KiB fail (EFBIG) — real I/O errors.
    $this->config = trlConfigArray($this->root, ['breaker_fault_threshold' => 5, 'breaker_open_seconds' => 120, 'ledger_shards' => 1]);
    $config = SpoolConfig::fromArray($this->config);
    $failing = SpoolProcess::start(['role' => 'writer', 'id' => 'io', 'config' => $this->config, 'count' => 200, 'minute' => TRL_MINUTE,
        'pad' => 60, 'no_xfsz' => true], $this->barrier, 16)->wait();

    $ledger = trlLedger($config);
    expect($failing['survived'])->toBeTrue()
        ->and($failing['stats']['breaker_trips'])->toBe(1)
        ->and($failing['stats']['dropped_by_reason']['io_error'] ?? 0)->toBe(5)                   // exactly the threshold …
        ->and($failing['stats']['dropped_by_reason']['breaker_open'] ?? 0)->toBeGreaterThan(100)  // … then everything is shed
        ->and($ledger['health'])->toBe(5)
        ->and(trlBreakerOpen($config, TRL_MINUTE * 60 + 1.0))->toBeTrue();
    trlAssertNoSilentUndercount($config, [$failing]);

    // Recovery: two simulated minutes later (> 120 s) the breaker has expired and a healthy writer writes again.
    $healthy = trlWriter($this, 'healthy', ['count' => 3, 'minute' => TRL_MINUTE + 3])->wait();
    expect($healthy['written'])->toHaveCount(3)->and(trlBreakerOpen($config, (TRL_MINUTE + 3) * 60 + 1.0))->toBeFalse();
});

it('slow successful writes are health faults and do open the breaker', function () {
    $this->config = trlConfigArray($this->root, ['slow_write_us' => 1, 'breaker_fault_threshold' => 3]);
    $config = SpoolConfig::fromArray($this->config);
    $result = trlWriter($this, 'slow', ['count' => 10])->wait();

    expect($result['written'])->toHaveCount(3)
        ->and($result['stats']['breaker_trips'])->toBe(1)
        ->and($result['stats']['dropped_by_reason']['breaker_open'] ?? 0)->toBe(7)
        ->and(trlLedger($config)['health'])->toBe(3);
});

// --- 2. Drop-ledger reliability -----------------------------------------------------------------------------------

it('ledger contention: 32 processes dropping simultaneously are all recorded, with no ledger failures', function () {
    // disk_free 0 and a reserve: no file can ever be created, so EVERY event is a drop that must be recorded.
    $this->config = trlConfigArray($this->root, ['disk_reserve_bytes' => 1024]);
    $config = SpoolConfig::fromArray($this->config);
    $results = trlRunAll($this, array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 300, 'gate' => true, 'disk_free' => 0]), range(0, 31)));

    $ledger = trlLedger($config);
    expect(array_sum(array_column($results, 'dropped')))->toBe(32 * 300)
        ->and($ledger['dropped'])->toBe(32 * 300)                                         // exact
        ->and(array_sum(array_map(fn ($r) => $r['stats']['ledger_failed'], $results)))->toBe(0)
        ->and(array_sum(array_column($results, 'unrecorded')))->toBe(0)
        ->and($ledger['torn_slots'])->toBe(0)
        ->and(SpoolStatus::collect($config, 60, TRL_MINUTE * 60 + 1.0)['accounting_exact'])->toBeTrue();
    fwrite(STDERR, sprintf("\n[ledger contention 32×300 drops] shard-lock retries %d, max ledger wait %.1f ms\n",
        array_sum(array_column($results, 'ledger_spins')), max(array_map(fn ($r) => $r['stats']['ledger_ns_max'], $results)) / 1e6));
});

it('SIGKILL while holding a shard lock: the lock is released, nothing is torn, others stay exact; the killed drop is the documented ≤ 1 loss', function (int $signal) {
    $this->config = trlConfigArray($this->root, ['disk_reserve_bytes' => 1024, 'ledger_shards' => 2]);
    $config = SpoolConfig::fromArray($this->config);

    $victim = trlWriter($this, 'victim', ['disk_free' => 0, 'pause' => ['ledger_locked']]);
    $victim->waitReached('ledger_locked');                                      // decided to drop; shard locked; nothing written
    $victim->signal($signal);

    $others = trlRunAll($this, array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 100, 'gate' => true, 'disk_free' => 0]), range(0, 3)));
    $ledger = trlLedger($config);

    expect($ledger['dropped'])->toBe(400)                                       // survivors exact; the victim's 1 drop missing
        ->and($ledger['torn_slots'])->toBe(0)
        ->and(array_sum(array_map(fn ($r) => $r['stats']['ledger_failed'], $others)))->toBe(0);
})->with(['SIGKILL' => [9], 'SIGTERM' => [15]]);

it('a counter file that cannot be updated: the drop is held, retried, then reported in the error log — never silently lost', function () {
    $this->config = trlConfigArray($this->root, ['disk_reserve_bytes' => 1024]);   // every event becomes a drop
    $config = SpoolConfig::fromArray($this->config);
    $first = trlWriter($this, 'first', ['count' => 1, 'disk_free' => 0, 'minute' => TRL_MINUTE])->wait();   // creates a shard
    foreach (glob($this->root.'/drops/*.bin') as $shard) {
        chmod($shard, 0444);                                                    // the counters can no longer be written
    }
    chmod($this->root.'/drops', 0555);                                          // nor recreated
    if (is_writable(glob($this->root.'/drops/*.bin')[0])) {
        $this->markTestSkipped('running as a user that ignores file permissions');
    }

    $result = trlWriter($this, 'blocked', ['count' => 20, 'disk_free' => 0])->wait();
    $stderr = (string) file_get_contents($this->barrier.'/blocked.err');

    expect($result['dropped'])->toBe(20)
        ->and($result['stats']['ledger_failed'])->toBe(20)
        ->and($result['pending_before_flush'])->toBe(20)                        // held in memory, merged, retried …
        ->and($result['unrecorded'])->toBe(20)                                  // … and finally reported
        ->and($stderr)->toContain('W2A_TELEMETRY_UNRECORDED_DROPS events=20');
    trlAssertNoSilentUndercount($config, [$first, $result]);
});

it('detects a torn ledger slot (CRC mismatch) and marks that minute uncertain instead of trusting it', function () {
    $this->config = trlConfigArray($this->root, ['disk_reserve_bytes' => 1024, 'ledger_shards' => 1]);
    $config = SpoolConfig::fromArray($this->config);
    trlWriter($this, 'w', ['count' => 5, 'disk_free' => 0])->wait();

    $shard = $config->ledgerShardPath(0);
    $offset = DropLedger::HEADER_BYTES + (TRL_MINUTE % 60) * DropLedger::SLOT_BYTES + 4;                                  // inside the slot's dropped_events field
    $h = fopen($shard, 'r+b');
    fseek($h, $offset);
    fwrite($h, pack('N', 3));                                                   // a torn / partial update
    fclose($h);

    $snapshot = (new DropLedger($config))->snapshot();
    $status = SpoolStatus::collect($config, 60, TRL_MINUTE * 60 + 1.0);
    expect($snapshot['torn_slots'])->toBe(1)
        ->and($snapshot['entries'][0]['uncertain'])->toBeTrue()
        ->and($status['accounting_exact'])->toBeFalse()
        ->and($status['accounting_uncertainty'])->toHaveKey('torn_ledger_slots');

    // Counter consistency after recovery: the next update keeps the (lower-bound) value, flags it, and re-checksums it.
    trlWriter($this, 'w2', ['count' => 2, 'disk_free' => 0])->wait();
    $after = (new DropLedger($config))->snapshot();
    expect($after['entries'][0]['dropped_events'])->toBe(5)                    // 3 (torn) + 2
        ->and($after['entries'][0]['uncertain'])->toBeTrue();
});

it('flags a reboot / power-loss window: minutes between the previous boot\'s last update and the new boot are uncertain', function () {
    $this->config = trlConfigArray($this->root, ['disk_reserve_bytes' => 1024, 'ledger_shards' => 1]);
    $config = SpoolConfig::fromArray($this->config);
    $writer = fn (int $minute) => new SpoolWriter($config, fn (): float => $minute * 60 + 1.0, null, fn (string $d): float => 0.0);

    DropLedger::overrideBootIdForTesting(str_repeat('a', 32));
    $writer(TRL_MINUTE)->write(['t' => 1.0, 'ev' => 'capacity_rejected', 'mod' => 'k']);
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(str_repeat('b', 32));                   // "after a reboot"
    $writer(TRL_MINUTE + 3)->write(['t' => 1.0, 'ev' => 'capacity_rejected', 'mod' => 'k']);
    DropLedger::resetProcessStateForTesting();
    $writer(TRL_MINUTE + 4)->write(['t' => 1.0, 'ev' => 'capacity_rejected', 'mod' => 'k']);

    $snapshot = (new DropLedger($config))->snapshot();
    $byMinute = array_column($snapshot['entries'], 'uncertain', 'minute');
    expect($snapshot['reboots'])->toBe([['shard' => 0, 'detected_minute' => TRL_MINUTE + 3, 'previous_last_minute' => TRL_MINUTE]])
        ->and($byMinute)->toBe([TRL_MINUTE => true, TRL_MINUTE + 3 => true, TRL_MINUTE + 4 => false])
        ->and(SpoolStatus::collect($config, 60, (TRL_MINUTE + 4) * 60 + 1.0)['accounting_exact'])->toBeFalse();
});

it('after kills, failures and recovery, the spool itself is still consistent: no duplicates, no writes into sealed batches, within bounds', function () {
    $this->config = trlConfigArray($this->root, ['file_max_bytes' => 64 * 1024, 'max_files' => 8]);
    $config = SpoolConfig::fromArray($this->config);
    $victim = trlWriter($this, 'victim', ['count' => 5, 'pause' => ['mid_write']]);
    $victim->waitReached('mid_write');
    $victim->kill();
    $sealer = SpoolProcess::start(['role' => 'sealer', 'id' => 'sealer', 'config' => $this->config, 'loop' => true, 'all' => true, 'interval_us' => 2000, 'gate' => true], $this->barrier);
    $writers = array_map(fn ($i) => trlWriter($this, "w{$i}", ['count' => 300, 'gate' => true, 'pad' => 40]), range(0, 15));
    foreach ([...$writers, $sealer] as $p) {
        $p->waitReady();
    }
    SpoolProcess::go($this->barrier);
    $writerResults = array_map(fn (SpoolProcess $w) => $w->wait(), $writers);
    touch($this->barrier.'/stop');
    $sealed = $sealer->wait()['sealed'];

    $ids = [];
    $bytes = 0;
    foreach ([$config->activeDir(), $config->sealedDir(), $config->quarantineDir()] as $dir) {
        foreach (glob($dir.'/*.ndjson') ?: [] as $file) {
            $bytes += BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$ids) {
                $ids[] = $e->toArray()['id'];
            })['bytes'];
        }
    }
    $written = array_merge(...array_map(fn ($r) => $r['written'], $writerResults));
    foreach ($sealed as $batch) {
        expect(filesize($batch['path']))->toBe($batch['bytes']);
    }
    expect(count($ids))->toBe(count(array_unique($ids)))
        ->and(array_values(array_diff($ids, $written)))->toBe([])                // nothing but what was reported written …
        ->and(array_values(array_diff($written, $ids)))->toBe([])                // … and all of it
        ->and($bytes)->toBeLessThanOrEqual($config->spoolBoundBytes());
    trlAssertNoSilentUndercount($config, $writerResults);
});

it('qualifies accounting_exact: it covers recorded counters only and says so; the command never claims "exact"', function () {
    config(['download_telemetry.spool' => 'on', 'download_telemetry.root' => $this->root]);
    $status = SpoolStatus::collect(SpoolConfig::fromLaravel());

    expect($status['accounting_exact'])->toBeTrue()
        ->and($status['accounting_exact_scope'])->toContain('recorded drop counters only')->toContain('not detectable');

    Artisan::call('downloads:telemetry-spool-status');
    $output = Artisan::output();
    expect($output)->toContain('no known uncertainty')->toContain('does not rule out')
        ->not->toContain('Drop accounting: exact');
});
