<?php

namespace App\Support\Downloads\Archive;

use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Monitoring\RejectionLogReport;
use Illuminate\Database\Connection;

/**
 * READ-ONLY storage estimate for the rejection archive (Phase 3D.1). No full-table scan, no export:
 *
 *  - table size and InnoDB bytes/row: information_schema statistics (MySQL/MariaDB), no scan;
 *  - compressed bytes/row: the newest `sample` rows by primary key (a bounded PK range read) are encoded exactly as
 *    the archiver encodes them and gzip-compressed IN MEMORY; only the resulting sizes are reported — no row, value
 *    or file leaves the process;
 *  - daily volume: rows per UTC day over the last `days` days from the created_at index (RejectionLogReport);
 *  - projections: steady-state archive size at the retention (the longest possible window: 4 calendar months ≤ 123
 *    days), the live table at its 20-day maximum, one run's temporary space, backup copies, and the disk reserve.
 *
 * An estimate, with stated uncertainty: compression depends on how repetitive real rows are (synthetic test data
 * compresses far better than real User-Agents and URLs), recent days may not represent incident peaks, and gzip on a
 * small sample over-states per-row cost slightly (fixed header overhead).
 */
final class ArchiveStorageEstimator
{
    /** Assumed when there is nothing to sample (stated as such). */
    public const ASSUMED_COMPRESSED_BYTES_PER_ROW = 120;

    public const MANIFEST_BYTES = 1500;

    public function __construct(private readonly ArchiveConfig $config, private readonly Connection $db) {}

    /** @return array<string, mixed> */
    public function estimate(int $now, int $sample = 2000, int $days = 30, int $backupCopies = 1): array
    {
        $sample = max(10, min(20_000, $sample));
        $days = max(1, min(90, $days));
        $backupCopies = max(0, min(10, $backupCopies));
        $table = DownloadRejectionRecorder::TABLE;
        $columns = $this->db->getSchemaBuilder()->getColumnListing($table);

        // Compressed size of the newest rows, encoded exactly as archived; nothing is written or returned.
        $rows = $this->db->table($table)->orderByDesc('id')->limit($sample)->get($columns);
        $ndjson = '';
        foreach ($rows->reverse() as $row) {
            $ndjson .= ArchiveFiles::encode((array) $row, $columns)."\n";
        }
        $sampled = $rows->count();
        $gz = $sampled > 0 ? strlen((string) gzencode($ndjson, 6)) : 0;
        $compressedPerRow = $sampled > 0 ? $gz / $sampled : self::ASSUMED_COMPRESSED_BYTES_PER_ROW;
        $rawPerRow = $sampled > 0 ? strlen($ndjson) / $sampled : null;
        unset($ndjson, $rows);

        $report = (new RejectionLogReport($this->db))->collect($now, $days);
        $perDay = array_values($report['per_day']);
        $avg = $perDay === [] ? 0.0 : array_sum($perDay) / count($perDay);
        $max = $perDay === [] ? 0 : max($perDay);
        $innodbPerRow = $report['bytes_per_row'];

        $retentionDays = (int) ceil((ArchiveFiles::deadline($now, $this->config->retentionMonths) - intdiv($now, 86400) * 86400) / 86400) + 1;
        $maxRetentionDays = max($retentionDays, (int) ceil($this->config->retentionMonths * 31));   // the longest N-month window
        $archive = fn (float $perDayRows) => (int) round($perDayRows * $maxRetentionDays * $compressedPerRow + $maxRetentionDays * self::MANIFEST_BYTES);
        $runRows = fn (float $perDayRows) => $perDayRows * ($this->config->frequencyDays + 1);

        $archiveAvg = $archive($avg);
        $archiveMax = $archive((float) $max);
        $tmpMax = (int) round($runRows((float) $max) * $compressedPerRow);
        $activeMax = $innodbPerRow === null ? null : (int) round($max * $this->config->activeRetentionDays * $innodbPerRow);

        $confidence = match (true) {
            $sampled === 0 => 'none (no rows to sample — compressed size ASSUMED)',
            $sampled < 200 || array_sum($perDay) < 1000 => 'low (small sample or little recent volume)',
            default => 'moderate (recent sample; real incident peaks may differ)',
        };

        return [
            'sampled_rows' => $sampled,
            'raw_ndjson_bytes_per_row' => $rawPerRow === null ? null : round($rawPerRow, 1),
            'compressed_bytes_per_row' => round($compressedPerRow, 1),
            'compressed_is_assumed' => $sampled === 0,
            'table_rows_estimate' => $report['total_rows'] ?? $report['total_rows_estimate'],
            'table_storage_bytes' => $report['storage_bytes'],
            'innodb_bytes_per_row' => $innodbPerRow,
            'window_days' => $days,
            'rows_per_day_avg' => round($avg, 1),
            'rows_per_day_max' => $max,
            'retention_window_days_max' => $maxRetentionDays,
            'archive_growth_bytes_per_day_avg' => (int) round($avg * $compressedPerRow),
            'archive_steady_state_bytes_avg' => $archiveAvg,
            'archive_steady_state_bytes_peak' => $archiveMax,
            'run_temporary_bytes_peak' => $tmpMax,
            'active_table_bytes_at_retention_peak' => $activeMax,
            'backup_copies' => $backupCopies,
            'backup_bytes_peak' => $archiveMax * $backupCopies,
            'disk_reserve_bytes' => $this->config->minFreeBytes,
            'recommended_free_bytes_on_archive_disk' => $archiveMax + $tmpMax + $this->config->minFreeBytes,
            'confidence' => $confidence,
            'notes' => [
                'compressed size measured on the newest rows only; real archives compress differently from synthetic test data',
                'peak figures assume the busiest recent day repeats every day for the whole retention window',
                'backup bytes are needed wherever the backups are kept (on the archive disk only if backups are stored there)',
            ],
        ];
    }
}
