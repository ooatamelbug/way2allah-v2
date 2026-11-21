<?php

namespace App\Support\Downloads\Archive;

use Closure;

/**
 * Expires published archive SEGMENTS (Phase 3D.1). Every record of a segment was created on the same UTC day, so the
 * segment has exactly the deadline of each of its records (ArchiveFiles::deadline: 00:00 UTC on that date + N calendar
 * months, month-end clamped). A segment is deleted as a whole, at or after that deadline — never before, never by
 * rewriting a file — so no record outlives its own deadline by more than the delay until the next expiry run.
 *
 * Only segments of a COMPLETED run (verified, its rows deleted from the live table) are eligible; anything else is
 * skipped and reported. Bounded per run; every deletion is written to audit.log BEFORE the files are removed (data
 * first, then manifest), so an interrupted expiry is visible and finished by the next run.
 */
final class ArchiveExpirer
{
    public function __construct(private readonly ArchiveConfig $config, private readonly ?Closure $clock = null) {}

    /** @return array{expired: list<string>, skipped: array<string, string>, due: int, capped: bool, dry_run: bool} */
    public function run(bool $dryRun = false): array
    {
        $now = $this->clock !== null ? (int) ($this->clock)() : time();
        $report = ['expired' => [], 'skipped' => [], 'due' => 0, 'capped' => false, 'dry_run' => $dryRun];

        foreach (self::dueSegments($this->config, $now) as $id => $manifest) {
            $report['due']++;
            $journal = ArchiveJournal::find($this->config, (string) ($manifest['run_id'] ?? ''));
            if ($journal === null || $journal->state() !== ArchiveJournal::COMPLETED) {
                $report['skipped'][$id] = 'run not completed ('.($journal?->state() ?? 'no journal').')';

                continue;
            }
            if (count($report['expired']) >= $this->config->expireMaxPerRun) {
                $report['capped'] = true;
                break;
            }
            $report['expired'][] = $id;
            if ($dryRun) {
                continue;
            }
            $data = ArchiveFiles::dataPath($this->config, $id);
            ArchiveFiles::audit($this->config, ['action' => 'expired', 'archive_id' => $id, 'run_id' => $manifest['run_id'], 'day' => $manifest['day'] ?? null,
                'rows' => $manifest['rows'] ?? null, 'deadline' => gmdate('c', self::deadlineOf($manifest, $this->config)),
                'file_sha256' => $manifest['file_sha256'] ?? null, 'file_bytes' => is_file($data) ? filesize($data) : 0]);
            @unlink($data);
            @unlink($this->config->archivesDir()."/{$id}.manifest.json");
            ArchiveFiles::syncDir($this->config->archivesDir());
            $journal->set(['expired_segments' => array_values(array_unique([...(array) ($journal->data['expired_segments'] ?? []), $id]))], $now);
        }

        return $report;
    }

    /**
     * Published segments whose deadline has passed.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function dueSegments(ArchiveConfig $config, int $now): array
    {
        $due = [];
        foreach (ArchiveFiles::published($config) as $id) {
            $m = ArchiveFiles::manifest($config, $id);
            if ($m !== null && self::deadlineOf($m, $config) <= $now) {
                $due[$id] = $m;
            }
        }

        return $due;
    }

    /**
     * The segment's deadline, from its records' own creation date. Uses the configured retention (never a longer
     * one recorded at archive time), and the newest record's date as a safety net should a segment ever span days.
     *
     * @param  array<string, mixed>  $m
     */
    public static function deadlineOf(array $m, ArchiveConfig $config): int
    {
        return ArchiveFiles::deadline((int) ($m['max_created_at'] ?? PHP_INT_MAX), $config->retentionMonths);
    }
}
