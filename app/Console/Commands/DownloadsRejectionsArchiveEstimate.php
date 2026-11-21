<?php

namespace App\Console\Commands;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveStorageEstimator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** READ-ONLY archive storage estimate (sizes only; no rows exported; no full-table scan). Works while archiving is disabled. */
class DownloadsRejectionsArchiveEstimate extends Command
{
    protected $signature = 'downloads:rejections-archive-estimate {--sample=2000 : Newest rows to compress in memory (10-20000)} {--days=30 : Days of daily volume (1-90)} {--backup-copies=1 : Backup copies to budget for} {--json : Print JSON}';

    protected $description = 'Estimate download_rejections archive storage (read-only)';

    public function handle(): int
    {
        $config = ArchiveConfig::fromLaravel();
        $e = (new ArchiveStorageEstimator($config, DB::connection($config->connection)))
            ->estimate(time(), (int) $this->option('sample'), (int) $this->option('days'), (int) $this->option('backup-copies'));

        if ($this->option('json')) {
            $this->line((string) json_encode($e, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $mib = fn (?int $b) => $b === null ? 'n/a' : number_format($b / 1048576, 1).' MiB';
        $this->line(sprintf('sample %d rows: %s compressed bytes/row%s (raw %s); confidence: %s', $e['sampled_rows'], $e['compressed_bytes_per_row'],
            $e['compressed_is_assumed'] ? ' ASSUMED' : '', $e['raw_ndjson_bytes_per_row'] ?? 'n/a', $e['confidence']));
        $this->line(sprintf('volume: %.1f rows/day average, %d busiest day (last %d days)', $e['rows_per_day_avg'], $e['rows_per_day_max'], $e['window_days']));
        $this->line(sprintf('archive at %d-day retention: %s average, %s peak; run temp %s; live table at retention %s', $e['retention_window_days_max'],
            $mib($e['archive_steady_state_bytes_avg']), $mib($e['archive_steady_state_bytes_peak']), $mib($e['run_temporary_bytes_peak']), $mib($e['active_table_bytes_at_retention_peak'])));
        $this->line(sprintf('backups (%d copies) %s; reserve %s; recommended free on the archive disk %s', $e['backup_copies'], $mib($e['backup_bytes_peak']),
            $mib($e['disk_reserve_bytes']), $mib($e['recommended_free_bytes_on_archive_disk'])));

        return self::SUCCESS;
    }
}
