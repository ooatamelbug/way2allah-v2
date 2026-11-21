<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\MigrateOldAdminPermissionsAction;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDeactivation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * One-time cutover step: grant existing regular administrators the Laravel equivalents of their OLD module and
 * per-action permissions (MigrateOldAdminPermissionsAction). Additive and idempotent; never revokes; never touches
 * super-admins. `--dry-run` writes nothing but the report file. Run AFTER `admin:sync-permissions` (which replaces
 * permissions) and never re-run that sync afterwards.
 */
class MigrateOldAdminPermissions extends Command
{
    protected $signature = 'admin:migrate-old-permissions
        {--dry-run : Show every proposed grant and unresolved mapping; write nothing except the report file}
        {--actor= : AdminCP aid of the super-admin attributed in the audit log (required to write)}
        {--report= : Path of the JSON report (default storage/app/admin-permission-migration/<timestamp>.json)}';

    protected $description = 'One-time, additive migration of OLD administrator permissions (nuke_modules.admins + nuke_*_mod tokens) to Laravel permissions';

    public function handle(MigrateOldAdminPermissionsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $actor = null;
        if (! $dryRun) {
            $aid = trim((string) $this->option('actor'));
            $actor = $aid === '' ? null : AdminUser::on('main')->where('aid', $aid)->first();
            if ($actor === null || ! $actor->hasRole('super-admin') || AdminDeactivation::isDeactivated((int) $actor->getKey())) {
                $this->error('--actor=<aid> must name an active super-admin for a write. Nothing was written.');

                return self::FAILURE;
            }
        }

        $result = $dryRun ? $action->plan() : $action->apply($actor);

        $this->table(['id', 'OLD modules', 'grant', 'already held', 'unresolved'], array_map(fn (array $a) => [
            $a['id'],
            implode(', ', $a['old_modules'] ?? []),
            isset($a['skipped']) ? 'skipped: '.$a['skipped'] : (($a['grant'] ?? []) === [] ? '—' : implode(' ', $a['grant'])),
            count($a['already_held'] ?? []),
            implode(' | ', $a['unresolved'] ?? []),
        ], $result['admins']));

        $path = (string) ($this->option('report') ?: storage_path('app/admin-permission-migration/'.date('Ymd-His').($dryRun ? '-dry-run' : '-applied').'.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['dry_run' => $dryRun, 'at' => date('c'), 'actor_id' => $actor?->getKey()] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $t = $result['totals'];
        $this->info(($dryRun ? 'Dry run — nothing written. ' : 'Applied. ')
            ."Admins considered: {$t['admins_considered']}; with grants: {$t['admins_with_changes']}; grants: {$t['grants_proposed']}; "
            ."super-admins skipped: {$t['skipped_super_admin']}; deactivated skipped: {$t['skipped_deactivated']}; unresolved items: {$t['unresolved_items']}. Report: {$path}");

        return self::SUCCESS;
    }
}
