<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\SyncLegacyAdminPermissionsAction;
use Illuminate\Console\Command;

/**
 * AdminCP Real-User Permission Parity Reconciliation (2026-08-23) — the
 * deployment-facing entry point for `SyncLegacyAdminPermissionsAction`.
 * Run after any deploy where `nuke_authors` admin rows changed (new admin,
 * `radminsuper` flip, permission-blob edit made directly against legacy,
 * or the very first run against an environment that has never had this
 * bridge executed). Safe to run repeatedly — idempotent, converges to the
 * current legacy state every time, never expands scope beyond what
 * `nuke_authors` itself says.
 */
class SyncAdminPermissions extends Command
{
    protected $signature = 'admin:sync-permissions {--dry-run : Preview the resulting grants without writing them}';

    protected $description = "Synchronize every real admin's Spatie role/permissions from their legacy radminsuper flag and permissions blob";

    public function handle(SyncLegacyAdminPermissionsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = $action->execute($dryRun);

        $this->table(
            ['id', 'aid', 'super-admin', 'permissions granted'],
            $results->map(fn (array $r) => [
                $r['id'],
                $r['aid'],
                $r['super_admin'] ? 'yes' : 'no',
                $r['permissions'] === [] ? '—' : implode(', ', $r['permissions']),
            ])->all()
        );

        $this->info($dryRun
            ? "Dry run — {$results->count()} admin accounts inspected, no changes written."
            : "Synchronized {$results->count()} admin accounts.");

        return self::SUCCESS;
    }
}
