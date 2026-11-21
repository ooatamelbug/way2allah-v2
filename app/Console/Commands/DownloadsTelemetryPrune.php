<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\Aggregation\DatabaseRollupLock;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\TelemetryPruner;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.2 retention (TelemetryPruner). Disabled by default — runs only with DOWNLOAD_TELEMETRY_PRUNE=on (or as
 * --dry-run, which deletes nothing). Not scheduled. Takes the same locks as the rollup, so it never interleaves with it.
 */
class DownloadsTelemetryPrune extends Command
{
    protected $signature = 'downloads:telemetry-prune {--dry-run : Report what would be deleted; delete nothing} {--json : Print JSON}';

    protected $description = 'Delete telemetry rows older than the approved retention (bounded, idempotent; off by default)';

    public function handle(): int
    {
        $config = RollupConfig::fromLaravel();
        $spool = SpoolConfig::fromLaravel();
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $config->pruneEnabled) {
            $this->warn('Pruning is disabled (DOWNLOAD_TELEMETRY_PRUNE=off). Use --dry-run to see what it would delete.');

            return self::FAILURE;
        }

        if (! is_dir($spool->root) && ! @mkdir($spool->root, 0775, true)) {
            $this->error('Spool root unavailable.');

            return self::FAILURE;
        }
        $lock = @fopen($spool->rollupLockPath(), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('The rollup (or another prune) is running.');

            return self::FAILURE;
        }

        $db = DB::connection($config->connection);
        $dbLock = null;

        try {
            if (! $dryRun && $config->dbLock && DatabaseRollupLock::supported($db)) {
                $dbLock = new DatabaseRollupLock($config->connection);
                if (! $dbLock->acquire()) {
                    $this->warn('The database advisory lock is held by another rollup or prune.');

                    return self::FAILURE;
                }
            }

            $report = (new TelemetryPruner($db, $spool, $config))->run(time(), $dryRun);
        } finally {
            $dbLock?->release();
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT));
        } else {
            foreach ($report['refused'] as $problem) {
                $this->error('Refused: '.$problem);
            }
            foreach ($report['tables'] as $table => $r) {
                $this->line(sprintf('%-30s eligible %s%d, deleted %d', $table, $r['capped'] ? '≥ ' : '', $r['eligible'], $r['deleted']));
            }
            if (isset($report['quarantine'])) {
                $q = $report['quarantine'];
                $this->line(sprintf('%-30s eligible %s%d, deleted %d (%d bytes)', 'spool quarantine files', $q['capped'] ? '≥ ' : '', $q['eligible'], $q['deleted'], $q['bytes']));
            }
        }

        return $report['refused'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
