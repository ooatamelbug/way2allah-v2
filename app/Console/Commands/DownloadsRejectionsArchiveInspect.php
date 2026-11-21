<?php

namespace App\Console\Commands;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveFiles;
use App\Support\Downloads\Archive\ArchiveJournal;
use App\Support\Downloads\Archive\ArchiveStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** READ-ONLY: list rejection archives and runs, or show one; --verify re-checks file integrity. */
class DownloadsRejectionsArchiveInspect extends Command
{
    protected $signature = 'downloads:rejections-archive-inspect {archive? : Archive id} {--verify : Re-check checksums and record counts} {--json : Print JSON}';

    protected $description = 'Inspect download_rejections archives (read-only)';

    public function handle(): int
    {
        $config = ArchiveConfig::fromLaravel();
        $status = ArchiveStatus::collect($config, DB::connection($config->connection), time(), (bool) $this->option('verify'));

        if (($id = $this->argument('archive')) !== null) {
            $out = ['manifest' => ArchiveFiles::manifest($config, (string) $id), 'journal' => ArchiveJournal::find($config, (string) $id)?->data,
                'listing' => collect($status['archives'])->firstWhere('archive_id', $id)];
            if ($out['manifest'] === null && $out['journal'] === null) {
                $this->error("No archive or run {$id}.");

                return self::FAILURE;
            }
            $this->line((string) json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $this->line(sprintf('archiving %s; %d archive(s), %s bytes; backlog %d rows; oldest active %s', $status['enabled'] ? 'enabled' : 'disabled',
            $status['archive_count'], number_format($status['archive_bytes']), $status['backlog_rows'], $status['oldest_active_rejection'] ?? '-'));
        foreach ($status['archives'] as $a) {
            $this->line(sprintf('  %s %-10s rows %s  %s … %s  expires %s%s', $a['archive_id'], $a['state'] ?? '?', $a['rows'], $a['oldest_record'], $a['newest_record'],
                $a['expires_at'], isset($a['integrity']) ? '  integrity '.(is_array($a['integrity']) ? implode('; ', $a['integrity']) : $a['integrity']) : ''));
        }

        return self::SUCCESS;
    }
}
