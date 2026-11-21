<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Console\Command;

/**
 * Phase 2: seal finished spool minutes and apply sealed batches to the telemetry tables exactly once, then finalize
 * period quality. Intended for the scheduler (every minute) once approved; does nothing while
 * DOWNLOAD_TELEMETRY_AGGREGATE=off. --dry-run aggregates in memory and reports without writing anything.
 */
class DownloadsTelemetryRollup extends Command
{
    protected $signature = 'downloads:telemetry-rollup {--dry-run : Aggregate and report only; no database write, no file moved} {--json : Print the report as JSON}';

    protected $description = 'Apply sealed download-telemetry batches exactly once and finalize period quality';

    public function handle(): int
    {
        $settings = (array) config('download_telemetry', []);
        if ($this->option('dry-run')) {
            $settings['aggregate'] = RollupConfig::DRY_RUN;
        }
        $report = (new RollupRunner(SpoolConfig::fromLaravel(), RollupConfig::fromArray($settings)))->run();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($report['mode'] === RollupConfig::OFF) {
            $this->line('Telemetry aggregation is off (DOWNLOAD_TELEMETRY_AGGREGATE=off).');
        } elseif ($report['busy']) {
            $this->line('Another rollup is running.');
        } else {
            $this->line(sprintf('%s: sealed %d, applied %d (%d events, %d late periods), already applied %d, duplicate content %d, conflicts %d, quarantined %d, deferred %d, done deleted %d',
                $report['mode'], $report['sealed'], $report['applied'], $report['events'], $report['late_periods'], $report['already_applied'],
                $report['duplicate_content'], $report['conflicts'], $report['quarantined'], $report['deferred'], $report['done_deleted']));
            foreach ($report['errors'] as $error) {
                $this->error($error);
            }
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
