<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\Anasheed\F07LinkRepair;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDeactivation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Throwable;

/**
 * B2 / F07-B2 — apply (or roll back) the 46 approved, byte-exact Variety link corrections ({@see F07LinkRepair}).
 *
 * Dry run by default (read-only). `--apply` writes after a verified backup; `--rollback=<backup>` restores from it
 * (dry run unless `--apply`). Every write needs `--actor=<AdminCP aid>` (super-admin, or both
 * `anasheed.editanasheed` and `anasheed.editmirror`) and is audited.
 */
class AnasheedRepairF07Links extends Command
{
    protected $signature = 'anasheed:repair-f07-links
        {--apply : Write the repair (default: dry run, read-only)}
        {--rollback= : Restore the original values from this backup file (dry run unless --apply)}
        {--skip-conflicts : With --rollback --apply: restore unchanged rows, leave conflicting rows untouched}
        {--actor= : AdminCP aid the write is attributed to (required for any write)}
        {--backup-dir= : Backup directory (default: storage/app/maintenance/anasheed-f07b2)}
        {--json : Print the report as JSON}';

    protected $description = 'B2/F07-B2: byte-exact correction of the 46 approved Variety media links (dry run by default; backup + audited apply; rollback)';

    public function handle(F07LinkRepair $repair): int
    {
        try {
            $rollback = $this->option('rollback');
            if (is_string($rollback) && $rollback !== '') {
                return $this->rollback($repair, $rollback);
            }

            return $this->option('apply') ? $this->apply($repair) : $this->dryRun($repair);
        } catch (AuthorizationException) {
            $this->error('The actor lacks anasheed.editanasheed / anasheed.editmirror (or super-admin). Nothing was written.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        return self::FAILURE;
    }

    private function dryRun(F07LinkRepair $repair): int
    {
        $plan = $repair->plan();
        $this->report($plan, 'DRY RUN (no database write)');

        return $plan['verdict'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }

    private function apply(F07LinkRepair $repair): int
    {
        $actor = $this->actor();
        if ($actor === null) {
            return self::FAILURE;
        }

        $result = $repair->apply($actor, $this->backupDirectory());
        $this->report($result['plan'], 'APPLY');

        if ($result['verdict'] === 'already_applied') {
            $this->info('No repair necessary: every approved row already holds its approved value. Nothing was written.');

            return self::SUCCESS;
        }
        if ($result['verdict'] !== 'applied') {
            $this->error('Refused: at least one row does not match its approved state. Nothing was written.');

            return self::FAILURE;
        }

        $this->info("Corrected {$result['updated']} link(s). Backup: {$result['backup']}");

        return self::SUCCESS;
    }

    private function rollback(F07LinkRepair $repair, string $path): int
    {
        if (! $this->option('apply')) {
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
            $this->error("Refused: {$result['skipped_conflicts']} row(s) changed after the repair. Nothing was written (see --skip-conflicts).");

            return self::FAILURE;
        }

        $this->info("Restored {$result['restored']} link(s); {$result['skipped_conflicts']} conflicting row(s) left untouched.");

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

        return is_string($dir) && $dir !== '' ? $dir : storage_path('app/maintenance/anasheed-f07b2');
    }

    /** Values are JSON-encoded so leading / trailing spaces are visible. */
    private static function show(?string $value): string
    {
        return $value === null ? '—' : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $plan */
    private function report(array $plan, string $mode): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['mode' => $mode] + $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line($mode);
        $this->table(['type', 'id', 'reason', 'state', 'current', 'approved new'],
            array_map(fn (array $r) => [$r['type'], $r['id'], $r['reason'], $r['state'], self::show($r['current']), self::show($r['new'])], $plan['rows']));
        $counts = $plan['counts'];
        $this->line(sprintf('Rows: %d (pending %d, applied %d). Verdict: %s', count($plan['rows']), $counts['pending'] ?? 0, $counts['applied'] ?? 0, $plan['verdict']));
        foreach ($plan['problems'] as $problem) {
            $this->warn('Discrepancy: '.$problem);
        }
    }

    /** @param array<string, mixed> $plan */
    private function rollbackReport(array $plan, string $mode): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['mode' => $mode, 'rows' => $plan['rows'], 'restorable' => $plan['restorable'], 'restored' => $plan['restored'],
                'conflicts' => $plan['conflicts'], 'backup' => $plan['backup']['path']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line($mode.' — '.$plan['backup']['path']);
        $this->table(['type', 'id', 'current', 'repaired value', 'original value', 'state'],
            array_map(fn (array $r) => [$r['type'], $r['id'], self::show($r['current']), self::show($r['new']), self::show($r['old']), $r['state']], $plan['rows']));
        $this->line(sprintf('restorable %d, already original %d, conflicts %d', $plan['restorable'], $plan['restored'], $plan['conflicts']));
    }
}
