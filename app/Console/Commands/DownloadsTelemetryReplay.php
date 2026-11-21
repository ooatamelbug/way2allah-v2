<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\Aggregation\BatchAggregate;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts a batch from done/ back into sealed/ so the next rollup applies it — ONLY when the ledger has no record of
 * that batch id or of its content. Because the ledger and the aggregates commit together, "no ledger row" means
 * "never applied", so a replay can never double-count. Anything already recorded is refused.
 */
class DownloadsTelemetryReplay extends Command
{
    protected $signature = 'downloads:telemetry-replay {batch : Batch id (file name in done/ without .ndjson)}';

    protected $description = 'Re-queue a batch from done/ for aggregation, only if the ledger proves it was never applied';

    public function handle(): int
    {
        $spool = SpoolConfig::fromLaravel();
        $batchId = (string) $this->argument('batch');
        if (preg_match('/^\d{12}-\d+-[0-9a-f]{8}$/', $batchId) !== 1) {
            $this->error('Not a batch id.');

            return self::FAILURE;
        }

        $source = $spool->doneDir().'/'.$batchId.'.ndjson';
        if (! is_file($source)) {
            $this->error("done/{$batchId}.ndjson does not exist.");

            return self::FAILURE;
        }

        // Stale-batch guard (Phase 2.2): never re-queue a batch older than the safe replay boundary — its ledger row or
        // its aggregate rows may already be pruned, so applying it could double-count or recreate expired rows.
        $config = RollupConfig::fromLaravel();
        $minute = SpoolConfig::minuteFromKey(substr($batchId, 0, 12));
        $aggregate = BatchAggregate::fromFile($source, (int) $minute);
        $boundary = $config->staleBoundary(time());
        if ($minute === null || $minute * 60 < $boundary || ($aggregate->minT !== null && $aggregate->minT < $boundary)) {
            $this->error("Refused: the batch is older than the safe replay boundary ({$config->staleBatchMaxAgeDays} days); it can never be applied.");

            return self::FAILURE;
        }

        $db = DB::connection($config->connection);
        $sha = (string) hash_file('sha256', $source);
        if ($db->table(T::BATCHES)->where('batch_id', $batchId)->orWhere('sha256', $sha)->exists()) {
            $this->error('Refused: the ledger already records this batch (or identical content); replaying it would count it twice.');

            return self::FAILURE;
        }

        if (! @rename($source, $spool->sealedDir().'/'.$batchId.'.ndjson')) {
            $this->error('Could not move the batch back to sealed/.');

            return self::FAILURE;
        }
        $this->line("Re-queued {$batchId}; the next rollup will apply it.");

        return self::SUCCESS;
    }
}
