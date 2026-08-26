<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * AdminCP Real-User Permission Parity Reconciliation (2026-08-23).
 *
 * Spatie's role/permission tables live on `config('database.default')`
 * (ADR-0011, `App\Support\Permission\Role`/`Permission`'s own
 * `getConnectionName()` override) — deliberately never on `main`, where
 * `AdminUser`/`nuke_authors` lives. That separation is correct, but no
 * mechanism was ever built to populate those tables FOR the real admin
 * rows that already exist on `main`: `RoleSeeder::seedAdminRoles()`'s own
 * docblock explicitly defers this ("Populate real per-admin role/
 * permission assignments once ... a real data-migration pass over
 * `nuke_authors.permissions` can run") and nothing ever picked it up.
 * Confirmed via `model_has_roles`/`model_has_permissions` both being
 * completely empty on the default connection — every real admin,
 * including `radminsuper=1` super-admins, therefore has zero Spatie
 * grants, so `AdminDashboardModules::visibleFor()` and every
 * `admin.permission:...`-gated route resolve to nothing for everyone.
 *
 * This action is the missing bridge, run on demand (never per-request —
 * see the `admin:sync-permissions` command) and idempotent: re-running it
 * with unchanged legacy data produces the identical Spatie state every
 * time (`assignRole`/`removeRole` are no-ops when already correct;
 * `syncPermissions()` replaces the grant set exactly, not additively).
 *
 * Mapping rules, matching Wave 5/decision-log #9's already-established
 * `{module}.{key}` namespace exactly — no permission name is invented:
 * - `radminsuper=1` -> the `super-admin` role (guard `admin`), mirroring
 *   legacy's own full-access bypass (`admincp/index.php`).
 * - Every `module => [key => 'on', ...]` entry in the legacy
 *   `nuke_authors.permissions` serialized blob becomes a candidate
 *   `"{module}.{key}"` permission name. Only names that already exist as
 *   a real, seeded `Permission` row (guard `admin`) are ever granted —
 *   any legacy key with no Spatie counterpart (e.g. `khotab/menu.php`'s
 *   excluded content-CRUD keys, Business Confirmation #6) is silently
 *   and correctly ignored, never fabricated.
 *
 * The raw legacy blob is read via `getRawOriginal()` — never written,
 * never re-serialized — this is one-directional, legacy-to-Spatie only.
 */
class SyncLegacyAdminPermissionsAction
{
    /**
     * @return Collection<int, array{id: int, aid: string, super_admin: bool, permissions: list<string>}>
     */
    public function execute(bool $dryRun = false): Collection
    {
        // Defensive: a permission/role seeded in an earlier, separate
        // process (e.g. `db:seed` run before this command, in its own
        // PHP process) can leave Spatie's persistent permission cache
        // stale for this run, causing `syncPermissions()` below to reject
        // a real, just-seeded permission name as unknown. Confirmed by
        // reproduction, not hypothetical — this command must be
        // self-sufficient regardless of what ran immediately before it.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $validPermissionNames = Permission::where('guard_name', 'admin')->pluck('name')->all();

        return AdminUser::on('main')->get()->map(function (AdminUser $admin) use ($superAdminRole, $validPermissionNames, $dryRun) {
            $isSuperAdmin = (bool) $admin->radminsuper;
            $grantedPermissions = $this->resolveLegacyPermissionNames(
                $admin->getRawOriginal('permissions'),
                $validPermissionNames
            );

            if (! $dryRun) {
                if ($isSuperAdmin && ! $admin->hasRole($superAdminRole)) {
                    $admin->assignRole($superAdminRole);
                } elseif (! $isSuperAdmin && $admin->hasRole($superAdminRole)) {
                    $admin->removeRole($superAdminRole);
                }

                $admin->syncPermissions($grantedPermissions);
            }

            return [
                'id' => $admin->id,
                'aid' => $admin->aid,
                'super_admin' => $isSuperAdmin,
                'permissions' => $grantedPermissions,
            ];
        });
    }

    /**
     * Safely parses the legacy serialized blob — `allowed_classes: false`
     * so a malformed or crafted value can never instantiate a PHP object,
     * only ever produce arrays/scalars or fail to `null`/`false`, both of
     * which resolve to "no grants" rather than an error.
     *
     * @param  list<string>  $validPermissionNames
     * @return list<string>
     */
    private function resolveLegacyPermissionNames(?string $raw, array $validPermissionNames): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $legacy = @unserialize($raw, ['allowed_classes' => false]);

        if (! is_array($legacy)) {
            return [];
        }

        $names = [];
        foreach ($legacy as $module => $keys) {
            if (! is_string($module) || ! is_array($keys)) {
                continue;
            }

            foreach (array_keys($keys) as $key) {
                if (is_string($key)) {
                    $names[] = "{$module}.{$key}";
                }
            }
        }

        return array_values(array_intersect($names, $validPermissionNames));
    }
}
