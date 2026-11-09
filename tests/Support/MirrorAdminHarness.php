<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * OLD Admin Migration Phase 1 — shared setup for the mirror-administration
 * tests. A class rather than per-file Pest functions so four test files
 * can share it without global-function collisions.
 *
 * Every table comes from `MainSchema`, the project's single source of
 * truth for `main` fixtures, so these tests cannot drift from the rest of
 * the suite. AdminUser rows use the existing `create()` pattern (the
 * shared `nuke_authors` fixture has no `permissions` column, so the
 * `isGuardableColumn` hazard does not arise).
 */
final class MirrorAdminHarness
{
    public const KHOTAB_PERMISSIONS = ['khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror'];

    public const ANASHEED_PERMISSIONS = ['anasheed.addmirror', 'anasheed.editmirror', 'anasheed.delmirror'];

    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
            'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
            'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'mirror-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'mirror-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /** @param array<string, mixed> $attributes */
    public static function khotabItem(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->insertGetId(array_merge([
            'author' => 7,
            'title' => 'درس',
            'vedio' => 1,
            'mirror' => 0,
            'lastmirror' => null,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function khotabMirror(int $itemId, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_mirror')->insertGetId(array_merge([
            'khid' => $itemId,
            'link' => 'https://example.invalid/'.uniqid('', true).'.mp3',
            'comment' => 'جودة عالية',
            'linksize' => 1048576,
            'vedio' => 0,
            'backupme' => 0,
            'time' => 1700000000,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function anasheedItem(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_anasheed_anasheed')->insertGetId(array_merge([
            'title' => 'نشيد',
            'vedio' => 0,
            'mirror' => 0,
            'lastmirror' => 0,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function anasheedMirror(int $itemId, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_anasheed_mirror')->insertGetId(array_merge([
            'khid' => $itemId,
            'link' => 'https://example.invalid/'.uniqid('', true).'.mp3',
            'title' => 'جودة عالية',
            'linksize' => 1048576,
            'vedio' => 0,
            'backupme' => 0,
            'time' => 1700000000,
        ], $attributes));
    }

    public static function khotabCounter(int $itemId): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $itemId)->value('mirror');
    }

    public static function anasheedCounter(int $itemId): int
    {
        return (int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $itemId)->value('mirror');
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }
}
