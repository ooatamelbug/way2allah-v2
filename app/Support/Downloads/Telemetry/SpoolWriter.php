<?php

namespace App\Support\Downloads\Telemetry;

use Closure;
use Throwable;

/**
 * Appends telemetry events to the bounded local spool — the ONLY telemetry
 * work on the download hot path. No database, no network, never throws, and
 * never waits for any lock longer than `lock_budget_us`.
 *
 * Every attempted event ends in exactly one of: WRITTEN (one complete line in
 * one active spool file), DROPPED (counted in the sharded DropLedger with its reason; see its documented
 * failure modes),
 * or DISABLED (spool off; nothing touched).
 *
 * ------------------------------------------------------------------
 * Writer / sealer protocol (both lock the SAME inode — the open file)
 * ------------------------------------------------------------------
 * 1. Open `active/{minute}.ndjson` with `r+b` — this never creates a file.
 *    If it does not exist, create it under `create.lock` (counted against
 *    `max_files`, after the disk-reserve check) with `x+b`.
 * 2. `flock(LOCK_EX)` on that handle, within the budget.
 * 3. Rotation check UNDER THE LOCK: the path must still name the inode this
 *    handle has open (`fstat` vs `stat`). The sealer renames a file only while
 *    it holds this same lock, so after a seal the path is gone or names a new
 *    inode: the writer releases, reopens and retries (bounded). Nothing is ever
 *    written into a sealed file, and a sealer can never rename a file while a
 *    writer holds it.
 * 4. Torn-record guard: if the file is non-empty and does not end in "\n"
 *    (a writer was killed mid-write), a "\n" is written first, so the fragment
 *    becomes one isolated malformed line; the repair is counted.
 * 5. Capacity: the line is written only if the file stays within
 *    `file_max_bytes`. A short write is rolled back with ftruncate() to the
 *    previous size, so I/O failure never leaves a torn record.
 * 6. Unlock, close.
 *
 * An event is written at most once: retries happen only before anything is
 * written, and a failed write is rolled back and counted as dropped, never
 * retried.
 */
final class SpoolWriter
{
    public const WRITTEN = 1;

    public const DROPPED = 2;

    public const DISABLED = 3;

    /**
     * This process's counters (observability only; they never change behaviour).
     *
     * @var array{written: int, dropped: int, lock_spins: int, rotations: int, torn_repaired: int, dropped_by_reason: array<string, int>, ledger_calls: int, ledger_ns_total: int, ledger_ns_max: int, ledger_failed: int, breaker_trips: int}
     */
    private array $stats = ['written' => 0, 'dropped' => 0, 'lock_spins' => 0, 'rotations' => 0, 'torn_repaired' => 0,
        'dropped_by_reason' => [], 'ledger_calls' => 0, 'ledger_ns_total' => 0, 'ledger_ns_max' => 0, 'ledger_failed' => 0, 'breaker_trips' => 0];

    private int $lastLatencyNs = 0;

    private readonly DropLedger $ledger;

    /**
     * @param  (Closure(): float)|null  $clock  current unix time (tests)
     * @param  (Closure(string, array<string, mixed>): void)|null  $hook  named synchronisation points (tests only)
     * @param  (Closure(string): (float|false))|null  $diskFree  free bytes for a directory (tests)
     */
    public function __construct(
        private readonly SpoolConfig $config,
        private readonly ?Closure $clock = null,
        private readonly ?Closure $hook = null,
        private readonly ?Closure $diskFree = null,
    ) {
        $this->ledger = new DropLedger($config, $hook);
    }

    public static function fromLaravel(): self
    {
        return new self(SpoolConfig::fromLaravel());
    }

    /**
     * @param  TelemetryEvent|array<string, mixed>  $event
     * @return self::WRITTEN|self::DROPPED|self::DISABLED
     */
    public function write(TelemetryEvent|array $event): int
    {
        if (! $this->config->enabled) {
            return self::DISABLED;
        }

        $started = hrtime(true);
        $minute = 0;
        $bytes = 0;

        try {
            $minute = intdiv((int) $this->now(), 60);

            if ($this->breakerOpen()) {
                return $this->drop($minute, DropReason::BREAKER_OPEN, 0, $started);
            }

            try {
                $line = ($event instanceof TelemetryEvent ? $event : TelemetryEvent::fromArray($event))->encode()."\n";
            } catch (InvalidTelemetryEvent) {
                return $this->drop($minute, DropReason::INVALID_EVENT, 0, $started);
            }
            $bytes = strlen($line);
            $path = $this->config->activePath($minute);

            for ($attempt = 0; $attempt <= $this->config->rotationRetries; $attempt++) {
                $reason = 0;
                $handle = $this->openActive($path, $reason);
                if ($handle === null) {
                    return $this->drop($minute, $reason, $bytes, $started);
                }

                try {
                    $this->checkpoint('after_open', ['path' => $path, 'attempt' => $attempt]);

                    if (! FileLock::acquire($handle, $this->config->lockBudgetUs, $this->stats['lock_spins'])) {
                        return $this->drop($minute, DropReason::LOCK_TIMEOUT, $bytes, $started);
                    }

                    try {
                        $this->checkpoint('after_lock', ['path' => $path, 'attempt' => $attempt]);

                        if (! self::stillNames($handle, $path)) {
                            $this->stats['rotations']++;

                            continue;
                        }

                        return $this->append($handle, $line, $minute, $started);
                    } finally {
                        flock($handle, LOCK_UN);
                    }
                } finally {
                    fclose($handle);
                }
            }

            return $this->drop($minute, DropReason::ROTATION_RETRIES, $bytes, $started);
        } catch (Throwable) {
            return $this->drop($minute, DropReason::IO_ERROR, $bytes, $started);
        }
    }

    /** @return array{written: int, dropped: int, lock_spins: int, rotations: int, torn_repaired: int, dropped_by_reason: array<string, int>, ledger_calls: int, ledger_ns_total: int, ledger_ns_max: int, ledger_failed: int, breaker_trips: int} */
    public function stats(): array
    {
        return $this->stats;
    }

    /** Duration of the last write() call, in nanoseconds. */
    public function lastLatencyNs(): int
    {
        return $this->lastLatencyNs;
    }

    /** True when `$handle` is the inode `$path` currently names. */
    public static function stillNames(mixed $handle, string $path): bool
    {
        $open = fstat($handle);
        clearstatcache(true, $path);
        $named = @stat($path);

        return is_array($open) && is_array($named) && $open['ino'] === $named['ino'] && $open['dev'] === $named['dev'];
    }

    /**
     * @param  resource  $handle  locked, verified current
     * @return self::WRITTEN|self::DROPPED
     */
    private function append(mixed $handle, string $line, int $minute, int $started): int
    {
        $stat = fstat($handle);
        if (! is_array($stat)) {
            return $this->drop($minute, DropReason::IO_ERROR, strlen($line), $started);
        }
        $size = (int) $stat['size'];

        $repair = false;
        if ($size > 0) {
            fseek($handle, $size - 1);
            $repair = fread($handle, 1) !== "\n";
        }
        $payload = ($repair ? "\n" : '').$line;

        if ($size + strlen($payload) > $this->config->fileMaxBytes) {
            return $this->drop($minute, DropReason::FILE_FULL, strlen($line), $started);
        }

        fseek($handle, 0, SEEK_END);
        if (! $this->writeAll($handle, $payload)) {
            @ftruncate($handle, $size);

            return $this->drop($minute, DropReason::IO_ERROR, strlen($line), $started);
        }
        fflush($handle);

        $this->stats['written']++;
        $latencyUs = intdiv(hrtime(true) - $started, 1000);
        $this->lastLatencyNs = hrtime(true) - $started;

        if ($repair) {
            $this->stats['torn_repaired']++;
        }
        if ($repair || $latencyUs >= $this->config->slowWriteUs) {
            $slow = $latencyUs >= $this->config->slowWriteUs;
            $this->recordInLedger($minute, [
                'torn_repaired' => $repair ? 1 : 0,
                'slow_writes' => $slow ? 1 : 0,
                'health_faults' => $slow ? 1 : 0,
                'latency_us' => $latencyUs,
            ]);
            if ($slow) {
                $this->healthFault($minute);   // a slow successful write is a health fault
            }
        }

        return self::WRITTEN;
    }

    /** @param resource $handle */
    private function writeAll(mixed $handle, string $payload): bool
    {
        $length = strlen($payload);

        if ($this->hook !== null && $length > 1) {
            // Test seam: a deliberately split write, so a process can be killed with half a record on disk.
            $half = intdiv($length, 2);
            if (@fwrite($handle, substr($payload, 0, $half)) !== $half) {
                return false;
            }
            fflush($handle);
            $this->checkpoint('mid_write', []);

            return @fwrite($handle, substr($payload, $half)) === $length - $half;
        }

        $written = 0;
        while ($written < $length) {
            $n = @fwrite($handle, substr($payload, $written));
            if ($n === false || $n === 0) {
                return false;
            }
            $written += $n;
        }

        return true;
    }

    /**
     * The active file opened `r+b`, creating it (counted, under create.lock) only when missing.
     *
     * @return resource|null
     */
    private function openActive(string $path, int &$reason): mixed
    {
        $handle = @fopen($path, 'r+b');
        if ($handle !== false) {
            return $handle;
        }

        if (! $this->ensureDirectories()) {
            $reason = DropReason::IO_ERROR;

            return null;
        }

        $lock = @fopen($this->config->createLockPath(), 'c');
        if ($lock === false) {
            $reason = DropReason::IO_ERROR;

            return null;
        }

        try {
            if (! FileLock::acquire($lock, $this->config->lockBudgetUs, $this->stats['lock_spins'])) {
                $reason = DropReason::LOCK_TIMEOUT;

                return null;
            }

            try {
                $this->checkpoint('create_locked', ['path' => $path]);

                $handle = @fopen($path, 'r+b');   // another writer may have created it meanwhile
                if ($handle !== false) {
                    return $handle;
                }

                if (self::spoolFileCount($this->config) >= $this->config->maxFiles) {
                    $reason = DropReason::FILE_COUNT;

                    return null;
                }

                $free = $this->diskFree !== null ? ($this->diskFree)($this->config->root) : @disk_free_space($this->config->root);
                if ($free !== false && $free < $this->config->diskReserveBytes) {
                    $reason = DropReason::DISK_RESERVE;

                    return null;
                }

                $handle = @fopen($path, 'x+b');
                if ($handle === false) {
                    $reason = DropReason::IO_ERROR;

                    return null;
                }
                $this->config->protect($path);   // raw IPs: 0640 by default

                return $handle;
            } finally {
                flock($lock, LOCK_UN);
            }
        } finally {
            fclose($lock);
        }
    }

    /** Spool files counted against max_files: every *.ndjson in active/, sealed/ and quarantine/. */
    public static function spoolFileCount(SpoolConfig $config): int
    {
        $count = 0;
        foreach ([$config->activeDir(), $config->sealedDir(), $config->quarantineDir()] as $dir) {
            foreach (@scandir($dir) ?: [] as $name) {
                if (str_ends_with($name, '.ndjson')) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function ensureDirectories(): bool
    {
        foreach ([$this->config->activeDir(), $this->config->sealedDir(), $this->config->quarantineDir()] as $dir) {
            if (! is_dir($dir) && ! @mkdir($dir, $this->config->dirMode, true) && ! is_dir($dir)) {
                return false;
            }
        }

        return true;
    }

    /** @return self::DROPPED */
    private function drop(int $minute, int $reason, int $bytes, int $started): int
    {
        $this->stats['dropped']++;
        foreach (DropReason::names($reason) as $name) {
            $this->stats['dropped_by_reason'][$name] = ($this->stats['dropped_by_reason'][$name] ?? 0) + 1;
        }
        $this->lastLatencyNs = hrtime(true) - $started;

        try {
            $health = DropReason::isHealthFault($reason);   // contention and capacity never feed the breaker
            $this->recordInLedger($minute, [
                'dropped_events' => 1,
                'dropped_bytes' => $bytes,
                'reason' => $reason,
                'health_faults' => $health ? 1 : 0,
                'latency_us' => intdiv($this->lastLatencyNs, 1000),
            ]);
            if ($health) {
                $this->healthFault($minute);
            }
        } catch (Throwable) {
            // DropLedger::record() never throws; belt and braces — telemetry must never break a download.
        }
        $this->lastLatencyNs = hrtime(true) - $started;   // including the ledger update

        return self::DROPPED;
    }

    /**
     * DropLedger::record(), timed (the extra latency the ledger lock budget can add to a drop or slow write).
     *
     * @param  array{dropped_events?: int, dropped_bytes?: int, reason?: int, torn_repaired?: int, slow_writes?: int, health_faults?: int, latency_us?: int}  $delta
     */
    private function recordInLedger(int $minute, array $delta): bool
    {
        $t0 = hrtime(true);
        try {
            $recorded = $this->ledger->record($minute, $delta);
            if (! $recorded) {
                $this->stats['ledger_failed']++;   // kept in process memory; retried, then flushed at shutdown
            }

            return $recorded;
        } finally {
            $elapsed = hrtime(true) - $t0;
            $this->stats['ledger_calls']++;
            $this->stats['ledger_ns_total'] += $elapsed;
            $this->stats['ledger_ns_max'] = max($this->stats['ledger_ns_max'], $elapsed);
        }
    }

    /** Count one health fault; open the breaker when the minute reaches the threshold. */
    private function healthFault(int $minute): void
    {
        $faults = (new HealthCounter($this->config))->add($minute);
        if ($faults !== null && $faults >= $this->config->breakerFaultThreshold && ! $this->breakerOpen()) {
            if (@touch($this->config->breakerPath(), (int) $this->now() + $this->config->breakerOpenSeconds)) {
                $this->stats['breaker_trips']++;
            }
        }
    }

    /** The breaker is open while its marker's modification time lies in the future. */
    private function breakerOpen(): bool
    {
        $path = $this->config->breakerPath();
        clearstatcache(true, $path);
        $until = @filemtime($path);

        return $until !== false && $until > $this->now();
    }

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true);
    }

    /** @param array<string, mixed> $context */
    private function checkpoint(string $point, array $context): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $context);
        }
    }
}
