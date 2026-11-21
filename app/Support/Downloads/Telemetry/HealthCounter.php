<?php

namespace App\Support\Downloads\Telemetry;

use Throwable;

/**
 * Per-minute count of genuine writer HEALTH faults — the only input of the circuit breaker (Phase 1.1).
 *
 * Health faults (DropReason::isHealthFault): I/O errors, and successful writes slower than `slow_write_us`.
 * Everything else — lock timeouts, rotation retries, a full minute file, the global file limit, the disk reserve,
 * invalid events, drops while the breaker is open — is contention or capacity, recorded in the DropLedger but never
 * counted here, so a capacity storm or lock contention can never switch telemetry off.
 *
 * `health.bin`: a fixed 60-slot ring of (minute, faults) pairs, pre-allocated, updated in place under its own lock
 * (bounded by `ledger_lock_budget_us`). It is touched only when a health fault happens, so it is never contended in
 * normal operation. If it cannot be updated, the breaker simply does not trip (fail-open).
 */
final class HealthCounter
{
    private const SLOTS = 60;

    private const SLOT_BYTES = 8;

    public function __construct(private readonly SpoolConfig $config) {}

    /** Add one health fault to `minute`; the minute's total afterwards, or null if it could not be recorded. */
    public function add(int $minute): ?int
    {
        $path = $this->config->healthPath();
        $handle = null;

        try {
            $created = ! is_file($path);
            $handle = @fopen($path, 'c+b');
            if ($handle === false) {
                return null;
            }
            if ($created) {
                $this->config->protect($path);
            }
            if (! FileLock::acquire($handle, $this->config->ledgerLockBudgetUs)) {
                return null;
            }

            try {
                if ((int) (fstat($handle)['size'] ?? 0) < self::SLOTS * self::SLOT_BYTES) {
                    fseek($handle, 0);
                    fwrite($handle, str_repeat("\0", self::SLOTS * self::SLOT_BYTES));
                }
                $offset = ($minute % self::SLOTS) * self::SLOT_BYTES;
                fseek($handle, $offset);
                $raw = (string) fread($handle, self::SLOT_BYTES);
                $slot = strlen($raw) === self::SLOT_BYTES ? unpack('Nminute/Nfaults', $raw) : false;
                $faults = is_array($slot) && $slot['minute'] === $minute ? $slot['faults'] + 1 : 1;

                fseek($handle, $offset);
                fwrite($handle, pack('NN', $minute, $faults));

                return $faults;
            } finally {
                flock($handle, LOCK_UN);
            }
        } catch (Throwable) {
            return null;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
