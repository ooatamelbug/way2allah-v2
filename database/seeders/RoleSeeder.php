<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Role;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Baseline roles reproducing the legacy authorization state as-is (Wave 0,
 * task 0.5) — no redesign yet, per ADR-0011 §"Authorization". Two separate
 * guard-scoped role sets, matching the two separate Guards; a role created
 * under one guard_name is never usable by the other.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedVbulletinRoles();
        $this->seedAdminRoles();
    }

    /**
     * Public-site authorization. Legacy source: w2a_config.php's
     * `$SuperAdmins = array(1, 16715);` (Fact — the literal, current array).
     * Replaces this hardcoded array with an equivalent role assignment.
     *
     * The role itself always gets created — it lives on the app's default
     * connection (App\Support\Permission\Role) and needs no vBulletin
     * access. Assigning it to the two legacy ids does need a reachable
     * 'vbulletin' connection, which is not guaranteed yet (Infrastructure
     * Confirmation #3): `db:seed` must remain safe to run against an
     * environment where that connection isn't configured — assignment is
     * skipped with a warning in that case, not a hard failure that would
     * also abort seedAdminRoles() below.
     */
    private function seedVbulletinRoles(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'vbulletin']);

        foreach ([1, 16715] as $legacySuperAdminId) {
            try {
                VbUser::on('vbulletin')->find($legacySuperAdminId)?->assignRole($superAdmin);
            } catch (Throwable $e) {
                $this->command?->warn(
                    "RoleSeeder: could not assign 'super-admin' to vBulletin user {$legacySuperAdminId} — ".
                    "'vbulletin' connection unreachable (Infrastructure Confirmation #3 still open). ".
                    'Role definition was still created; re-run once the connection is configured.'
                );

                return; // Don't retry the second id against a connection already known to be unreachable.
            }
        }
    }

    /**
     * Admin-panel authorization. Legacy source: admincp/index.php derives
     * exactly two role tiers from the `radminsuper` column at login time
     * ("superadmin" if radminsuper == 1, else "admin") — the role
     * *structure* is confirmed by this Fact. `nuke_authors.permissions`
     * (a per-admin serialized-array blob controlling fine-grained feature
     * access beyond the superadmin/admin split) is NOT reproduced here:
     * its actual contents are only readable from a live database, not from
     * source, and assigning specific permissions to specific admin ids
     * without that live read would be fabricated data, not a migration of
     * real state. Populate real per-admin role/permission assignments once
     * Infrastructure Confirmation #3 (DB reachability) is resolved and a
     * real data-migration pass over `nuke_authors.permissions` can run.
     */
    private function seedAdminRoles(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    }
}
