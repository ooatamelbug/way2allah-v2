<?php

namespace App\Console\Commands;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveExpirer;
use Illuminate\Console\Command;

/** Expire rejection archives older than the retention (calendar months from their newest record). Disabled by default. */
class DownloadsRejectionsArchiveExpire extends Command
{
    protected $signature = 'downloads:rejections-archive-expire {--dry-run : Report only} {--json : Print JSON}';

    protected $description = 'Delete rejection archives past their retention (audited, bounded; disabled by default)';

    public function handle(): int
    {
        $config = ArchiveConfig::fromLaravel();
        if (! $config->enabled) {
            $this->line('Rejection archiving is disabled (DOWNLOAD_REJECTIONS_ARCHIVE).');

            return self::SUCCESS;
        }
        $report = (new ArchiveExpirer($config))->run((bool) $this->option('dry-run'));
        $this->option('json')
            ? $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            : $this->line(sprintf('%s %d archive(s); %d due; %d skipped%s', $report['dry_run'] ? 'would expire' : 'expired',
                count($report['expired']), $report['due'], count($report['skipped']), $report['capped'] ? ' (capped)' : ''));

        return self::SUCCESS;
    }
}
