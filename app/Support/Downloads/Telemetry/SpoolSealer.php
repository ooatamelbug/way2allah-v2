<?php

namespace App\Support\Downloads\Telemetry;

use Closure;
use Throwable;

/**
 * Seals finished minute files: `active/{minute}.ndjson` →
 * `sealed/{batch_id}.ndjson`, where `batch_id` = `{minute}-{sealMicros}-{random}`
 * is assigned ONCE, here, and is the batch's identity forever after (Phase 2's
 * ledger key).
 *
 * Protocol (see SpoolWriter): only one sealer runs at a time (`seal.lock`).
 * For each file it takes `flock(LOCK_EX)` on the file itself — the lock every
 * writer holds while writing — so it can never rename a file in the middle of
 * a write. It queues for that lock (FileLock::acquireQueued, so a stream of
 * writers cannot starve it) for at most `seal_lock_budget_us` (rounded up to
 * whole seconds) and otherwise leaves the file for the next run. It re-checks that the path still names the inode it
 * locked, fsyncs, and renames WHILE STILL HOLDING the lock; a writer waiting
 * for that lock then sees the path no longer names its inode and reopens.
 *
 * After sealing, a batch containing any record of a newer schema version is
 * moved to `quarantine/` (kept for newer code; never misread, never deleted).
 * A crash between rename and inspection leaves the batch in `sealed/`; the
 * consumer (Phase 2) inspects every batch again before applying it.
 */
final class SpoolSealer
{
    /**
     * @param  (Closure(): float)|null  $clock
     * @param  (Closure(string, array<string, mixed>): void)|null  $hook  tests only
     */
    public function __construct(
        private readonly SpoolConfig $config,
        private readonly ?Closure $clock = null,
        private readonly ?Closure $hook = null,
    ) {}

    /**
     * Seal every active file whose minute ended at least `seal_grace_minutes` ago (or every active file with `$all`).
     *
     * @return array{busy: bool, sealed: list<array{batch_id: string, minute: int, bytes: int, path: string}>, quarantined: list<string>, deferred: list<string>, errors: list<string>}
     */
    public function seal(bool $all = false): array
    {
        $report = ['busy' => false, 'sealed' => [], 'quarantined' => [], 'deferred' => [], 'errors' => []];

        if (! is_dir($this->config->activeDir())) {
            return $report;
        }
        foreach ([$this->config->sealedDir(), $this->config->quarantineDir()] as $dir) {
            if (! is_dir($dir) && ! @mkdir($dir, $this->config->dirMode, true) && ! is_dir($dir)) {
                $report['errors'][] = 'cannot create '.basename($dir);

                return $report;
            }
        }

        $sealLock = @fopen($this->config->sealLockPath(), 'c');
        if ($sealLock === false) {
            $report['errors'][] = 'cannot open seal.lock';

            return $report;
        }

        try {
            if (! flock($sealLock, LOCK_EX | LOCK_NB)) {
                $report['busy'] = true;

                return $report;
            }

            try {
                $current = intdiv((int) $this->now(), 60);
                $files = [];
                foreach (@scandir($this->config->activeDir()) ?: [] as $name) {
                    $minute = str_ends_with($name, '.ndjson') ? SpoolConfig::minuteFromKey(substr($name, 0, -7)) : null;
                    // minute m ended at (m + 1) × 60; it is due once that is at least `grace` minutes ago
                    if ($minute !== null && ($all || $minute <= $current - 1 - $this->config->sealGraceMinutes)) {
                        $files[$minute] = $this->config->activeDir().'/'.$name;
                    }
                }
                ksort($files);

                foreach ($files as $minute => $path) {
                    $this->sealOne($minute, $path, $report);
                }
            } finally {
                flock($sealLock, LOCK_UN);
            }
        } finally {
            fclose($sealLock);
        }

        return $report;
    }

    /**
     * @param  array{busy: bool, sealed: list<array{batch_id: string, minute: int, bytes: int, path: string}>, quarantined: list<string>, deferred: list<string>, errors: list<string>}  $report
     */
    private function sealOne(int $minute, string $path, array &$report): void
    {
        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            return; // already gone
        }

        $target = null;
        $batchId = null;

        try {
            if (! FileLock::acquireQueued($handle, $this->config->sealLockBudgetUs)) {
                $report['deferred'][] = basename($path);

                return;
            }

            try {
                $this->checkpoint('sealer_locked', ['path' => $path]);

                if (! SpoolWriter::stillNames($handle, $path)) {
                    return;
                }

                fflush($handle);
                @fsync($handle);
                $size = (int) (fstat($handle)['size'] ?? 0);

                $batchId = self::batchId($minute, $this->now());
                $target = $this->config->sealedDir().'/'.$batchId.'.ndjson';

                $this->checkpoint('sealer_before_rename', ['path' => $path, 'target' => $target]);

                if (! @rename($path, $target)) {
                    $report['errors'][] = 'rename failed: '.basename($path);
                    $target = null;

                    return;
                }

                $this->checkpoint('sealer_after_rename', ['path' => $path, 'target' => $target]);

                $report['sealed'][] = ['batch_id' => $batchId, 'minute' => $minute, 'bytes' => $size, 'path' => $target];
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }

        // Reached only after a successful rename (every other path returned above).
        $this->quarantineIfUnsupported($batchId, $target, $report);
    }

    /**
     * @param  array{busy: bool, sealed: list<array{batch_id: string, minute: int, bytes: int, path: string}>, quarantined: list<string>, deferred: list<string>, errors: list<string>}  $report
     */
    private function quarantineIfUnsupported(string $batchId, string $path, array &$report): void
    {
        try {
            if (BatchInspector::inspect($path)['unsupported'] > 0
                && @rename($path, $this->config->quarantineDir().'/'.$batchId.'.ndjson')) {
                $report['quarantined'][] = $batchId;
            }
        } catch (Throwable) {
            $report['errors'][] = 'inspection failed: '.$batchId;
        }
    }

    /** `{YYYYmmddHHMM}-{sealMicros}-{8 hex}`: unique, sortable, assigned once. */
    public static function batchId(int $minute, float $now): string
    {
        return SpoolConfig::minuteKey($minute).'-'.(int) round($now * 1_000_000).'-'.bin2hex(random_bytes(4));
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
