<?php

namespace App\Console\Commands;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveRestorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore ONE verified rejection archive into `download_rejections_restored` (never the live table). Requires the
 * operator to confirm: interactively, or with --confirm=<the same archive id>. --dry-run changes nothing.
 */
class DownloadsRejectionsArchiveRestore extends Command
{
    protected $signature = 'downloads:rejections-archive-restore {archive : Archive id} {--dry-run : Report only} {--confirm= : Repeat the archive id to confirm (non-interactive)} {--json : Print JSON}';

    protected $description = 'Restore a download_rejections archive into download_rejections_restored (explicit confirmation)';

    public function handle(): int
    {
        $config = ArchiveConfig::fromLaravel();
        if (! $config->enabled) {
            $this->line('Rejection archiving is disabled (DOWNLOAD_REJECTIONS_ARCHIVE).');

            return self::SUCCESS;
        }
        $id = (string) $this->argument('archive');
        $dryRun = (bool) $this->option('dry-run');
        $given = $this->option('confirm');
        $confirmed = $given !== null
            ? $given === $id   // a wrong id never falls back to a prompt
            : $this->input->isInteractive() && $this->confirm("Restore archive {$id} into ".ArchiveRestorer::TARGET.'?');
        if (! $dryRun && ! $confirmed) {
            $this->error('Not confirmed: pass --confirm='.$id.' (or answer yes interactively). Nothing was changed.');

            return self::FAILURE;
        }

        $report = (new ArchiveRestorer($config, DB::connection($config->connection)))->restore($id, $dryRun);
        $this->option('json')
            ? $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            : $this->line($report['ok']
                ? sprintf('%s %d of %d rows into %s; %d duplicate(s) skipped; %d conflict(s) left untouched', $dryRun ? 'would insert' : 'inserted',
                    $report['inserted'], $report['rows'], $report['target'], $report['duplicates'], $report['conflicts'])
                : 'restore refused: '.$report['error']);

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
