<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Database\Connection;

/**
 * Retention for the Phase 2 telemetry tables (Phase 2.2). Disabled by default (`DOWNLOAD_TELEMETRY_PRUNE=off`), never
 * scheduled automatically, and only ever deletes rows OLDER than the approved retention:
 *
 *   download_metrics_5m      bucket       < now − 35 days
 *   download_metrics_daily   day          < now − 400 days
 *   download_ip_ua_hourly    hour         < now − 14 days
 *   download_distinct_sketches day        < now − 400 days
 *   download_telemetry_coverage minute    < now − 400 days
 *   download_telemetry_batches  batch_minute < now − 400 days AND < (oldest spool file's minute − 1 day), applied_at old
 *   download_period_status   never pruned (yet)
 *   spool quarantine/*.ndjson  file mtime < now − 14 days (Phase 3B.1; at most QUARANTINE_FILES_PER_RUN per run)
 *
 * Bounded: deletes in chunks of `prune_batch_rows`, at most `prune_max_rows_per_table` per table per run; a second run
 * continues where the first stopped. Idempotent: rerunning deletes nothing new until more rows age out.
 *
 * Safety: refuses to run when the stale-batch boundary is not below the shortest retention (RollupConfig::
 * retentionProblems()) — that ordering is what guarantees a pruned row can never be recreated, and a pruned ledger
 * row can never let an old batch be applied twice (the stale guard refuses any batch with events older than the
 * boundary). The ledger is additionally never pruned past the oldest batch file still in the spool.
 */
final class TelemetryPruner
{
    public const QUARANTINE_FILES_PER_RUN = 1000;

    public function __construct(
        private readonly Connection $db,
        private readonly SpoolConfig $spool,
        private readonly RollupConfig $config,
    ) {}

    /** @return array{refused: list<string>, dry_run: bool, tables: array<string, array{cutoff: int, eligible: int, deleted: int, capped: bool}>, quarantine?: array{cutoff: int, eligible: int, deleted: int, bytes: int, capped: bool}} */
    public function run(int $now, bool $dryRun): array
    {
        $report = ['refused' => $this->config->retentionProblems(), 'dry_run' => $dryRun, 'tables' => []];
        if ($report['refused'] !== []) {
            return $report;
        }

        $day = 86400;
        $ledgerCutoffMinute = intdiv($now - $this->config->retentionLedgerDays * $day, 60);
        $oldestFile = $this->oldestSpoolFileMinute();
        if ($oldestFile !== null) {
            $ledgerCutoffMinute = min($ledgerCutoffMinute, $oldestFile - 1440);
        }

        $plan = [
            T::METRICS_5M => ['bucket', $now - $this->config->retention5mDays * $day],
            T::METRICS_DAILY => ['day', $now - $this->config->retentionDailyDays * $day],
            T::IP_UA_HOURLY => ['hour', $now - $this->config->retentionIpUaDays * $day],
            T::SKETCHES => ['day', $now - $this->config->retentionSketchDays * $day],
            T::COVERAGE => ['minute', intdiv($now - $this->config->retentionCoverageDays * $day, 60)],
            T::BATCHES => ['batch_minute', $ledgerCutoffMinute],
        ];

        foreach ($plan as $table => [$column, $cutoff]) {
            $query = fn () => $this->db->table($table)->where($column, '<', $cutoff)
                ->when($table === T::BATCHES, fn ($q) => $q->where('applied_at', '<', $now - $this->config->retentionLedgerDays * $day));
            $cap = $this->config->pruneMaxRowsPerTable;
            $eligible = (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM ('.$query()->select($this->db->raw('1'))->limit($cap + 1)->toSql().') AS bounded',
                $query()->getBindings())->c;
            $deleted = 0;

            if (! $dryRun) {
                while ($deleted < $cap) {
                    $n = $query()->limit(min($this->config->pruneBatchRows, $cap - $deleted))->delete();
                    $deleted += $n;
                    if ($n === 0) {
                        break;
                    }
                }
            }
            $report['tables'][$table] = ['cutoff' => $cutoff, 'eligible' => min($eligible, $cap), 'deleted' => $deleted, 'capped' => $eligible > $cap];
        }

        $report['quarantine'] = $this->pruneQuarantine($now, $dryRun);

        return $report;
    }

    /**
     * Quarantined batch files (malformed, unsupported, stale or conflicting — they hold raw IPs) older than the
     * quarantine retention, by file mtime (when the file was quarantined or last written). Only `*.ndjson` directly in
     * quarantine/; contents are never read or logged.
     *
     * @return array{cutoff: int, eligible: int, deleted: int, bytes: int, capped: bool}
     */
    private function pruneQuarantine(int $now, bool $dryRun): array
    {
        $cutoff = $now - $this->config->retentionQuarantineDays * 86400;
        $dir = $this->spool->quarantineDir();
        $eligible = $deleted = $bytes = 0;
        $capped = false;
        foreach (@scandir($dir) ?: [] as $name) {
            $path = $dir.'/'.$name;
            if (! str_ends_with($name, '.ndjson') || ! is_file($path) || is_link($path)) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            if ($eligible >= self::QUARANTINE_FILES_PER_RUN) {
                $capped = true;
                break;
            }
            $eligible++;
            $size = (int) @filesize($path);
            if (! $dryRun && @unlink($path)) {
                $deleted++;
                $bytes += $size;
            }
        }

        return ['cutoff' => $cutoff, 'eligible' => $eligible, 'deleted' => $deleted, 'bytes' => $bytes, 'capped' => $capped];
    }

    /** The minute of the oldest batch file anywhere in the spool (active, sealed, done, quarantine), or null. */
    private function oldestSpoolFileMinute(): ?int
    {
        $oldest = null;
        foreach ([$this->spool->activeDir(), $this->spool->sealedDir(), $this->spool->doneDir(), $this->spool->quarantineDir()] as $dir) {
            foreach (@scandir($dir) ?: [] as $name) {
                $minute = str_ends_with($name, '.ndjson') ? SpoolConfig::minuteFromKey(substr($name, 0, 12)) : null;
                if ($minute !== null) {
                    $oldest = $oldest === null ? $minute : min($oldest, $minute);
                }
            }
        }

        return $oldest;
    }
}
