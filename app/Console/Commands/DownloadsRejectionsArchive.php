<?php

namespace App\Console\Commands;

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveExpirer;
use App\Support\Downloads\Archive\RejectionArchiver;
use Illuminate\Console\Command;

/**
 * Archive `download_rejections` (Phase 3D). DISABLED by default (DOWNLOAD_REJECTIONS_ARCHIVE) and not scheduled.
 * Safe to run repeatedly: does nothing until a run is due, resumes an interrupted run, and then expires archives past
 * retention. Exit 0 = ok / nothing to do, 1 = failed or blocked.
 */
class DownloadsRejectionsArchive extends Command
{
    protected $signature = 'downloads:rejections-archive
        {--force : Start a run even if one is not due yet}
        {--confirm-backup= : Run id whose verified segments have been backed up externally (requires --digest)}
        {--digest= : The run\'s manifest digest, exactly as printed by the run or by downloads:rejections-archive-inspect}
        {--reference= : Optional backup reference recorded in the audit log (e.g. the backup job id)}
        {--abandon= : Abandon this VERIFY_FAILED run (its files move to rejected/; nothing is deleted)}
        {--no-expire : Skip archive expiration after the run}
        {--json : Print JSON}';

    protected $description = 'Archive old download_rejections rows into verified compressed NDJSON (disabled by default)';

    public function handle(): int
    {
        $config = ArchiveConfig::fromLaravel();
        if (! $config->enabled) {
            $this->line('Rejection archiving is disabled (DOWNLOAD_REJECTIONS_ARCHIVE).');

            return self::SUCCESS;
        }
        $archiver = RejectionArchiver::fromLaravel();

        if (($id = $this->option('confirm-backup')) !== null) {
            if (! is_string($this->option('digest')) || $this->option('digest') === '') {
                $this->error('--confirm-backup needs --digest=<the run\'s manifest digest>. Nothing was confirmed.');

                return self::FAILURE;
            }
            $result = $archiver->confirmBackup((string) $id, (string) $this->option('digest'), $this->option('reference') !== null ? (string) $this->option('reference') : null);

            return $this->result($result['ok'], "backup confirmed for {$id}; deletion proceeds on the next run", 'not confirmed: '.$result['error']);
        }
        if (($id = $this->option('abandon')) !== null) {
            return $this->result($archiver->abandon((string) $id), "{$id} abandoned; its files are in rejected/", "{$id} is not a VERIFY_FAILED run");
        }

        $report = $archiver->run((bool) $this->option('force'));
        if (! $this->option('no-expire') && in_array($report['status'], ['completed', 'not_due', 'awaiting_backup'], true)) {
            $report['expired'] = (new ArchiveExpirer($config))->run();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('status %s; run %s; state %s; rows %d in %d segment(s); deleted %d%s%s', $report['status'], $report['archive_id'] ?? '-',
                $report['state'] ?? '-', $report['rows'], count($report['segments']), $report['deleted'], $report['backlog'] ? '; backlog remains' : '', $report['error'] ? '; '.$report['error'] : ''));
            if ($report['status'] === 'awaiting_backup') {
                $this->warn("Back up these files, then confirm: --confirm-backup={$report['archive_id']} --digest={$report['digest']}");
                foreach ($report['segments'] as $segment) {
                    $this->line('  archives/'.$segment.'.ndjson.gz (+ .manifest.json)');
                }
            }
            if (is_array($report['expired'])) {
                $this->line(sprintf('expired %d archive(s); %d skipped', count($report['expired']['expired']), count($report['expired']['skipped'])));
            }
        }

        return in_array($report['status'], ['failed', 'blocked', 'refused'], true) ? self::FAILURE : self::SUCCESS;
    }

    private function result(bool $ok, string $yes, string $no): int
    {
        $ok ? $this->info($yes) : $this->error($no);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
