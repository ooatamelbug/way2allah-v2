<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

/** Phase 2 aggregation settings (config/download_telemetry.php), validated. Plain values only. */
final class RollupConfig
{
    public const OFF = 'off';

    public const DRY_RUN = 'dry-run';

    public const ON = 'on';

    private function __construct(
        public readonly string $mode,
        public readonly string $connection,
        public readonly int $timeBudgetSeconds,
        public readonly int $maxBatches,
        public readonly int $doneRetentionHours,
        public readonly int $doneMaxBytes,
        public readonly int $hourGraceSeconds,
        public readonly int $dayGraceSeconds,
        public readonly int $maxWaitHours,
        public readonly int $lookbackHours,
        public readonly bool $dbLock = true,
        public readonly bool $pruneEnabled = false,
        public readonly int $retention5mDays = 35,
        public readonly int $retentionDailyDays = 400,
        public readonly int $retentionIpUaDays = 14,
        public readonly int $retentionSketchDays = 400,
        public readonly int $retentionCoverageDays = 400,
        public readonly int $retentionLedgerDays = 400,
        public readonly int $retentionQuarantineDays = 14,
        public readonly int $pruneBatchRows = 5000,
        public readonly int $pruneMaxRowsPerTable = 200_000,
        public readonly int $staleBatchMaxAgeDays = 13,
        public readonly int $ipUaWarnRowsPerHour = 20_000,
        public readonly int $ipUaCriticalRowsPerHour = 100_000,
        public readonly int $ipUaGrowthRatio = 5,
        public readonly int $ipUaAvgRowBytes = 1000,
        public readonly int $ipUaProjectedWarnBytes = 2 * 1024 * 1024 * 1024,
    ) {}

    /** Events older than this unix time are never applied (stale-batch guard). */
    public function staleBoundary(int $now): int
    {
        return $now - $this->staleBatchMaxAgeDays * 86400;
    }

    /** The shortest retention of any table a batch writes to — the stale boundary must stay below it. */
    public function shortestRetentionDays(): int
    {
        return min($this->retention5mDays, $this->retentionDailyDays, $this->retentionIpUaDays, $this->retentionSketchDays, $this->retentionLedgerDays);
    }

    /**
     * Problems that make pruning unsafe (empty = safe).
     *
     * @return list<string>
     */
    public function retentionProblems(): array
    {
        $problems = [];
        if ($this->staleBatchMaxAgeDays >= $this->shortestRetentionDays()) {
            $problems[] = "stale_batch_max_age_days ({$this->staleBatchMaxAgeDays}) must be below the shortest retention ({$this->shortestRetentionDays()} days)";
        }
        if ($this->retentionCoverageDays * 24 < $this->lookbackHours + 24) {
            $problems[] = 'retention_coverage_days must cover the finalize look-back window';
        }

        return $problems;
    }

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
        $mode = strtolower(trim((string) ($c['aggregate'] ?? self::OFF)));

        return new self(
            mode: in_array($mode, [self::OFF, self::DRY_RUN, self::ON], true) ? $mode : self::OFF,
            connection: (string) ($c['connection'] ?? 'main') ?: 'main',
            timeBudgetSeconds: $int('rollup_time_budget_seconds', 50, 1, 3600),
            maxBatches: $int('rollup_max_batches', 120, 1, 100_000),
            doneRetentionHours: $int('done_retention_hours', 24, 0, 24 * 90),
            doneMaxBytes: $int('done_max_bytes', 256 * 1024 * 1024, 0, PHP_INT_MAX),
            hourGraceSeconds: $int('finalize_hour_grace_seconds', 900, 0, 86_400),
            dayGraceSeconds: $int('finalize_day_grace_seconds', 7200, 0, 7 * 86_400),
            maxWaitHours: $int('finalize_max_wait_hours', 24, 1, 24 * 30),
            lookbackHours: $int('finalize_lookback_hours', 192, 24, 24 * 60),
            dbLock: filter_var($c['rollup_db_lock'] ?? true, FILTER_VALIDATE_BOOLEAN),
            pruneEnabled: strtolower(trim((string) ($c['prune'] ?? 'off'))) === 'on',
            retention5mDays: $int('retention_metrics_5m_days', 35, 1, 3650),
            retentionDailyDays: $int('retention_metrics_daily_days', 400, 1, 36500),
            retentionIpUaDays: $int('retention_ip_ua_hourly_days', 14, 1, 3650),
            retentionSketchDays: $int('retention_sketches_days', 400, 1, 36500),
            retentionCoverageDays: $int('retention_coverage_days', 400, 1, 36500),
            retentionLedgerDays: $int('retention_ledger_days', 400, 1, 36500),
            retentionQuarantineDays: $int('retention_quarantine_days', 14, 1, 36500),
            pruneBatchRows: $int('prune_batch_rows', 5000, 1, 100_000),
            pruneMaxRowsPerTable: $int('prune_max_rows_per_table', 200_000, 1, 100_000_000),
            staleBatchMaxAgeDays: $int('stale_batch_max_age_days', 13, 1, 3650),
            ipUaWarnRowsPerHour: $int('ip_ua_warn_rows_per_hour', 20_000, 1, PHP_INT_MAX),
            ipUaCriticalRowsPerHour: $int('ip_ua_critical_rows_per_hour', 100_000, 1, PHP_INT_MAX),
            ipUaGrowthRatio: $int('ip_ua_growth_ratio', 5, 1, 1000),
            ipUaAvgRowBytes: $int('ip_ua_avg_row_bytes', 1000, 1, 1_000_000),
            ipUaProjectedWarnBytes: $int('ip_ua_projected_warn_bytes', 2 * 1024 * 1024 * 1024, 1, PHP_INT_MAX),
        );
    }
}
