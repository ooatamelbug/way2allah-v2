<?php

namespace App\Console\Commands;

use App\Support\Downloads\Monitoring\RejectionLogReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY size and growth report for `download_rejections` (Phase 3C). Never deletes, exports or modifies a row.
 * `--exact` adds a full COUNT(*) over the created_at index (cost grows with the table; run it off-peak).
 */
class DownloadsRejectionsReport extends Command
{
    protected $signature = 'downloads:rejections-report {--days=30 : Days of daily growth (1-90)} {--exact : Exact total (full index count)} {--json : Print JSON}';

    protected $description = 'Report download_rejections size and growth (read-only)';

    public function handle(): int
    {
        $report = (new RejectionLogReport(DB::connection('main')))->collect(time(), (int) $this->option('days'), (bool) $this->option('exact'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line(sprintf('download_rejections: %s rows%s; oldest %s; newest %s',
            number_format((int) ($report['total_rows'] ?? $report['total_rows_estimate'] ?? 0)), $report['total_is_exact'] ? '' : ' (estimate)',
            $report['oldest'] ?? '-', $report['newest'] ?? '-'));
        $this->line(sprintf('last %d days: %d rows (%.1f/day, last 7 days %d, busiest %s)', $report['window_days'], $report['rows_in_window'],
            $report['average_per_day'], $report['rows_last_7_days'], $report['busiest_day'] ?? '-'));
        $this->line(sprintf('storage: %s; ~%s bytes/row; projected %s rows/year%s', $report['storage_bytes'] === null ? 'n/a' : number_format($report['storage_bytes']).' bytes',
            $report['bytes_per_row'] ?? 'n/a', number_format($report['projected_rows_per_year']),
            $report['projected_bytes_per_year'] === null ? '' : ' ('.number_format($report['projected_bytes_per_year']).' bytes)'));

        return self::SUCCESS;
    }
}
