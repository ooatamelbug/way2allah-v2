<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolStatus;
use Illuminate\Console\Command;

/** Read-only: spool size and file counts against the bound, the breaker, and recent drops (Phase 1 observability). */
class DownloadsTelemetrySpoolStatus extends Command
{
    protected $signature = 'downloads:telemetry-spool-status {--window=60 : Minutes of drop-ledger history to summarise} {--json : Print JSON}';

    protected $description = 'Show the download-telemetry spool size, capacity, breaker state and recent drops (read-only)';

    public function handle(): int
    {
        $status = SpoolStatus::collect(SpoolConfig::fromLaravel(), max(1, (int) $this->option('window')));

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Spool: '.($status['enabled'] ? 'enabled' : 'off').'  ('.$status['root'].')');
        foreach ($status['files'] as $state => $info) {
            $this->line(sprintf('  %-10s %5d files  %12d bytes', $state, $info['files'], $info['bytes']));
        }
        $this->line(sprintf('  total      %5d / %d files  %12d / %d bytes  (%.2f%% of bound)',
            $status['total_files'], $status['max_files'], $status['total_bytes'], $status['bound_bytes'], $status['used_ratio'] * 100));
        $this->line('  oldest unsealed minute: '.($status['oldest_active_minute'] ?? '-'));
        $this->line('  breaker: '.($status['breaker_open_until'] !== null ? 'OPEN until '.$status['breaker_open_until'] : 'closed'));

        $window = $status['drop_ledger']['window'];
        $this->line(sprintf('Last %d min: dropped %d events (%d bytes) [%s]; torn repaired %d; slow writes %d; health faults %d; max latency %d µs',
            $status['drop_ledger']['window_minutes'], $window['dropped_events'], $window['dropped_bytes'], implode(', ', $window['reasons']) ?: '-',
            $window['torn_repaired'], $window['slow_writes'], $window['health_faults'], $window['max_latency_us']));
        if ($status['accounting_exact']) {
            $this->line('Recorded drop counters: no known uncertainty (does not rule out drops lost before they could be recorded, e.g. a killed process)');
        } else {
            $this->warn('Recorded drop counters: LOWER BOUND, known uncertainty — '.json_encode($status['accounting_uncertainty']));
        }

        return self::SUCCESS;
    }
}
