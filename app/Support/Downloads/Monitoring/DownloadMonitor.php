<?php

namespace App\Support\Downloads\Monitoring;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveStatus;
use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Telemetry\Aggregation\IpUaGrowthMonitor;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolStatus;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only download health report (Phase 3B). Every check is bounded and isolated:
 *
 *  - filesystem: one stat per slot file, a capped read of /proc/locks, the spool's own directory listings (bounded by
 *    `max_files`) and drop-ledger shards (fixed size). It never takes a slot lock, a spool lock or a ledger lock;
 *  - database: a handful of indexed range reads over a capped window (the rejection log by `created_at`, the 5-minute
 *    metrics by bucket, the period table by primary key, the batch ledger by `applied_at`, and the existing bounded IP/UA
 *    growth report). No writes, no locks, no table scans;
 *  - a failing check is reported as `unknown` with the exception class — it never stops the other checks.
 *
 * It does not terminate, signal or otherwise touch any process, and it is not scheduled: it runs only when invoked.
 */
final class DownloadMonitor
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const CRITICAL = 'critical';

    public const UNKNOWN = 'unknown';

    public const DISABLED = 'disabled';

    private const RANK = [self::OK => 0, self::DISABLED => 0, self::UNKNOWN => 1, self::WARN => 2, self::CRITICAL => 3];

    /** Upper bound for COUNT queries (a count at the cap is reported as "at least"). */
    private const COUNT_CAP = 100_000;

    /** @param array<string, int|float> $thresholds */
    public function __construct(
        private readonly Connection $db,
        private readonly SpoolConfig $spool,
        private readonly RollupConfig $rollup,
        private readonly array $thresholds,
        private readonly string $slotDirectory,
        private readonly int $slotLimit,
        private readonly bool $telemetryEvents,
        private readonly string $locksPath = '/proc/locks',
        private ?Connection $telemetryDb = null,
        private ?ArchiveConfig $archive = null,
    ) {
        $this->telemetryDb ??= $db;
    }

    private function rollupOn(): bool
    {
        return $this->rollup->mode === RollupConfig::ON;
    }

    public static function fromLaravel(): self
    {
        return new self(
            DB::connection('main'),
            SpoolConfig::fromLaravel(),
            RollupConfig::fromLaravel(),
            (array) config('download_monitor.thresholds', []),
            (string) config('downloads.slot_directory'),
            DownloadCapacityGuard::limit(),
            strtolower(trim((string) config('download_telemetry.events', 'off'))) === 'on',
            '/proc/locks',
            DB::connection(RollupConfig::fromLaravel()->connection),
        );
    }

    /** @return array{overall: string, generated_at: string, window_minutes: int, duration_ms: float, checks: array<string, array<string, mixed>>} */
    public function collect(int $now, int $windowMinutes = 60): array
    {
        $windowMinutes = max(5, min(1440, $windowMinutes));
        $started = microtime(true);
        $spoolStatus = null;

        $checks = [];
        $run = function (string $name, callable $check) use (&$checks) {
            $t = microtime(true);
            try {
                $checks[$name] = $check();
            } catch (Throwable $e) {
                $checks[$name] = ['status' => self::UNKNOWN, 'error' => $e::class];
            }
            $checks[$name]['ms'] = round((microtime(true) - $t) * 1000, 2);
        };

        $run('slots', fn () => $this->slots($now));
        $run('capacity_rejections', fn () => $this->rejections($now, $windowMinutes));
        $run('transfers', fn () => $this->transfers($now, $windowMinutes));
        $run('spool', function () use ($now, $windowMinutes, &$spoolStatus) {
            $spoolStatus = SpoolStatus::collect($this->spool, $windowMinutes, (float) $now);

            return $this->spoolCheck($spoolStatus, $now);
        });
        $run('telemetry_drops', fn () => $this->drops($spoolStatus));
        $run('circuit_breaker', fn () => $this->breaker($spoolStatus));
        $run('pending_batches', fn () => $this->pending($now));
        $run('rollup_lag', fn () => $this->rollupLag($now));
        $run('period_quality', fn () => $this->periods($now));
        $run('ip_ua_growth', fn () => $this->growth($now));
        $run('rejections_archive', fn () => $this->rejectionsArchive($now));

        $overall = self::OK;
        foreach ($checks as $check) {
            $overall = self::RANK[$check['status']] > self::RANK[$overall] ? $check['status'] : $overall;
        }

        return ['overall' => $overall, 'generated_at' => gmdate('c', $now), 'window_minutes' => $windowMinutes,
            'duration_ms' => round((microtime(true) - $started) * 1000, 2), 'checks' => $checks];
    }

    /** @return array<string, mixed> */
    private function slots(int $now): array
    {
        if (! config('downloads.guard')) {
            return ['status' => self::DISABLED, 'reason' => 'download guard disabled'];
        }
        $info = SlotInspector::inspect($this->slotDirectory, $this->slotLimit, (float) $now, $this->locksPath);
        if (! $info['available']) {
            return ['status' => self::UNKNOWN, 'reason' => 'lock table unavailable'] + $info;
        }
        $ratio = $this->slotLimit > 0 ? $info['occupied'] / $this->slotLimit : 0.0;
        $longWarn = count(array_filter($info['holders'], fn ($h) => $h['held_seconds'] >= $this->t('slot_held_warn_seconds', 7200)));
        $longCrit = count(array_filter($info['holders'], fn ($h) => $h['held_seconds'] >= $this->t('slot_held_critical_seconds', 43200)));

        $status = match (true) {
            $ratio >= $this->t('slot_occupancy_critical', 0.95) || $longCrit > 0 => self::CRITICAL,
            $ratio >= $this->t('slot_occupancy_warn', 0.8) || $longWarn > 0 => self::WARN,
            default => self::OK,
        };
        $info['holders'] = array_slice($info['holders'], 0, 20);   // the longest-held 20 only

        return ['status' => $status, 'occupancy_ratio' => round($ratio, 4), 'long_running_warn' => $longWarn, 'long_running_critical' => $longCrit] + $info;
    }

    /** @return array<string, mixed> */
    private function rejections(int $now, int $windowMinutes): array
    {
        $from = $now - $windowMinutes * 60;
        $count = $this->boundedCount(DownloadRejectionRecorder::TABLE, 'created_at', $from);
        $last = $this->db->table(DownloadRejectionRecorder::TABLE)->max('created_at');

        $status = match (true) {
            $count >= $this->t('rejections_critical', 100) => self::CRITICAL,
            $count >= $this->t('rejections_warn', 1) => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'in_window' => $count, 'at_least' => $count >= self::COUNT_CAP,
            'last_rejection_at' => $last === null ? null : gmdate('c', (int) $last)];
    }

    /** Stream failures and client disconnects, from the rolled-up 5-minute metrics (empty unless telemetry runs). @return array<string, mixed> */
    private function transfers(int $now, int $windowMinutes): array
    {
        if (! $this->telemetryEvents || ! $this->spool->enabled || ! $this->rollupOn()) {
            return ['status' => self::DISABLED, 'reason' => 'telemetry events, spool or rollup disabled'];
        }
        $from = intdiv($now - $windowMinutes * 60, 300) * 300;
        $rows = $this->telemetryDb->table(T::METRICS_5M)->where('bucket', '>=', $from)
            ->groupBy('event', 'outcome')->selectRaw('event, outcome, SUM(events) AS n')->get();

        $by = [];
        foreach ($rows as $row) {
            $by[$row->event.($row->outcome === '' ? '' : ':'.$row->outcome)] = (int) $row->n;
        }
        $finished = array_sum(array_filter($by, fn ($k) => str_starts_with($k, 'transfer_finished:'), ARRAY_FILTER_USE_KEY));
        $failures = ($by['transfer_finished:stalled'] ?? 0) + ($by['transfer_finished:read_failed'] ?? 0)
            + ($by['transfer_finished:incomplete'] ?? 0) + ($by['transfer_finished:interrupted'] ?? 0)
            + array_sum(array_filter($by, fn ($k) => str_starts_with($k, 'source_open_failed') || str_starts_with($k, 'source_rejected'), ARRAY_FILTER_USE_KEY));
        $attempts = $finished + array_sum(array_filter($by, fn ($k) => str_starts_with($k, 'source_'), ARRAY_FILTER_USE_KEY));
        $disconnects = $by['transfer_finished:client_aborted'] ?? 0;
        $failureRatio = $attempts > 0 ? $failures / $attempts : 0.0;
        $disconnectRatio = $finished > 0 ? $disconnects / $finished : 0.0;

        $status = match (true) {
            $failures >= $this->t('stream_failures_min', 10) && $failureRatio >= $this->t('stream_failure_ratio_critical', 0.25) => self::CRITICAL,
            $failures >= $this->t('stream_failures_min', 10) && $failureRatio >= $this->t('stream_failure_ratio_warn', 0.05) => self::WARN,
            $disconnects >= $this->t('stream_failures_min', 10) && $disconnectRatio >= $this->t('client_disconnect_ratio_warn', 0.6) => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'source' => 'rolled-up metrics (lag applies)', 'completed' => $by['transfer_finished:completed'] ?? 0,
            'stream_failures' => $failures, 'failure_ratio' => round($failureRatio, 4), 'client_disconnects' => $disconnects,
            'disconnect_ratio' => round($disconnectRatio, 4), 'by_event_outcome' => $by];
    }

    /** @param array<string, mixed> $s @return array<string, mixed> */
    private function spoolCheck(array $s, int $now): array
    {
        if (! $this->spool->enabled) {
            return ['status' => self::DISABLED, 'reason' => 'spool disabled'];
        }
        $free = @disk_free_space(is_dir($this->spool->root) ? $this->spool->root : dirname($this->spool->root));
        $fileRatio = $s['max_files'] > 0 ? $s['total_files'] / $s['max_files'] : 0.0;
        $used = max((float) $s['used_ratio'], $fileRatio);
        $lowDisk = $free !== false && $free < 2 * $this->spool->diskReserveBytes;

        $status = match (true) {
            $used >= $this->t('spool_used_critical', 0.8) || ($free !== false && $free < $this->spool->diskReserveBytes) => self::CRITICAL,
            $used >= $this->t('spool_used_warn', 0.5) || $lowDisk => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'files' => $s['files'], 'total_files' => $s['total_files'], 'max_files' => $s['max_files'],
            'total_bytes' => $s['total_bytes'], 'bound_bytes' => $s['bound_bytes'], 'used_ratio' => round($used, 4),
            'disk_free_bytes' => $free === false ? null : (int) $free, 'disk_reserve_bytes' => $this->spool->diskReserveBytes,
            'oldest_active_minute' => $s['oldest_active_minute'], 'done_bytes' => self::dirBytes($this->spool->doneDir())];
    }

    /** @param array<string, mixed>|null $s @return array<string, mixed> */
    private function drops(?array $s): array
    {
        if (! $this->spool->enabled) {
            return ['status' => self::DISABLED, 'reason' => 'spool disabled'];
        }
        if ($s === null) {
            return ['status' => self::UNKNOWN, 'reason' => 'spool status unavailable'];
        }
        $window = $s['drop_ledger']['window'];
        $status = match (true) {
            $window['dropped_events'] >= $this->t('drops_critical', 1000) => self::CRITICAL,
            $window['dropped_events'] >= $this->t('drops_warn', 1) || ! $s['accounting_exact'] => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'dropped_events' => $window['dropped_events'], 'reasons' => $window['reasons'],
            'health_faults' => $window['health_faults'], 'accounting_exact' => $s['accounting_exact'],
            'accounting_uncertainty' => $s['accounting_uncertainty']];
    }

    /** @param array<string, mixed>|null $s @return array<string, mixed> */
    private function breaker(?array $s): array
    {
        if (! $this->spool->enabled) {
            return ['status' => self::DISABLED, 'reason' => 'spool disabled'];
        }
        if ($s === null) {
            return ['status' => self::UNKNOWN, 'reason' => 'spool status unavailable'];
        }

        return ['status' => $s['breaker_open_until'] === null ? self::OK : self::CRITICAL, 'open_until' => $s['breaker_open_until']];
    }

    /** @return array<string, mixed> */
    private function pending(int $now): array
    {
        if (! $this->spool->enabled) {
            return ['status' => self::DISABLED, 'reason' => 'spool disabled'];
        }
        $count = 0;
        $oldest = null;
        foreach (@scandir($this->spool->sealedDir()) ?: [] as $name) {
            if (str_ends_with($name, '.ndjson')) {
                $count++;
                $mtime = @filemtime($this->spool->sealedDir().'/'.$name);
                $oldest = $mtime === false ? $oldest : min($oldest ?? $mtime, $mtime);
            }
        }
        $age = $oldest === null ? 0 : max(0, $now - $oldest);
        $status = match (true) {
            $count >= $this->t('pending_batches_critical', 20) || $age >= $this->t('pending_age_critical_seconds', 3600) => self::CRITICAL,
            $count >= $this->t('pending_batches_warn', 5) || $age >= $this->t('pending_age_warn_seconds', 600) => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'sealed_batches' => $count, 'oldest_sealed_age_seconds' => $oldest === null ? null : $age,
            'quarantined' => count(array_filter(@scandir($this->spool->quarantineDir()) ?: [], fn ($n) => str_ends_with($n, '.ndjson')))];
    }

    /** @return array<string, mixed> */
    private function rollupLag(int $now): array
    {
        if (! $this->spool->enabled || ! $this->rollupOn()) {
            return ['status' => self::DISABLED, 'reason' => 'spool or rollup disabled'];
        }
        $last = $this->telemetryDb->table(T::BATCHES)->max('applied_at');
        $oldestPending = null;
        foreach (@scandir($this->spool->sealedDir()) ?: [] as $name) {
            if (str_ends_with($name, '.ndjson') && ($m = @filemtime($this->spool->sealedDir().'/'.$name)) !== false) {
                $oldestPending = min($oldestPending ?? $m, $m);
            }
        }
        // Lag = how long sealed work has waited. An idle site with nothing pending has no lag, however old the last batch.
        $lag = $oldestPending === null ? 0 : max(0, $now - $oldestPending);
        $status = match (true) {
            $lag >= $this->t('rollup_lag_critical_seconds', 3600) => self::CRITICAL,
            $lag >= $this->t('rollup_lag_warn_seconds', 900) => self::WARN,
            default => self::OK,
        };

        return ['status' => $status, 'lag_seconds' => $lag, 'last_applied_at' => $last === null ? null : gmdate('c', (int) $last)];
    }

    /** @return array<string, mixed> */
    private function periods(int $now): array
    {
        if (! $this->rollupOn()) {
            return ['status' => self::DISABLED, 'reason' => 'rollup disabled'];
        }
        $from = intdiv($now, 86400) * 86400 - 7 * 86400;
        $states = [];
        foreach ($this->telemetryDb->table(T::PERIODS)->where('granularity', 'day')->where('period_start', '>=', $from)
            ->groupBy('state')->selectRaw('state, COUNT(*) AS n')->get() as $row) {
            $states[(string) $row->state] = (int) $row->n;
        }
        $incomplete = $this->telemetryDb->table(T::PERIODS)->where('granularity', 'day')->where('period_start', '>=', $from)
            ->where('state', 'incomplete')->orderByDesc('period_start')->limit(7)->pluck('period_start')
            ->map(fn ($d) => gmdate('Y-m-d', (int) $d))->all();

        return ['status' => $incomplete === [] ? self::OK : self::WARN, 'last_7_days' => $states, 'incomplete_days' => $incomplete];
    }

    /** @return array<string, mixed> */
    private function growth(int $now): array
    {
        if (! $this->rollupOn()) {
            return ['status' => self::DISABLED, 'reason' => 'rollup disabled'];
        }
        $report = (new IpUaGrowthMonitor($this->telemetryDb, $this->rollup))->report($now, 24);
        $warnings = (array) ($report['warnings'] ?? []);

        return ['status' => in_array('rows_per_hour_critical', $warnings, true) ? self::CRITICAL : ($warnings === [] ? self::OK : self::WARN),
            'warnings' => $warnings] + array_intersect_key($report, array_flip(['latest_full_hour_rows', 'median_earlier_hours', 'projected_bytes_at_retention', 'actual_table_bytes']));
    }

    /**
     * Phase 3D archive health: last successful run and its age, oldest active rejection, backlog past the cutoff,
     * verification failures, failed / interrupted runs, archive storage, expired archives still present.
     *
     * @return array<string, mixed>
     */
    private function rejectionsArchive(int $now): array
    {
        $config = $this->archive ??= ArchiveConfig::fromLaravel();
        if (! $config->enabled) {
            return ['status' => self::DISABLED, 'reason' => 'rejection archiving disabled'];
        }
        $s = ArchiveStatus::collect($config, $this->db, $now);
        $lastAge = $s['last_successful_run']['age_seconds'] ?? null;
        $stale = array_filter($s['incomplete_runs'], fn ($r) => $r['error'] !== null || $r['updated_seconds_ago'] >= $config->staleRunHours * 3600);
        $day = 86400;

        $problems = [];
        $status = self::OK;
        $raise = function (string $level, string $problem) use (&$status, &$problems) {
            $problems[] = $problem;
            $status = self::RANK[$level] > self::RANK[$status] ? $level : $status;
        };
        if ($s['verify_failed'] !== []) {
            $raise(self::CRITICAL, 'verification_failed');
        }
        if ($lastAge === null) {
            $raise(self::WARN, 'no_successful_run_yet');
        } elseif ($lastAge >= $config->criticalAfterDays * $day) {
            $raise(self::CRITICAL, 'no_successful_run_for_'.$config->criticalAfterDays.'_days');
        } elseif ($lastAge >= $config->warnAfterDays * $day) {
            $raise(self::WARN, 'no_successful_run_for_'.$config->warnAfterDays.'_days');
        }
        if ($stale !== []) {
            $raise(self::WARN, 'failed_or_interrupted_run');
        }
        if ($s['oldest_active_age_days'] !== null && $s['oldest_active_age_days'] > $config->activeRetentionDays) {
            $raise(self::WARN, 'active_retention_exceeded');
        }
        if ($s['expired_backlog'] !== []) {
            $raise(self::WARN, 'expired_archives_present');
        }
        if ($s['awaiting_backup'] !== []) {
            $raise(self::WARN, 'awaiting_backup_confirmation');   // deletion from the live table is waiting for an operator
        }

        return ['status' => $status, 'problems' => $problems, 'last_successful_run' => $s['last_successful_run'],
            'archive_age_days' => $lastAge === null ? null : round($lastAge / $day, 2), 'oldest_active_rejection' => $s['oldest_active_rejection'],
            'oldest_active_age_days' => $s['oldest_active_age_days'], 'backlog_rows' => $s['backlog_rows'], 'verify_failed' => $s['verify_failed'],
            'incomplete_runs' => $s['incomplete_runs'], 'archive_count' => $s['archive_count'], 'archive_bytes' => $s['archive_bytes'],
            'expired_backlog' => $s['expired_backlog'], 'awaiting_backup' => $s['awaiting_backup']];
    }

    private function boundedCount(string $table, string $column, int $from): int
    {
        return (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM (SELECT 1 FROM '.$table.' WHERE '.$column.' >= ? LIMIT '.self::COUNT_CAP.') AS bounded', [$from])->c;
    }

    private function t(string $key, int|float $default): int|float
    {
        $value = $this->thresholds[$key] ?? null;

        return is_int($value) || is_float($value) ? $value : $default;
    }

    private static function dirBytes(string $dir): int
    {
        $bytes = 0;
        $n = 0;
        foreach (@scandir($dir) ?: [] as $name) {
            if ($name !== '.' && $name !== '..' && ++$n <= 10_000) {
                $bytes += (int) @filesize($dir.'/'.$name);
            }
        }

        return $bytes;
    }
}
