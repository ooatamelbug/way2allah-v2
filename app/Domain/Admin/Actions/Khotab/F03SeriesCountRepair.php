<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * B2 / F03-A (owner-approved 2026-11-16, decision-log #145) — the targeted repair of the stored `count` of exactly 22
 * visible Khotab series whose counter was lost in the 2025-09-15/16 series renumbering (`old_id = id − 800`) and is
 * now `0` or negative, although each holds visible items of its own author. See
 * `docs/reports/admincp-b2-f03-resolution-proposal.md` and `…-counter-repair-results.md`.
 *
 * **Scope is closed:** only the ids in `BASELINE`, each with its approved identity (author, group, media type,
 * `old_id`), its approved current count and its approved repaired count. Nothing is discovered at run time; any
 * difference blocks the run. The existing «إصلاح العدادات» (`RepairKhotabAuthorCounters*`) is not used: it counts other
 * authors' items and rewrites items' `group_id`.
 *
 * **Criterion:** a series' count = its items (`ser_id`) of the series' OWN author, hidden included (the convention
 * every writer follows). Each series must still hold at least one visible own item. Other authors' items in the same
 * series (renumbering collisions) are never counted.
 *
 * **Writes (apply):** `nuke_islamic_series.count` of the 22 rows, nothing else, plus one `admin_audit_log` row per
 * series, in one transaction, each `UPDATE` conditioned on the full approved identity and the approved current count.
 * A verified backup file is written before the transaction; without it nothing is written.
 *
 * **Rollback:** from that backup file only; a row is restored only if it still holds the repaired value (a later
 * legitimate change is a conflict and is never overwritten).
 */
final class F03SeriesCountRepair
{
    public const PERMISSION = 'khotab.editauthor';

    public const BACKUP_FORMAT = 'w2a.khotab.f03a.series-count-backup';

    public const BACKUP_VERSION = 1;

    public const EXPECTED_TOTAL_ITEMS = 316;

    /**
     * The approved baseline (local evidence, 2026-11-16): id => [author_id, group_id, vedio, old_id, count_before,
     * count_after]. `count_after` = the series' own items.
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}>
     */
    public const BASELINE = [
        15346 => [215, 0, 1, 14546, -1, 25],
        15404 => [154, 1138, 1, 14604, -1, 8],
        15495 => [154, 1138, 1, 14695, 0, 30],
        15496 => [154, 1138, 1, 14696, 0, 30],
        15498 => [154, 1138, 1, 14698, 0, 30],
        15503 => [154, 1138, 1, 14703, 0, 30],
        15509 => [154, 1138, 1, 14709, 0, 15],
        15510 => [154, 1138, 1, 14710, -1, 27],
        15561 => [16, 0, 1, 14761, -2, 26],
        15616 => [154, 1138, 1, 14816, -15, 15],
        15625 => [154, 0, 1, 14825, -20, 2],
        15627 => [154, 232, 1, 14827, 0, 24],
        15681 => [200, 0, 0, 14881, -2, 1],
        15743 => [772, 0, 0, 14943, -5, 6],
        15746 => [211, 0, 1, 14946, -9, 2],
        15752 => [763, 0, 1, 14952, -4, 7],
        15756 => [763, 0, 1, 14956, -39, 7],
        15765 => [588, 0, 1, 14965, -4, 1],
        15766 => [195, 1298, 1, 14966, -3, 3],
        15884 => [22, 0, 0, 15084, -32, 1],
        15949 => [154, 1138, 1, 15149, 0, 13],
        15950 => [154, 1138, 1, 15150, -1, 13],
    ];

    /** Row states. */
    public const PENDING = 'pending';

    public const REPAIRED = 'repaired';

    public const MISSING = 'missing';

    public const IDENTITY_CHANGED = 'identity_changed';

    public const GROUP_MISMATCH = 'group_mismatch';

    public const COUNT_CHANGED = 'count_changed';

    public const ITEMS_CHANGED = 'items_changed';

    public const NO_VISIBLE_ITEMS = 'no_visible_items';

    /** Overall verdicts. */
    public const READY = 'ready';

    public const ALREADY_REPAIRED = 'already_repaired';

    public const BLOCKED = 'blocked';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * Read-only analysis of the 22 rows against the baseline.
     *
     * @return array{verdict: string, total_own_items: int, rows: list<array<string, mixed>>, problems: list<string>}
     */
    public function plan(bool $lock = false): array
    {
        $main = $this->main();
        $ids = array_keys(self::BASELINE);

        $seriesQuery = $main->table('nuke_islamic_series')->whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $seriesQuery->lockForUpdate();
        }
        $series = $seriesQuery->get(['id', 'author_id', 'group_id', 'vedio', 'hidden', 'old_id', 'count'])->keyBy('id');

        $groupIds = array_values(array_filter(array_unique(array_map(fn (array $b) => $b[1], self::BASELINE))));
        $groups = $main->table('nuke_islamic_groups')->whereIn('id', $groupIds)->get(['id', 'author_id', 'vedio'])->keyBy('id');

        // Own items only: `author IN (the series' authors) AND ser_id IN (the 22)`; ownership is then checked per series.
        // A plain (non-locking, consistent) read: on the local copy MySQL scans the table (~290 k rows, sub-second)
        // rather than `idx_khotab_author_listing`; no item row is ever locked.
        $authors = array_values(array_unique(array_map(fn (array $b) => $b[0], self::BASELINE)));
        $counts = [];
        foreach ($main->table('nuke_islamic_khotab')->whereIn('author', $authors)->whereIn('ser_id', $ids)
            ->groupBy('ser_id', 'author')
            ->selectRaw('ser_id, author, COUNT(*) AS n, SUM(CASE WHEN hidden = 0 THEN 1 ELSE 0 END) AS visible')
            ->get() as $row) {
            if ((int) $row->author === self::BASELINE[(int) $row->ser_id][0]) {
                $counts[(int) $row->ser_id] = ['own' => (int) $row->n, 'visible' => (int) $row->visible];
            }
        }

        $rows = [];
        $problems = [];
        $total = 0;
        foreach (self::BASELINE as $id => [$author, $group, $vedio, $oldId, $before, $after]) {
            $own = $counts[$id]['own'] ?? 0;
            $visible = $counts[$id]['visible'] ?? 0;
            $total += $own;
            $current = $series->get($id);

            $state = match (true) {
                $current === null => self::MISSING,
                (int) $current->author_id !== $author || (int) $current->group_id !== $group
                    || (int) $current->vedio !== $vedio || (int) $current->hidden !== 0 || (int) $current->old_id !== $oldId => self::IDENTITY_CHANGED,
                $group > 0 && (! $groups->has($group) || (int) $groups->get($group)->author_id !== $author
                    || (int) $groups->get($group)->vedio !== $vedio) => self::GROUP_MISMATCH,
                $own !== $after => self::ITEMS_CHANGED,
                $visible === 0 => self::NO_VISIBLE_ITEMS,
                (int) $current->count === $before => self::PENDING,
                (int) $current->count === $after => self::REPAIRED,
                default => self::COUNT_CHANGED,
            };

            if (! in_array($state, [self::PENDING, self::REPAIRED], true)) {
                $problems[] = sprintf('series %d: %s', $id, $state);
            }

            $rows[] = [
                'id' => $id,
                'author_id' => $author,
                'group_id' => $group,
                'stored_count' => $current === null ? null : (int) $current->count,
                'approved_before' => $before,
                'recomputed' => $own,
                'visible_own_items' => $visible,
                'approved_after' => $after,
                'state' => $state,
                'eligible' => $state === self::PENDING,
            ];
        }

        $states = array_unique(array_column($rows, 'state'));
        if ($problems === [] && $total !== self::EXPECTED_TOTAL_ITEMS) {
            $problems[] = sprintf('own items total %d, expected %d', $total, self::EXPECTED_TOTAL_ITEMS);
        }

        $verdict = match (true) {
            $problems !== [] => self::BLOCKED,
            $states === [self::PENDING] => self::READY,
            $states === [self::REPAIRED] => self::ALREADY_REPAIRED,
            default => self::BLOCKED,   // a mix of repaired and unrepaired rows is never assumed safe
        };
        if ($verdict === self::BLOCKED && $problems === []) {
            $problems[] = 'partially repaired: some rows hold the approved value, others the original';
        }

        return ['verdict' => $verdict, 'total_own_items' => $total, 'rows' => $rows, 'problems' => $problems];
    }

    /**
     * Back up, then repair in one transaction. Nothing is written unless the plan is READY before AND inside the
     * transaction (rows locked) and the backup file was written and verified.
     *
     * @return array{verdict: string, backup: string|null, updated: int, plan: array<string, mixed>}
     */
    public function apply(AdminUser $actor, string $backupDirectory, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, self::PERMISSION);

        $plan = $this->plan();
        if ($plan['verdict'] !== self::READY) {
            return ['verdict' => $plan['verdict'], 'backup' => null, 'updated' => 0, 'plan' => $plan];
        }

        $backup = $this->writeBackup($actor, $backupDirectory, $plan);

        $main = $this->main();
        $updated = $main->transaction(function () use ($main, $actor, $ip, $backup) {
            $locked = $this->plan(lock: true);
            if ($locked['verdict'] !== self::READY) {
                throw new RuntimeException('F03-A: the data changed after the dry run; nothing was written ('.implode('; ', $locked['problems']).').');
            }

            $updated = 0;
            foreach (self::BASELINE as $id => [$author, $group, $vedio, $oldId, $before, $after]) {
                $affected = $main->table('nuke_islamic_series')
                    ->where('id', $id)->where('author_id', $author)->where('group_id', $group)->where('vedio', $vedio)
                    ->where('hidden', 0)->where('old_id', $oldId)->where('count', $before)
                    ->update(['count' => $after]);
                if ($affected !== 1) {
                    throw new RuntimeException("F03-A: series {$id} did not match its approved state at write time; nothing was written.");
                }
                $this->audit->record($actor, 'repair_series_count', 'khotab', 'series', $id, [
                    'from' => $before, 'to' => $after, 'reason' => 'b2_f03a', 'backup_sha256' => $backup['sha256'],
                ], $ip);
                $updated++;
            }

            return $updated;
        });

        $after = $this->plan();
        if ($after['verdict'] !== self::ALREADY_REPAIRED) {
            throw new RuntimeException('F03-A: post-check failed after commit ('.implode('; ', $after['problems']).'). Use the backup to roll back: '.$backup['path']);
        }

        return ['verdict' => 'applied', 'backup' => $backup['path'], 'updated' => $updated, 'plan' => $after];
    }

    /**
     * Read a backup and compare it with the current data. A row is RESTORABLE only while it still holds exactly the
     * repaired value (and its identity); `restored` when it already holds the original; anything else is a CONFLICT.
     *
     * @return array{rows: list<array<string, mixed>>, restorable: int, restored: int, conflicts: int, backup: array<string, mixed>}
     */
    public function planRollback(string $backupPath, bool $lock = false): array
    {
        $backup = self::readBackup($backupPath);
        $query = $this->main()->table('nuke_islamic_series')->whereIn('id', array_column($backup['series'], 'id'));
        if ($lock) {
            $query->lockForUpdate();
        }
        $current = $query->get(['id', 'author_id', 'group_id', 'vedio', 'hidden', 'old_id', 'count'])->keyBy('id');

        $rows = [];
        foreach ($backup['series'] as $entry) {
            $row = $current->get($entry['id']);
            $identity = $row !== null && (int) $row->author_id === $entry['author_id'] && (int) $row->group_id === $entry['group_id']
                && (int) $row->vedio === $entry['vedio'] && (int) $row->old_id === $entry['old_id'];
            $state = match (true) {
                ! $identity => 'conflict',
                (int) $row->count === $entry['count_after'] => 'restorable',
                (int) $row->count === $entry['count_before'] => 'restored',
                default => 'conflict',
            };
            $rows[] = ['id' => $entry['id'], 'current' => $row === null ? null : (int) $row->count,
                'repaired_value' => $entry['count_after'], 'original_value' => $entry['count_before'], 'state' => $state];
        }

        $states = array_count_values(array_column($rows, 'state'));

        return ['rows' => $rows, 'restorable' => $states['restorable'] ?? 0, 'restored' => $states['restored'] ?? 0,
            'conflicts' => $states['conflict'] ?? 0, 'backup' => $backup];
    }

    /**
     * Restore the original counts from a verified backup. With any conflict nothing is written, unless
     * `$skipConflicts`, in which case only the restorable rows are restored (conflicting rows are left untouched).
     *
     * @return array{restored: int, skipped_conflicts: int, plan: array<string, mixed>}
     */
    public function rollback(AdminUser $actor, string $backupPath, bool $skipConflicts = false, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, self::PERMISSION);
        $main = $this->main();

        return $main->transaction(function () use ($main, $actor, $backupPath, $skipConflicts, $ip) {
            $plan = $this->planRollback($backupPath, lock: true);
            if ($plan['conflicts'] > 0 && ! $skipConflicts) {
                return ['restored' => 0, 'skipped_conflicts' => $plan['conflicts'], 'plan' => $plan];
            }

            $restored = 0;
            foreach ($plan['backup']['series'] as $entry) {
                $row = collect($plan['rows'])->firstWhere('id', $entry['id']);
                if ($row['state'] !== 'restorable') {
                    continue;
                }
                $affected = $main->table('nuke_islamic_series')
                    ->where('id', $entry['id'])->where('author_id', $entry['author_id'])->where('group_id', $entry['group_id'])
                    ->where('vedio', $entry['vedio'])->where('old_id', $entry['old_id'])->where('count', $entry['count_after'])
                    ->update(['count' => $entry['count_before']]);
                if ($affected !== 1) {
                    throw new RuntimeException("F03-A rollback: series {$entry['id']} changed during the rollback; nothing was written.");
                }
                $this->audit->record($actor, 'rollback_series_count', 'khotab', 'series', $entry['id'], [
                    'from' => $entry['count_after'], 'to' => $entry['count_before'], 'reason' => 'b2_f03a', 'backup_sha256' => $plan['backup']['sha256'],
                ], $ip);
                $restored++;
            }

            return ['restored' => $restored, 'skipped_conflicts' => $plan['conflicts'], 'plan' => $plan];
        });
    }

    // ─── backup file ───────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Write the backup durably: a temporary file in the target directory, flushed and fsync'ed, renamed into place,
     * then read back and verified (format, the 22 ids, values, checksum). Any failure throws before any data write.
     *
     * @param  array<string, mixed>  $plan
     * @return array{path: string, sha256: string}
     */
    private function writeBackup(AdminUser $actor, string $directory, array $plan): array
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("F03-A: cannot create the backup directory {$directory}; nothing was written.");
        }
        if (! is_writable($directory)) {
            throw new RuntimeException("F03-A: the backup directory {$directory} is not writable; nothing was written.");
        }

        $payload = [
            'format' => self::BACKUP_FORMAT,
            'version' => self::BACKUP_VERSION,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'actor_aid' => (string) $actor->aid,
            'connection' => 'main',
            'database' => (string) $this->main()->getDatabaseName(),
            'total_own_items' => $plan['total_own_items'],
            'series' => array_map(fn (int $id) => [
                'id' => $id,
                'author_id' => self::BASELINE[$id][0],
                'group_id' => self::BASELINE[$id][1],
                'vedio' => self::BASELINE[$id][2],
                'old_id' => self::BASELINE[$id][3],
                'count_before' => self::BASELINE[$id][4],
                'count_after' => self::BASELINE[$id][5],
            ], array_keys(self::BASELINE)),
        ];
        $sha = hash('sha256', self::canonical($payload));
        $json = json_encode($payload + ['sha256' => $sha], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

        $path = rtrim($directory, '/').'/series-counts-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
        $temporary = $path.'.tmp';
        try {
            $handle = @fopen($temporary, 'xb');
            if ($handle === false) {
                throw new RuntimeException('cannot open the temporary backup file');
            }
            $written = fwrite($handle, $json);
            $flushed = fflush($handle) && (! function_exists('fsync') || fsync($handle));
            fclose($handle);
            if ($written !== strlen($json) || ! $flushed || ! @rename($temporary, $path)) {
                throw new RuntimeException('incomplete write');
            }
            @chmod($path, 0640);
            $verified = self::readBackup($path);
            if ($verified['sha256'] !== $sha) {
                throw new RuntimeException('read-back mismatch');
            }
        } catch (Throwable $e) {
            @unlink($temporary);
            throw new RuntimeException('F03-A: the backup could not be written and verified ('.$e->getMessage().'); nothing was written.', 0, $e);
        }

        return ['path' => $path, 'sha256' => $sha];
    }

    /**
     * Read and verify a backup file: format, version, checksum, and that it describes exactly the approved 22 rows
     * with the approved values (a tampered or foreign file is refused).
     *
     * @return array{series: list<array{id: int, author_id: int, group_id: int, vedio: int, old_id: int, count_before: int, count_after: int}>, sha256: string, path: string}
     */
    public static function readBackup(string $path): array
    {
        $raw = is_file($path) ? @file_get_contents($path) : false;
        if ($raw === false) {
            throw new RuntimeException("F03-A: backup file {$path} cannot be read.");
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ($data['format'] ?? null) !== self::BACKUP_FORMAT || ($data['version'] ?? null) !== self::BACKUP_VERSION
            || ! isset($data['sha256'], $data['series']) || ! is_array($data['series'])) {
            throw new RuntimeException("F03-A: {$path} is not an F03-A series-count backup.");
        }
        $sha = (string) $data['sha256'];
        unset($data['sha256']);
        if (! hash_equals(hash('sha256', self::canonical($data)), $sha)) {
            throw new RuntimeException("F03-A: backup {$path} failed its checksum.");
        }

        $series = [];
        foreach ($data['series'] as $entry) {
            $id = (int) ($entry['id'] ?? 0);
            $expected = self::BASELINE[$id] ?? null;
            if ($expected === null || [(int) $entry['author_id'], (int) $entry['group_id'], (int) $entry['vedio'], (int) $entry['old_id'],
                (int) $entry['count_before'], (int) $entry['count_after']] !== $expected) {
                throw new RuntimeException("F03-A: backup {$path} does not match the approved baseline (series {$id}).");
            }
            $series[$id] = ['id' => $id, 'author_id' => $expected[0], 'group_id' => $expected[1], 'vedio' => $expected[2],
                'old_id' => $expected[3], 'count_before' => $expected[4], 'count_after' => $expected[5]];
        }
        ksort($series);
        if (array_keys($series) !== array_keys(self::BASELINE)) {
            throw new RuntimeException("F03-A: backup {$path} does not list exactly the 22 approved series.");
        }

        return ['series' => array_values($series), 'sha256' => $sha, 'path' => $path];
    }

    /** @param array<string, mixed> $data */
    private static function canonical(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function main(): ConnectionInterface
    {
        return DB::connection('main');
    }
}
