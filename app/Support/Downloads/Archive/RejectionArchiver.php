<?php

namespace App\Support\Downloads\Archive;

use App\Support\Downloads\DownloadRejectionRecorder;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Archives `download_rejections` into compressed NDJSON day segments (Phase 3D; day segments and digest-bound backup
 * confirmation since 3D.1). One run at a time (host flock + MariaDB GET_LOCK), durable and restartable:
 *
 *  PENDING   the run's FIXED selection is recorded before any data moves: `cutoff` (run time − cutoff_days) and
 *            `high_id` (MAX(id) at that moment). The run covers exactly the rows with id ≤ high_id AND
 *            created_at < cutoff. Rows inserted while it runs get ids > high_id: never touched.
 *  COPYING   rows are read in bounded keyset chunks ordered by (created_at, id) — the order of `idx_dr_created`, which
 *            carries the primary key — into one tmp part file per UTC DAY of created_at (one file open at a time), at
 *            most max_rows_per_run (the rest is backlog: the next run is due at once). Each part is then re-read FROM
 *            DISK, counted, hashed, and every record compared byte-for-byte with its row in the table. Then each
 *            segment is published atomically (data, then manifest), and the run's DIGEST over all its manifests is
 *            recorded. A crash anywhere in COPYING restarts it from scratch: this run's unpublished AND published
 *            segment files are removed first (nothing was deleted from the table, and the run was never VERIFIED).
 *  VERIFIED  published and verified. With require_backup_confirmation (the default), deletion waits for an operator's
 *            confirmation naming THIS run and THIS digest (confirmBackup()).
 *  DELETING  first every segment is re-verified (checksums, counts) and, if required, the confirmation's digest must
 *            equal the digest of the manifests on disk now; then each segment's OWN ids are deleted in chunks
 *            (`id IN (…) AND id ≤ high_id AND created_at < cutoff`), progress (segment, chunk) recorded per chunk.
 *  COMPLETED done. VERIFY_FAILED: nothing is deleted; no new run starts until an operator abandons it.
 *
 * Errors keep the state (crash semantics) and record only the exception class — never a message that could carry
 * row data. The live table's writer is never locked: reads are plain snapshot reads; deletes touch only archived ids.
 */
final class RejectionArchiver
{
    public const TABLE = DownloadRejectionRecorder::TABLE;

    public const DB_LOCK = 'way2allah:download_rejections:archive';

    /**
     * A run is due this long BEFORE frequency_days have fully elapsed, so a daily scheduler firing at the same minute
     * each day (a few seconds early or late) never slips a whole day: consecutive runs stay ≤ 7 days apart, and
     * cutoff (13 days) + 7 days keeps the live table ≤ 20 days.
     */
    public const DUE_TOLERANCE_SECONDS = 3600;

    /** @param (Closure(string, array<string, mixed>): void)|null $hook named checkpoints (tests: crash simulation) */
    public function __construct(
        private readonly ArchiveConfig $config,
        private readonly ?Connection $db = null,
        private readonly ?Closure $hook = null,
        private readonly ?Closure $clock = null,
        private readonly ?Closure $diskFree = null,
    ) {}

    public static function fromLaravel(): self
    {
        $config = ArchiveConfig::fromLaravel();

        return new self($config, DB::connection($config->connection));
    }

    /**
     * @return array<string, mixed> report: status = disabled | refused | busy | blocked | not_due | awaiting_backup | completed | failed
     */
    public function run(bool $force = false): array
    {
        $report = ['status' => 'disabled', 'archive_id' => null, 'state' => null, 'rows' => 0, 'deleted' => 0, 'backlog' => false,
            'segments' => [], 'digest' => null, 'error' => null, 'expired' => null];
        if (! $this->config->enabled) {
            return $report;
        }
        if (($problems = $this->config->problems()) !== []) {
            return ['status' => 'refused', 'error' => implode('; ', $problems)] + $report;
        }

        try {
            foreach ([$this->config->directory, $this->config->archivesDir(), $this->config->runsDir(), $this->config->tmpDir()] as $dir) {
                ArchiveFiles::ensureDir($this->config, $dir);
            }

            return $this->locked(fn () => $this->runLocked($force, $report), ['status' => 'busy'] + $report);
        } catch (ArchiveAborted $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()] + $report;
        }
    }

    /**
     * Operator: confirm that THIS run's verified segments have been backed up externally. The confirmation names the
     * run id AND its digest (printed by the run and by inspect); it is accepted only while the run is VERIFIED, the
     * digest equals the run's recorded digest AND the digest of the manifests on disk now, and every segment passes its
     * integrity check. It is stored in the run's journal (durable across retries) and audited. It can never authorize
     * another run, nor this run's files if they change afterwards (the digest is re-checked before any deletion).
     *
     * @return array{ok: bool, error: ?string}
     */
    public function confirmBackup(string $runId, string $digest, ?string $reference = null): array
    {
        return $this->locked(function () use ($runId, $digest, $reference) {
            $j = ArchiveJournal::find($this->config, $runId);
            if ($j === null) {
                return ['ok' => false, 'error' => 'no such archive run'];
            }
            if ($j->state() !== ArchiveJournal::VERIFIED) {
                return ['ok' => false, 'error' => 'the run is '.$j->state().', not VERIFIED (awaiting deletion)'];
            }
            $segments = array_column((array) $j->data['segments'], 'id');
            $onDisk = ArchiveFiles::digest($this->config, $segments);
            if ($digest === '' || ! hash_equals((string) $j->data['digest'], $digest) || $onDisk === null || ! hash_equals($onDisk, $digest)) {
                return ['ok' => false, 'error' => 'digest does not match this run\'s verified manifests'];
            }
            if (($problems = $this->segmentProblems($j)) !== []) {
                return ['ok' => false, 'error' => 'archive integrity failed: '.implode('; ', $problems)];
            }
            $reference = $reference === null ? null : mb_substr(trim($reference), 0, 200);
            ArchiveFiles::audit($this->config, ['action' => 'backup_confirmed', 'run_id' => $runId, 'digest' => $digest,
                'segments' => $segments, 'reference' => $reference]);
            $j->set(['backup_confirmation' => ['digest' => $digest, 'confirmed_at' => $this->now(), 'reference' => $reference], 'error' => null], $this->now());

            return ['ok' => true, 'error' => null];
        }, ['ok' => false, 'error' => 'an archive run is in progress; try again']);
    }

    /**
     * Operator: give up a VERIFY_FAILED run. Nothing was deleted for it; its files (if any) are moved to rejected/
     * for investigation, never deleted. The rows it covered stay in the table and are archived by the next run.
     */
    public function abandon(string $runId): bool
    {
        return $this->locked(function () use ($runId) {
            $j = ArchiveJournal::find($this->config, $runId);
            if ($j === null || $j->state() !== ArchiveJournal::VERIFY_FAILED || (int) $j->data['deleted_rows'] > 0) {
                return false;
            }
            ArchiveFiles::ensureDir($this->config, $this->config->rejectedDir());
            foreach ($this->runFiles($runId) as $path) {
                @rename($path, $this->config->rejectedDir().'/'.basename($path));
            }
            ArchiveFiles::audit($this->config, ['action' => 'abandoned', 'run_id' => $runId, 'reason' => $j->data['error']]);
            $j->set(['state' => ArchiveJournal::ABANDONED], $this->now());

            return true;
        }, false);
    }

    /** The class and code only (plus fixed archive texts): a database exception message embeds row values. */
    public static function describe(Throwable $e): string
    {
        if ($e instanceof ArchiveAborted) {
            return $e->getMessage();
        }
        $state = $e instanceof \PDOException && is_array($e->errorInfo) && isset($e->errorInfo[0]) ? ' SQLSTATE '.$e->errorInfo[0] : '';

        return $e::class.' (code '.(string) $e->getCode().')'.$state;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function runLocked(bool $force, array $report): array
    {
        $journal = null;
        try {
            $journals = ArchiveJournal::all($this->config);
            foreach ($journals as $j) {
                if ($j->state() === ArchiveJournal::VERIFY_FAILED) {
                    return ['status' => 'blocked', 'archive_id' => $j->id(), 'state' => $j->state(),
                        'error' => 'a run failed verification; investigate, then --abandon='.$j->id()] + $report;
                }
            }
            $journal = current(array_filter($journals, fn (ArchiveJournal $j) => in_array($j->state(), ArchiveJournal::INCOMPLETE, true))) ?: null;

            if ($journal === null) {
                if (! $force && ! $this->due($journals)) {
                    return ['status' => 'not_due'] + $report;
                }
                $journal = ArchiveJournal::start($this->config, $this->now(), $this->config->cutoff($this->now()),
                    (int) ($this->db()->table(self::TABLE)->max('id') ?? 0), $this->columns());
                $this->checkpoint('pending', $journal);
            }

            $journal->set(['attempts' => (int) $journal->data['attempts'] + 1], $this->now());
            $status = $this->advance($journal);

            return ['status' => $status] + $this->summary($journal) + $report;
        } catch (Throwable $e) {
            $error = self::describe($e);
            if ($journal !== null) {
                try {
                    $journal->set(['error' => $error], $this->now());   // state unchanged: resumable
                } catch (Throwable) {
                    // the journal itself may be what failed (disk full, permissions)
                }
            }

            return ['status' => 'failed', 'error' => $error] + ($journal === null ? [] : $this->summary($journal)) + $report;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @param  T  $busy
     * @return T
     */
    private function locked(Closure $work, mixed $busy): mixed
    {
        ArchiveFiles::ensureDir($this->config, $this->config->directory);
        $lock = @fopen($this->config->lockPath(), 'c');
        if ($lock === false) {
            throw new ArchiveAborted('archive lock cannot be opened');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return $busy;
        }
        $dbLocked = false;
        try {
            if ($this->isMysql()) {
                $dbLocked = (int) ($this->db()->selectOne('SELECT GET_LOCK(?, 0) AS l', [self::DB_LOCK])->l ?? 0) === 1;
                if (! $dbLocked) {
                    return $busy;
                }
            }

            return $work();
        } finally {
            if ($dbLocked) {
                try {
                    $this->db()->selectOne('SELECT RELEASE_LOCK(?) AS r', [self::DB_LOCK]);
                } catch (Throwable) {
                    // the session's end releases it
                }
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function summary(ArchiveJournal $j): array
    {
        return ['archive_id' => $j->id(), 'state' => $j->state(), 'rows' => (int) $j->data['rows'], 'deleted' => (int) $j->data['deleted_rows'],
            'backlog' => (bool) $j->data['backlog'], 'segments' => array_column((array) $j->data['segments'], 'id'), 'digest' => $j->data['digest'],
            'error' => in_array($j->state(), [ArchiveJournal::VERIFY_FAILED, ArchiveJournal::VERIFIED], true) ? $j->data['error'] : null];
    }

    /** @param list<ArchiveJournal> $journals */
    private function due(array $journals): bool
    {
        $completed = array_values(array_filter($journals, fn (ArchiveJournal $j) => $j->state() === ArchiveJournal::COMPLETED));
        $last = end($completed);
        if ($last === false) {
            return true;
        }

        return (bool) $last->data['backlog']
            || $this->now() - (int) $last->data['started_at'] >= $this->config->frequencyDays * 86400 - self::DUE_TOLERANCE_SECONDS;
    }

    private function advance(ArchiveJournal $j): string
    {
        while (true) {
            switch ($j->state()) {
                case ArchiveJournal::PENDING:
                    $j->set(['state' => ArchiveJournal::COPYING, 'error' => null], $this->now());
                    break;

                case ArchiveJournal::COPYING:
                    $this->copyAndPublish($j);
                    break;

                case ArchiveJournal::VERIFIED:
                    if ($this->config->requireBackupConfirmation && ! $this->confirmationValid($j)) {
                        return 'awaiting_backup';
                    }
                    $j->set(['state' => ArchiveJournal::DELETING], $this->now());
                    $this->checkpoint('deleting', $j);
                    break;

                case ArchiveJournal::DELETING:
                    if (! $this->deleteArchived($j)) {
                        // deleteArchived() moved the run back to VERIFIED (confirmation void) or to VERIFY_FAILED.
                        return ($j->data['state'] ?? null) === ArchiveJournal::VERIFIED ? 'awaiting_backup' : 'failed';
                    }
                    break;

                case ArchiveJournal::COMPLETED:
                    return 'completed';

                default:   // VERIFY_FAILED
                    return 'failed';
            }
        }
    }

    /** A confirmation counts only if it names the digest of THIS run's manifests as they are on disk now. */
    private function confirmationValid(ArchiveJournal $j): bool
    {
        $c = $j->data['backup_confirmation'] ?? null;
        if (! is_array($c)) {
            return false;
        }
        $onDisk = ArchiveFiles::digest($this->config, array_column((array) $j->data['segments'], 'id'));
        if ($onDisk === null || ! hash_equals((string) $c['digest'], $onDisk) || ! hash_equals((string) $j->data['digest'], $onDisk)) {
            $j->set(['error' => 'backup confirmation does not match the archive on disk; nothing deleted; confirm again'], $this->now());

            return false;
        }

        return true;
    }

    private function copyAndPublish(ArchiveJournal $j): void
    {
        // Restart from scratch: nothing of this run was deleted from the table, and it was never VERIFIED.
        foreach ($this->runFiles($j->id()) as $path) {
            @unlink($path);
        }
        $j->set(['segments' => [], 'rows' => 0, 'digest' => null], $this->now());

        $free = $this->diskFree !== null ? ($this->diskFree)($this->config->directory) : @disk_free_space($this->config->directory);
        if ($free !== false && $free < $this->config->minFreeBytes) {
            throw new ArchiveAborted('insufficient free disk space for the archive');
        }

        $columns = (array) $j->data['columns'];
        $segments = [];
        $segment = null;
        $rows = 0;
        $lastC = -1;
        $lastId = 0;
        $backlog = false;

        // Keyset on (created_at, id): created_at ≥ last AND (created_at > last OR id > last id).
        $base = function () use ($j, &$lastC, &$lastId) {
            $c = $lastC;
            $i = $lastId;

            return $this->db()->table(self::TABLE)->where('id', '<=', $j->data['high_id'])->where('created_at', '<', $j->data['cutoff'])
                ->where('created_at', '>=', $c)->where(fn ($q) => $q->where('created_at', '>', $c)->orWhere('id', '>', $i));
        };

        try {
            while (true) {
                $limit = min($this->config->chunkRows, $this->config->maxRowsPerRun - $rows);
                if ($limit <= 0) {
                    $backlog = $base()->exists();
                    break;
                }
                $chunk = $base()->orderBy('created_at')->orderBy('id')->limit($limit)->get($columns);
                if ($chunk->isEmpty()) {
                    break;
                }
                foreach ($chunk as $row) {
                    $row = (array) $row;
                    $created = (int) $row['created_at'];
                    $day = intdiv($created, 86400);
                    if ($segment === null || $segment['day'] !== $day) {
                        if ($segment !== null) {
                            $segments[] = $this->closeSegment($segment);
                            $segment = null;
                        }
                        $segment = $this->openSegment($j->id(), $day);
                    }
                    $line = ArchiveFiles::encode($row, $columns)."\n";
                    if (@gzwrite($segment['gz'], $line) !== strlen($line)) {
                        throw new ArchiveAborted('archive write failed (disk full or I/O error)');
                    }
                    hash_update($segment['hash'], $line);
                    $segment['rows']++;
                    $id = (int) $row['id'];
                    $segment['min_id'] = min($segment['min_id'] ?? $id, $id);
                    $segment['max_id'] = max($segment['max_id'] ?? $id, $id);
                    $segment['min_created_at'] ??= $created;
                    $segment['max_created_at'] = $created;
                    $rows++;
                    $lastC = $created;
                    $lastId = $id;
                }
                $j->set(['rows' => $rows], $this->now());
                $this->checkpoint('copy_chunk', $j);
            }
            if ($segment !== null) {
                $closing = $segment;
                $segment = null;
                $segments[] = $this->closeSegment($closing);
            }
        } finally {
            if ($segment !== null) {
                @gzclose($segment['gz']);
            }
        }

        if ($rows === 0) {
            $j->set(['state' => ArchiveJournal::COMPLETED, 'rows' => 0, 'completed_at' => $this->now()], $this->now());

            return;
        }
        $j->set(['rows' => $rows, 'segments' => $segments, 'backlog' => $backlog], $this->now());
        $this->checkpoint('copied', $j);

        foreach ($segments as $s) {
            if (($problem = $this->verifyAgainstTable($s, $this->partPath($s['id']), $columns)) !== null) {
                $j->set(['state' => ArchiveJournal::VERIFY_FAILED, 'error' => "verification failed ({$s['id']}): {$problem}"], $this->now());

                return;   // nothing published, nothing deleted
            }
            $this->checkpoint('verify', $j);
        }
        $this->checkpoint('verified_part', $j);

        foreach ($segments as $i => $s) {
            $manifest = [
                'format' => ArchiveFiles::FORMAT, 'version' => ArchiveFiles::VERSION, 'archive_id' => $s['id'], 'run_id' => $j->id(),
                'day' => gmdate('Y-m-d', $s['day'] * 86400), 'table' => self::TABLE, 'columns' => $columns, 'order' => 'created_at, id',
                'rows' => $s['rows'], 'min_id' => $s['min_id'], 'max_id' => $s['max_id'], 'min_created_at' => $s['min_created_at'],
                'max_created_at' => $s['max_created_at'], 'cutoff' => $j->data['cutoff'], 'high_id' => $j->data['high_id'],
                'content_sha256' => $s['content_sha256'], 'file_sha256' => $s['file_sha256'], 'file_bytes' => $s['file_bytes'],
                'compression' => 'gzip', 'encoding' => 'ndjson; values as strings; non-UTF-8 as {"$b64": …}',
                'created_at' => gmdate('c', $this->now()), 'retention_months' => $this->config->retentionMonths,
                'expires_at' => gmdate('c', ArchiveFiles::deadline($s['day'] * 86400, $this->config->retentionMonths)),
            ];
            $manifestPart = $this->config->tmpDir()."/{$s['id']}.manifest.json.part";
            ArchiveFiles::atomicWrite($this->config, $manifestPart, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if (! @rename($this->partPath($s['id']), ArchiveFiles::dataPath($this->config, $s['id']))) {
                throw new ArchiveAborted('archive file cannot be published');
            }
            if ($i === 0) {
                $this->checkpoint('published_data', $j);
            }
            if (! @rename($manifestPart, $this->config->archivesDir()."/{$s['id']}.manifest.json")) {
                throw new ArchiveAborted('archive manifest cannot be published');
            }
        }
        ArchiveFiles::syncDir($this->config->archivesDir());
        $this->checkpoint('published', $j);

        $digest = ArchiveFiles::digest($this->config, array_column($segments, 'id'));
        if ($digest === null) {
            throw new ArchiveAborted('archive manifests cannot be read back');
        }
        $j->set(['state' => ArchiveJournal::VERIFIED, 'verified_at' => $this->now(), 'digest' => $digest], $this->now());
        $this->checkpoint('verified', $j);
    }

    /** @return array<string, mixed> */
    private function openSegment(string $runId, int $day): array
    {
        $id = $runId.'-'.gmdate('Ymd', $day * 86400);
        $part = $this->partPath($id);
        @unlink($part);
        $gz = @gzopen($part, 'wb6');
        if ($gz === false) {
            throw new ArchiveAborted('archive file cannot be created');
        }
        @chmod($part, $this->config->fileMode);

        return ['id' => $id, 'day' => $day, 'gz' => $gz, 'hash' => hash_init('sha256'), 'rows' => 0, 'min_id' => null, 'max_id' => null,
            'min_created_at' => null, 'max_created_at' => null];
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    private function closeSegment(array $s): array
    {
        $part = $this->partPath($s['id']);
        if (! @gzclose($s['gz']) || ! ArchiveFiles::fsyncFile($part)) {
            throw new ArchiveAborted('archive file cannot be completed (disk full or I/O error)');
        }

        return ['id' => $s['id'], 'day' => $s['day'], 'rows' => $s['rows'], 'min_id' => $s['min_id'], 'max_id' => $s['max_id'],
            'min_created_at' => $s['min_created_at'], 'max_created_at' => $s['max_created_at'], 'content_sha256' => hash_final($s['hash']),
            'file_sha256' => hash_file('sha256', $part), 'file_bytes' => (int) filesize($part)];
    }

    /**
     * The segment file re-read from disk must match its recorded checksums and count, and every record must equal the
     * row still in the table. Returns the problem, or null.
     *
     * @param  array<string, mixed>  $s
     * @param  list<string>  $columns
     */
    private function verifyAgainstTable(array $s, string $path, array $columns): ?string
    {
        $problems = ArchiveFiles::check($path, (int) $s['rows'], (string) $s['content_sha256'], (string) $s['file_sha256']);
        if ($problems !== []) {
            return implode('; ', $problems);
        }
        $compare = function (array $batch) use ($columns): ?string {
            $live = $this->db()->table(self::TABLE)->whereIn('id', array_keys($batch))->get($columns)->keyBy('id');
            foreach ($batch as $id => $line) {
                $row = $live->get($id) ?? $live->get((string) $id);
                if ($row === null) {
                    return 'archived record no longer in the table';
                }
                if (ArchiveFiles::encode((array) $row, $columns) !== $line) {
                    return 'archived record differs from the table';
                }
            }

            return null;
        };
        try {
            $batch = [];
            foreach (ArchiveFiles::lines($path) as $line) {
                $batch[(int) ArchiveFiles::decode($line)['id']] = $line;
                if (count($batch) >= $this->config->chunkRows) {
                    if (($p = $compare($batch)) !== null) {
                        return $p;
                    }
                    $batch = [];
                }
            }

            return $batch === [] ? null : $compare($batch);
        } catch (ArchiveAborted $e) {
            return $e->getMessage();
        }
    }

    /** @return list<string> integrity problems of the run's published segments (empty = all intact) */
    private function segmentProblems(ArchiveJournal $j): array
    {
        $problems = [];
        foreach ((array) $j->data['segments'] as $s) {
            $m = ArchiveFiles::manifest($this->config, (string) $s['id']);
            $p = $m === null ? ['manifest missing']
                : ArchiveFiles::check(ArchiveFiles::dataPath($this->config, (string) $s['id']), (int) $m['rows'], (string) $m['content_sha256'], (string) $m['file_sha256']);
            if ($m !== null && ($m['file_sha256'] !== $s['file_sha256'] || (int) $m['rows'] !== (int) $s['rows'])) {
                $p[] = 'manifest differs from the run journal';
            }
            foreach ($p as $problem) {
                $problems[] = "{$s['id']}: {$problem}";
            }
        }

        return $problems;
    }

    /** @return bool true when every segment's rows were deleted; false when stopped (verify failed / confirmation invalid) */
    private function deleteArchived(ArchiveJournal $j): bool
    {
        if (($problems = $this->segmentProblems($j)) !== []) {
            $j->set(['state' => ArchiveJournal::VERIFY_FAILED, 'error' => 'archive integrity failed before deletion: '.implode('; ', $problems)], $this->now());

            return false;
        }
        if ($this->config->requireBackupConfirmation && ! $this->confirmationValid($j)) {
            $j->set(['state' => ArchiveJournal::VERIFIED], $this->now());   // back to waiting; nothing (more) deleted

            return false;
        }

        $segments = array_values((array) $j->data['segments']);
        $deleted = (int) $j->data['deleted_rows'];
        for ($si = (int) $j->data['delete_segment']; $si < count($segments); $si++) {
            $chunkIndex = 0;
            $done = $si === (int) $j->data['delete_segment'] ? (int) $j->data['delete_chunk'] : 0;
            $ids = [];
            $flush = function (array $ids) use ($j, $si, &$chunkIndex, &$done, &$deleted) {
                if ($chunkIndex++ < $done) {
                    return;   // deleted before a crash
                }
                $n = $this->db()->table(self::TABLE)->whereIn('id', $ids)->where('id', '<=', $j->data['high_id'])
                    ->where('created_at', '<', $j->data['cutoff'])->delete();
                $deleted += $n;
                $done = $chunkIndex;
                $j->set(['delete_segment' => $si, 'delete_chunk' => $done, 'deleted_rows' => $deleted], $this->now());
                $this->checkpoint('delete_chunk', $j);
                if ($this->config->deletePauseMs > 0) {
                    usleep($this->config->deletePauseMs * 1000);
                }
            };
            foreach (ArchiveFiles::lines(ArchiveFiles::dataPath($this->config, (string) $segments[$si]['id'])) as $line) {
                $ids[] = (int) ArchiveFiles::decode($line)['id'];
                if (count($ids) >= $this->config->chunkRows) {
                    $flush($ids);
                    $ids = [];
                }
            }
            if ($ids !== []) {
                $flush($ids);
            }
            $j->set(['delete_segment' => $si + 1, 'delete_chunk' => 0], $this->now());
        }

        $j->set(['state' => ArchiveJournal::COMPLETED, 'completed_at' => $this->now()], $this->now());
        $this->checkpoint('completed', $j);

        return true;
    }

    /** Every file of a run: published segments and manifests, and tmp parts. @return list<string> */
    private function runFiles(string $runId): array
    {
        $files = [];
        foreach ([$this->config->archivesDir(), $this->config->tmpDir()] as $dir) {
            foreach (@scandir($dir) ?: [] as $name) {
                if (str_starts_with($name, $runId.'-') && is_file($dir.'/'.$name)) {
                    $files[] = $dir.'/'.$name;
                }
            }
        }

        return $files;
    }

    private function partPath(string $segmentId): string
    {
        return $this->config->tmpDir()."/{$segmentId}.ndjson.gz.part";
    }

    /** @return list<string> */
    private function columns(): array
    {
        $columns = $this->db()->getSchemaBuilder()->getColumnListing(self::TABLE);
        if (! in_array('id', $columns, true) || ! in_array('created_at', $columns, true)) {
            throw new ArchiveAborted('download_rejections has an unexpected schema');
        }

        return $columns;
    }

    private function isMysql(): bool
    {
        return in_array($this->db()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function db(): Connection
    {
        return $this->db ?? DB::connection($this->config->connection);
    }

    private function now(): int
    {
        return $this->clock !== null ? (int) ($this->clock)() : time();
    }

    private function checkpoint(string $point, ArchiveJournal $j): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $j->data);
        }
    }
}
