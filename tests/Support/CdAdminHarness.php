<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/** OLD Admin Migration Phase 6 — shared setup for the CD / Album Downloads tests. */
final class CdAdminHarness
{
    public static function setUp(): string
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_w2acd_w2acd' => MainSchema::nukeW2acdW2acd(),
            'nuke_w2acd_groups' => MainSchema::nukeW2acdGroups(),
            'nuke_w2acd_mirror' => MainSchema::nukeW2acdMirror(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);

        // Isolated image directory — never the real public images tree.
        $root = sys_get_temp_dir().'/cds-test-'.bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        config(['admin_cds.image_root' => $root]);

        return $root;
    }

    public static function tearDown(string $root): void
    {
        foreach (glob($root.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($root);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'cd-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'cd-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /** A release as OLD stored it. @param array<string, mixed> $attributes */
    public static function release(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_w2acd_w2acd')->insertGetId(array_merge([
            'title' => 'إسطوانة', 'mytime' => 1600000000, 'group_id' => 0, 'order_in_group' => 1,
            'link' => 'https://archive.org/a.iso,https://archive.org/a.torrent', 'cd' => 'رابط ISO,رابط تورنت',
            'thumbnail' => '01600000000.png,11600000000.png,', 'lastvisit' => 0,
        ], $attributes));
    }

    public static function group(int $counter = 0): int
    {
        return (int) DB::connection('main')->table('nuke_w2acd_groups')->insertGetId(['title' => 'قسم', 'w2acd' => $counter]);
    }

    public static function row(int $id): ?object
    {
        return DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $id)->first();
    }

    public static function counter(int $groupId): int
    {
        return (int) DB::connection('main')->table('nuke_w2acd_groups')->where('id', $groupId)->value('w2acd');
    }

    public static function snapshot(): array
    {
        $main = DB::connection('main');
        $all = fn (string $t) => $main->table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return ['releases' => $all('nuke_w2acd_w2acd'), 'groups' => $all('nuke_w2acd_groups'), 'mirror' => $all('nuke_w2acd_mirror')];
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }

    /** @return list<array{sql: string, bindings: array<int, mixed>}> */
    public static function writesDuring(callable $callback): array
    {
        $writes = [];
        DB::connection('main')->listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });
        $callback();

        return $writes;
    }
}
