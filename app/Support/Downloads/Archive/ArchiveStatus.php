<?php

namespace App\Support\Downloads\Archive;

use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Database\Connection;

/**
 * READ-ONLY view of the rejection archive (Phase 3D), for `downloads:rejections-archive-inspect` and
 * `downloads:monitor`. Reads the journals and manifests (small JSON files) and two indexed queries on the live table.
 * Never creates a directory, never takes the archive lock.
 */
final class ArchiveStatus
{
    public const COUNT_CAP = 100_000;

    /** @return array<string, mixed> */
    public static function collect(ArchiveConfig $config, Connection $db, int $now, bool $verify = false): array
    {
        $journals = ArchiveJournal::all($config);
        $completed = array_values(array_filter($journals, fn ($j) => $j->state() === ArchiveJournal::COMPLETED));
        $last = end($completed) ?: null;

        $archives = [];
        $bytes = 0;
        foreach (ArchiveFiles::published($config) as $id) {
            $m = ArchiveFiles::manifest($config, $id) ?? [];
            $path = ArchiveFiles::dataPath($config, $id);
            $size = is_file($path) ? (int) filesize($path) : 0;
            $bytes += $size;
            $entry = ['archive_id' => $id, 'run_id' => $m['run_id'] ?? null, 'day' => $m['day'] ?? null,
                'state' => ArchiveJournal::find($config, (string) ($m['run_id'] ?? ''))?->state(), 'rows' => $m['rows'] ?? null,
                'min_id' => $m['min_id'] ?? null, 'max_id' => $m['max_id'] ?? null,
                'oldest_record' => isset($m['min_created_at']) ? gmdate('c', (int) $m['min_created_at']) : null,
                'newest_record' => isset($m['max_created_at']) ? gmdate('c', (int) $m['max_created_at']) : null,
                'expires_at' => $m === [] ? null : gmdate('c', ArchiveExpirer::deadlineOf($m, $config)),
                'file_bytes' => $size, 'file_sha256' => $m['file_sha256'] ?? null];
            if ($verify) {
                $problems = $m === [] ? ['manifest unreadable'] : ArchiveFiles::check($path, (int) $m['rows'], (string) $m['content_sha256'], (string) $m['file_sha256']);
                $entry['integrity'] = $problems === [] ? 'ok' : $problems;
            }
            $archives[] = $entry;
        }

        $cutoff = $config->cutoff($now);
        $table = DownloadRejectionRecorder::TABLE;
        $oldest = $db->table($table)->min('created_at');
        $backlog = (int) $db->selectOne("SELECT COUNT(*) AS c FROM (SELECT 1 FROM {$table} WHERE created_at < ? LIMIT ".self::COUNT_CAP.') AS b', [$cutoff])->c;

        $incomplete = array_values(array_filter($journals, fn ($j) => in_array($j->state(), ArchiveJournal::INCOMPLETE, true)));

        return [
            'enabled' => $config->enabled,
            'directory' => $config->directory,
            'last_successful_run' => $last === null ? null : ['archive_id' => $last->id(), 'started_at' => gmdate('c', (int) $last->data['started_at']),
                'completed_at' => $last->data['completed_at'] ? gmdate('c', (int) $last->data['completed_at']) : null, 'rows' => $last->data['rows'],
                'age_seconds' => $now - (int) $last->data['started_at']],
            'oldest_active_rejection' => $oldest === null ? null : gmdate('c', (int) $oldest),
            'oldest_active_age_days' => $oldest === null ? null : round(($now - (int) $oldest) / 86400, 2),
            'cutoff' => gmdate('c', $cutoff),
            'backlog_rows' => $backlog,
            'backlog_at_least' => $backlog >= self::COUNT_CAP,
            'verify_failed' => array_map(fn ($j) => ['archive_id' => $j->id(), 'error' => $j->data['error']],
                array_values(array_filter($journals, fn ($j) => $j->state() === ArchiveJournal::VERIFY_FAILED))),
            'incomplete_runs' => array_map(fn ($j) => ['archive_id' => $j->id(), 'state' => $j->state(), 'error' => $j->data['error'],
                'age_seconds' => $now - (int) $j->data['started_at'], 'updated_seconds_ago' => $now - (int) $j->data['updated_at']], $incomplete),
            'archives' => $archives,
            'archive_count' => count($archives),
            'archive_bytes' => $bytes,
            'expired_backlog' => array_keys(ArchiveExpirer::dueSegments($config, $now)),
            'awaiting_backup' => array_map(fn ($j) => ['run_id' => $j->id(), 'digest' => $j->data['digest'], 'segments' => array_column((array) $j->data['segments'], 'id'),
                'rows' => $j->data['rows'], 'waiting_seconds' => $now - (int) ($j->data['verified_at'] ?? $now), 'confirmed' => is_array($j->data['backup_confirmation'] ?? null)],
                array_values(array_filter($journals, fn ($j) => $j->state() === ArchiveJournal::VERIFIED))),
        ];
    }
}
