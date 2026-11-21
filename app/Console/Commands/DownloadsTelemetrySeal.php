<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use Illuminate\Console\Command;

/**
 * Seals finished minute files of the download-telemetry spool. Not scheduled in Phase 1; Phase 2's rollup will
 * call the sealer itself. Sealing only renames spool files inside the spool root — it never deletes anything.
 */
class DownloadsTelemetrySeal extends Command
{
    protected $signature = 'downloads:telemetry-seal {--all : Also seal the current minute\'s file}';

    protected $description = 'Seal finished download-telemetry spool files into stable batches';

    public function handle(): int
    {
        $config = SpoolConfig::fromLaravel();
        $report = (new SpoolSealer($config))->seal((bool) $this->option('all'));

        if ($report['busy']) {
            $this->warn('Another sealer is running.');

            return self::SUCCESS;
        }

        foreach ($report['sealed'] as $batch) {
            $this->line("sealed {$batch['batch_id']} ({$batch['bytes']} bytes)");
        }
        foreach ($report['quarantined'] as $batchId) {
            $this->warn("quarantined {$batchId} (unsupported schema version)");
        }
        foreach ($report['deferred'] as $name) {
            $this->line("deferred {$name} (a writer held it past the budget)");
        }
        foreach ($report['errors'] as $error) {
            $this->error($error);
        }
        $this->line(sprintf('%d sealed, %d quarantined, %d deferred, %d errors.', count($report['sealed']), count($report['quarantined']), count($report['deferred']), count($report['errors'])));

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
