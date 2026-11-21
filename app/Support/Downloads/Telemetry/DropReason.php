<?php

namespace App\Support\Downloads\Telemetry;

/** Why an event was not persisted — bit flags, OR-ed per minute in the drop ledger. */
final class DropReason
{
    public const INVALID_EVENT = 1;

    public const FILE_FULL = 2;          // the minute's file would exceed file_max_bytes

    public const FILE_COUNT = 4;         // max_files reached; no new file may be created

    public const DISK_RESERVE = 8;       // free disk below disk_reserve_bytes

    public const LOCK_TIMEOUT = 16;      // a lock was not obtained within lock_budget_us

    public const ROTATION_RETRIES = 32;  // the file was sealed under the writer too many times in a row

    public const IO_ERROR = 64;          // open/write failed (a partial write is rolled back)

    public const BREAKER_OPEN = 128;     // the circuit breaker is open

    /*
     * Classification policy (Phase 1.1). Only a genuine writer HEALTH failure feeds the circuit breaker:
     *   health      IO_ERROR (and, outside this bit set, a successful write slower than slow_write_us)
     *   contention  LOCK_TIMEOUT, ROTATION_RETRIES — bounded waits under concurrency, not failures
     *   capacity    FILE_FULL, FILE_COUNT, DISK_RESERVE — the configured bounds doing their job
     *   other       INVALID_EVENT (caller bug), BREAKER_OPEN (the breaker's own effect)
     */
    public const HEALTH = self::IO_ERROR;

    public const CONTENTION = self::LOCK_TIMEOUT | self::ROTATION_RETRIES;

    public const CAPACITY = self::FILE_FULL | self::FILE_COUNT | self::DISK_RESERVE;

    public static function isHealthFault(int $reason): bool
    {
        return ($reason & self::HEALTH) !== 0;
    }

    /** @return list<string> */
    public static function names(int $mask): array
    {
        static $bits = null;
        $bits ??= array_filter((new \ReflectionClass(self::class))->getConstants(),
            fn (mixed $bit, string $name) => is_int($bit) && ! in_array($name, ['HEALTH', 'CONTENTION', 'CAPACITY'], true), ARRAY_FILTER_USE_BOTH);

        $names = [];
        foreach ($bits as $name => $bit) {
            if (($mask & $bit) !== 0) {
                $names[] = strtolower($name);
            }
        }

        return $names;
    }
}
