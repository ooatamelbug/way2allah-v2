<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\Aggregation\IpUaGrowthMonitor;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * READ-ONLY growth report for download_ip_ua_hourly (Phase 2.2). Exit code 0 = normal, 2 = warning(s); warnings are
 * also logged as DOWNLOAD_TELEMETRY_IP_UA_GROWTH. Never deletes or caps anything.
 */
class DownloadsTelemetryGrowth extends Command
{
    protected $signature = 'downloads:telemetry-growth {--hours=24 : Hours to inspect (2-168)} {--json : Print JSON}';

    protected $description = 'Report download_ip_ua_hourly growth against the configured thresholds (read-only)';

    public function handle(): int
    {
        $config = RollupConfig::fromLaravel();
        $report = (new IpUaGrowthMonitor(DB::connection($config->connection), $config))->report(time(), (int) $this->option('hours'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->line(sprintf('IP/User-Agent combinations, latest full hour: %d (median earlier: %d); projected at %d-day retention: %.1f MiB',
                $report['latest_full_hour_rows'], $report['median_earlier_hours'], $report['retention_days'], $report['projected_bytes_at_retention'] / 1048576));
            foreach ($report['warnings'] as $warning) {
                $this->warn('WARNING: '.$warning);
            }
        }

        if ($report['warnings'] !== []) {
            Log::warning('download_ip_ua_hourly growth', ['event' => 'DOWNLOAD_TELEMETRY_IP_UA_GROWTH', 'warnings' => $report['warnings'],
                'latest_full_hour_rows' => $report['latest_full_hour_rows'], 'projected_bytes' => $report['projected_bytes_at_retention']]);

            return 2;
        }

        return self::SUCCESS;
    }
}
