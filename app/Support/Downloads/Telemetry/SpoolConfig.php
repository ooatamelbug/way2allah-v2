<?php

namespace App\Support\Downloads\Telemetry;

/**
 * The spool's validated settings and paths. Plain values only — no container,
 * no facades — so the writer can run in any PHP process (and in the
 * multi-process tests) exactly as it runs inside a request.
 */
final class SpoolConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly string $root,
        public readonly int $fileMaxBytes,
        public readonly int $maxFiles,
        public readonly int $diskReserveBytes,
        public readonly int $lockBudgetUs,
        public readonly int $ledgerLockBudgetUs,
        public readonly int $rotationRetries,
        public readonly int $ledgerSlots,
        public readonly int $ledgerShards,
        public readonly int $slowWriteUs,
        public readonly int $breakerFaultThreshold,
        public readonly int $breakerOpenSeconds,
        public readonly int $sealGraceMinutes,
        public readonly int $sealLockBudgetUs,
        public readonly int $dirMode = 0750,
        public readonly int $fileMode = 0640,
    ) {}

    /** From `config('download_telemetry')`. */
    public static function fromLaravel(): self
    {
        return self::fromArray((array) config('download_telemetry', []));
    }

    /** @param array<string, mixed> $c */
    public static function fromArray(array $c): self
    {
        $int = static function (string $key, int $default, int $min, int $max) use ($c): int {
            $value = $c[$key] ?? null;
            if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
                $value = (int) $value;
            }

            return is_int($value) && $value >= $min && $value <= $max ? $value : $default;
        };

        $spool = strtolower(trim((string) ($c['spool'] ?? 'off')));
        $root = rtrim((string) ($c['root'] ?? ''), DIRECTORY_SEPARATOR);

        return new self(
            enabled: $spool !== '' && $spool !== 'off' && $root !== '',
            root: $root,
            fileMaxBytes: $int('file_max_bytes', 32 * 1024 * 1024, 4096, 1024 * 1024 * 1024),
            maxFiles: $int('max_files', 32, 1, 100_000),
            diskReserveBytes: $int('disk_reserve_bytes', 2 * 1024 * 1024 * 1024, 0, PHP_INT_MAX),
            lockBudgetUs: $int('lock_budget_us', 2000, 0, 1_000_000),
            ledgerLockBudgetUs: $int('ledger_lock_budget_us', 50_000, 0, 1_000_000),
            rotationRetries: $int('rotation_retries', 3, 0, 20),
            ledgerSlots: $int('ledger_slots', 10080, 2, 1_000_000),
            ledgerShards: $int('ledger_shards', 16, 1, 256),
            slowWriteUs: $int('slow_write_us', 50_000, 1, 60_000_000),
            breakerFaultThreshold: $int('breaker_fault_threshold', 50, 1, 1_000_000),
            breakerOpenSeconds: $int('breaker_open_seconds', 300, 1, 86_400),
            sealGraceMinutes: $int('seal_grace_minutes', 2, 0, 1440),
            sealLockBudgetUs: $int('seal_lock_budget_us', 2_000_000, 0, 60_000_000),
            dirMode: self::mode($c['dir_mode'] ?? null, 0750, 0700),
            fileMode: self::mode($c['file_mode'] ?? null, 0640, 0600),
        );
    }

    /**
     * An octal permission string ("0750") or int. Owner permissions must include `$ownerMinimum` (rwx for
     * directories, rw for files); the "other" class is always cleared — spool files hold raw IP addresses.
     */
    private static function mode(mixed $value, int $default, int $ownerMinimum): int
    {
        if (is_string($value) && preg_match('/^0?[0-7]{3}$/', trim($value)) === 1) {
            $value = intval(trim($value), 8);
        }
        if (! is_int($value) || $value < 0 || $value > 0777 || ($value & $ownerMinimum) !== $ownerMinimum) {
            return $default;
        }

        return $value & 0770;
    }

    /** Apply the spool file mode to a file this process just created (best effort; never fails a write). */
    public function protect(string $path): void
    {
        @chmod($path, $this->fileMode);
    }

    /** The highest number of bytes the spool files (active + sealed + quarantine) can ever occupy. */
    public function spoolBoundBytes(): int
    {
        return $this->maxFiles * $this->fileMaxBytes;
    }

    public function activeDir(): string
    {
        return $this->root.'/active';
    }

    public function sealedDir(): string
    {
        return $this->root.'/sealed';
    }

    public function quarantineDir(): string
    {
        return $this->root.'/quarantine';
    }

    /** Applied batches (Phase 2), kept for replay/debugging under their own retention and size cap. */
    public function doneDir(): string
    {
        return $this->root.'/done';
    }

    public function rollupLockPath(): string
    {
        return $this->root.'/rollup.lock';
    }

    public function activePath(int $minute): string
    {
        return $this->activeDir().'/'.self::minuteKey($minute).'.ndjson';
    }

    public function ledgerShardPath(int $shard): string
    {
        return sprintf('%s/drops/shard-%02d.bin', $this->root, $shard);
    }

    /** Fixed size of all ledger shards together: shards × (header + 40 × slots) bytes. */
    public function ledgerBytes(): int
    {
        return $this->ledgerShards * (DropLedger::HEADER_BYTES + DropLedger::SLOT_BYTES * $this->ledgerSlots);
    }

    public function healthPath(): string
    {
        return $this->root.'/health.bin';
    }

    public function createLockPath(): string
    {
        return $this->root.'/create.lock';
    }

    public function sealLockPath(): string
    {
        return $this->root.'/seal.lock';
    }

    public function breakerPath(): string
    {
        return $this->root.'/breaker';
    }

    /** `YYYYmmddHHMM` (UTC) of a unix minute. */
    public static function minuteKey(int $minute): string
    {
        return gmdate('YmdHi', $minute * 60);
    }

    /** The unix minute of a `YYYYmmddHHMM` key, or null. */
    public static function minuteFromKey(string $key): ?int
    {
        if (preg_match('/^\d{12}$/', $key) !== 1) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!YmdHi', $key, new \DateTimeZone('UTC'));

        return $time === false ? null : intdiv($time->getTimestamp(), 60);
    }
}
