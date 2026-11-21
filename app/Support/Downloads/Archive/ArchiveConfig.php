<?php

namespace App\Support\Downloads\Archive;

/** Validated settings for the `download_rejections` archive (Phase 3D). */
final class ArchiveConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $directory,
        public readonly int $frequencyDays = 7,
        public readonly int $activeRetentionDays = 20,
        public readonly int $cutoffDays = 13,
        public readonly int $retentionMonths = 4,
        public readonly int $chunkRows = 5000,
        public readonly int $maxRowsPerRun = 2_000_000,
        public readonly int $deletePauseMs = 20,
        public readonly int $minFreeBytes = 512 * 1024 * 1024,
        public readonly int $expireMaxPerRun = 100,
        public readonly bool $requireBackupConfirmation = true,
        public readonly int $dirMode = 0750,
        public readonly int $fileMode = 0640,
        public readonly int $warnAfterDays = 8,
        public readonly int $criticalAfterDays = 14,
        public readonly int $staleRunHours = 24,
        public readonly string $connection = 'main',
    ) {}

    public static function fromLaravel(): self
    {
        return self::fromArray((array) config('download_rejections.archive', []));
    }

    /** @param array<string, mixed> $c */
    public static function fromArray(array $c): self
    {
        $int = static function (string $key, int $default, int $min, int $max) use ($c): int {
            $v = $c[$key] ?? null;
            if (is_string($v) && preg_match('/^\d+$/', $v) === 1) {
                $v = (int) $v;
            }

            return is_int($v) && $v >= $min && $v <= $max ? $v : $default;
        };
        $mode = static function (mixed $v, int $default, int $owner): int {
            if (is_string($v) && preg_match('/^0?[0-7]{3}$/', trim($v)) === 1) {
                $v = intval(trim($v), 8);
            }

            return is_int($v) && ($v & $owner) === $owner ? $v & 0770 : $default;   // never "other"
        };

        return new self(
            enabled: filter_var($c['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            directory: rtrim((string) ($c['directory'] ?? ''), DIRECTORY_SEPARATOR),
            frequencyDays: $int('frequency_days', 7, 1, 365),
            activeRetentionDays: $int('active_retention_days', 20, 1, 3650),
            cutoffDays: $int('cutoff_days', 13, 0, 3650),
            retentionMonths: $int('retention_months', 4, 1, 1200),
            chunkRows: $int('chunk_rows', 5000, 1, 50_000),
            maxRowsPerRun: $int('max_rows_per_run', 2_000_000, 1, 100_000_000),
            deletePauseMs: $int('delete_pause_ms', 20, 0, 10_000),
            minFreeBytes: $int('min_free_bytes', 512 * 1024 * 1024, 0, PHP_INT_MAX),
            expireMaxPerRun: $int('expire_max_archives_per_run', 100, 1, 100_000),
            requireBackupConfirmation: filter_var($c['require_backup_confirmation'] ?? true, FILTER_VALIDATE_BOOL),
            dirMode: $mode($c['dir_mode'] ?? null, 0750, 0700),
            fileMode: $mode($c['file_mode'] ?? null, 0640, 0600),
            warnAfterDays: $int('warn_after_days', 8, 1, 3650),
            criticalAfterDays: $int('critical_after_days', 14, 1, 3650),
            staleRunHours: $int('stale_run_hours', 24, 1, 8760),
            connection: (string) ($c['connection'] ?? 'main') ?: 'main',
        );
    }

    /** Settings that make archiving unsafe or meaningless (empty = fine). @return list<string> */
    public function problems(): array
    {
        $p = [];
        if ($this->directory === '') {
            $p[] = 'archive directory is not configured';
        } elseif ($this->insidePublicRoot()) {
            $p[] = 'archive directory must be outside the public web root';
        }
        if ($this->cutoffDays + $this->frequencyDays > $this->activeRetentionDays) {
            $p[] = "cutoff_days ({$this->cutoffDays}) + frequency_days ({$this->frequencyDays}) exceeds active_retention_days ({$this->activeRetentionDays})";
        }

        return $p;
    }

    public function archivesDir(): string
    {
        return $this->directory.'/archives';
    }

    public function runsDir(): string
    {
        return $this->directory.'/runs';
    }

    public function tmpDir(): string
    {
        return $this->directory.'/tmp';
    }

    public function rejectedDir(): string
    {
        return $this->directory.'/rejected';
    }

    public function lockPath(): string
    {
        return $this->directory.'/archive.lock';
    }

    public function auditLogPath(): string
    {
        return $this->directory.'/audit.log';
    }

    /**
     * Exactly cutoff_days before the run: rows created strictly before this are archived. (Not aligned to a day
     * boundary — alignment would let a late-in-the-day run keep rows up to a day older, breaking the 20-day maximum.)
     */
    public function cutoff(int $now): int
    {
        return $now - $this->cutoffDays * 86400;
    }

    private function insidePublicRoot(): bool
    {
        $public = realpath(public_path());
        $dir = realpath($this->directory) ?: $this->directory;

        return $public !== false && ($dir === $public || str_starts_with($dir.'/', rtrim($public, '/').'/'));
    }
}
