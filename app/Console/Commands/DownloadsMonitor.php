<?php

namespace App\Console\Commands;

use App\Support\Downloads\Monitoring\DownloadMonitor;
use Illuminate\Console\Command;

/**
 * READ-ONLY download health report (Phase 3B): slots, capacity rejections, stream failures and client disconnects,
 * telemetry drops, breaker, spool usage, pending batches, rollup lag, period quality and IP/User-Agent growth.
 * Never takes a slot, never touches a process, never writes. Not scheduled.
 *
 * Exit codes (monitoring-agent friendly): 0 ok, 1 warn, 2 critical, 3 unknown.
 */
class DownloadsMonitor extends Command
{
    protected $signature = 'downloads:monitor {--window=60 : Minutes to inspect (5-1440)} {--json : Print JSON}';

    protected $description = 'Report download capacity and telemetry health (read-only)';

    public function handle(): int
    {
        $report = DownloadMonitor::fromLaravel()->collect(time(), (int) $this->option('window'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Download health: %s (window %d min, %.1f ms)', strtoupper($report['overall']), $report['window_minutes'], $report['duration_ms']));
            foreach ($report['checks'] as $name => $check) {
                $details = array_diff_key($check, ['status' => 1, 'ms' => 1, 'holders' => 1, 'by_event_outcome' => 1, 'accounting_uncertainty' => 1, 'files' => 1]);
                $this->line(sprintf('  %-20s %-9s %s', $name, $check['status'], (string) json_encode($details, JSON_UNESCAPED_SLASHES)));
            }
        }

        return match ($report['overall']) {
            DownloadMonitor::CRITICAL => 2,
            DownloadMonitor::WARN => 1,
            DownloadMonitor::UNKNOWN => 3,
            default => 0,
        };
    }
}
