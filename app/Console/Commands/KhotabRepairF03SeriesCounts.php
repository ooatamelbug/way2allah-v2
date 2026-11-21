<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\Khotab\F03SeriesCountRepair;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDeactivation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Throwable;

/**
 * B2 / F03-A — repair (or roll back) the stored `count` of the 22 approved Khotab series ({@see F03SeriesCountRepair}).
 *
 * Dry run by default: read-only, prints every series with its stored, approved and recomputed counts. `--apply` writes
 * (after a verified backup), `--rollback=<backup>` restores from that backup; both need `--actor=<AdminCP aid>`
 * (super-admin or `khotab.editauthor`) so every write is attributed in `admin_audit_log`.
 */
class KhotabRepairF03SeriesCounts extends Command
{
    protected $signature = 'khotab:repair-f03-series-counts
        {--apply : Write the repair (default: dry run, read-only)}
        {--rollback= : Restore the original counts from this backup file (dry run unless --apply)}
        {--skip-conflicts : With --rollback --apply: restore the unchanged rows and leave conflicting rows untouched}
        {--actor= : AdminCP aid the write is attributed to (required with --apply)}
        {--backup-dir= : Directory for the backup file (default: storage/app/maintenance/khotab-f03a)}
        {--json : Print the report as JSON}';

    protected $description = 'B2/F03-A: repair the stored count of the 22 approved Khotab series (dry run by default; backup + audited apply; rollback)';

    public function handle(F03SeriesCountRepair $repair): int
    {
        $apply = (bool) $this->option('apply');
        $rollback = $this->option('rollback');

        try {
            if (is_string($rollback) && $rollback !== '') {
                return $this->rollback($repair, $rollback, $apply);
            }

            return $apply ? $this->apply($repair) : $this->dryRun($repair);
        } catch (AuthorizationException) {
            $this->error('The actor lacks khotab.editauthor (or super-admin). Nothing was written.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        return self::FAILURE;
    }

    private function dryRun(F03SeriesCountRepair $repair): int
    {
        $plan = $repair->plan();
        $this->report($plan, 'DRY RUN (no database write)');

        return $plan['verdict'] === F03SeriesCountRepair::BLOCKED ? self::FAILURE : self::SUCCESS;
    }

    private function apply(F03SeriesCountRepair $repair): int
    {
        $actor = $this->actor();
        if ($actor === null) {
            return self::FAILURE;
        }

        $result = $repair->apply($actor, $this->backupDirectory());
        $this->report($result['plan'], 'APPLY');

        if ($result['verdict'] === F03SeriesCountRepair::ALREADY_REPAIRED) {
            $this->info('No repair necessary: all 22 series already hold their approved counts. Nothing was written.');

            return self::SUCCESS;
        }
        if ($result['verdict'] !== 'applied') {
            $this->error('Refused: the data does not match the approved baseline. Nothing was written.');

            return self::FAILURE;
        }

        $this->info("Repaired {$result['updated']} series. Backup: {$result['backup']}");

        return self::SUCCESS;
    }

    private function rollback(F03SeriesCountRepair $repair, string $path, bool $apply): int
    {
        if (! $apply) {
            $plan = $repair->planRollback($path);
            $this->rollbackReport($plan, 'ROLLBACK DRY RUN (no database write)');

            return $plan['conflicts'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $actor = $this->actor();
        if ($actor === null) {
            return self::FAILURE;
        }

        $result = $repair->rollback($actor, $path, (bool) $this->option('skip-conflicts'));
        $this->rollbackReport($result['plan'], 'ROLLBACK');

        if ($result['restored'] === 0 && $result['skipped_conflicts'] > 0 && ! $this->option('skip-conflicts')) {
            $this->error("Refused: {$result['skipped_conflicts']} series changed after the repair. Nothing was written (see --skip-conflicts).");

            return self::FAILURE;
        }

        $this->info("Restored {$result['restored']} series; {$result['skipped_conflicts']} conflicting series left untouched.");

        return $result['skipped_conflicts'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function actor(): ?AdminUser
    {
        $aid = trim((string) $this->option('actor'));
        $actor = $aid === '' ? null : AdminUser::query()->where('aid', $aid)->first();
        // F02: a deactivated admin cannot be the attributed actor of a write.
        if ($actor !== null && AdminDeactivation::isDeactivated((int) $actor->getKey())) {
            $actor = null;
        }
        if ($actor === null) {
            $this->error('--actor=<AdminCP aid> is required for a write and must name an existing admin. Nothing was written.');
        }

        return $actor;
    }

    private function backupDirectory(): string
    {
        $dir = $this->option('backup-dir');

        return is_string($dir) && $dir !== '' ? $dir : storage_path('app/maintenance/khotab-f03a');
    }

    /** @param array<string, mixed> $plan */
    private function report(array $plan, string $mode): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['mode' => $mode] + $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line($mode);
        $this->table(['series', 'author', 'group', 'stored', 'approved before', 'recomputed', 'visible own', 'approved after', 'state', 'eligible'],
            array_map(fn (array $r) => [$r['id'], $r['author_id'], $r['group_id'], $r['stored_count'] ?? '—', $r['approved_before'],
                $r['recomputed'], $r['visible_own_items'], $r['approved_after'], $r['state'], $r['eligible'] ? 'yes' : 'no'], $plan['rows']));
        $this->line(sprintf('Own items: %d (expected %d). Verdict: %s', $plan['total_own_items'], F03SeriesCountRepair::EXPECTED_TOTAL_ITEMS, $plan['verdict']));
        foreach ($plan['problems'] as $problem) {
            $this->warn('Discrepancy: '.$problem);
        }
    }

    /** @param array<string, mixed> $plan */
    private function rollbackReport(array $plan, string $mode): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['mode' => $mode, 'rows' => $plan['rows'], 'restorable' => $plan['restorable'],
                'restored' => $plan['restored'], 'conflicts' => $plan['conflicts'], 'backup' => $plan['backup']['path']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line($mode.' — '.$plan['backup']['path']);
        $this->table(['series', 'current', 'repaired value', 'original value', 'state'],
            array_map(fn (array $r) => [$r['id'], $r['current'] ?? '—', $r['repaired_value'], $r['original_value'], $r['state']], $plan['rows']));
        $this->line(sprintf('restorable %d, already original %d, conflicts %d', $plan['restorable'], $plan['restored'], $plan['conflicts']));
    }
}
