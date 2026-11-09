<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/** OLD Admin Migration Phase 5 — shared setup for the Advfiles tests. */
final class AdvfileAdminHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
            'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_advfile' => MainSchema::nukeIslamicAdvfile(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'adv-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'adv-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /**
     * An item with its own author, group and series (counters at $counter).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{item: int, author: int, group: int, series: int}
     */
    public static function item(int $state = 0, array $attributes = [], int $counter = 5): array
    {
        $main = DB::connection('main');
        $author = (int) $main->table('nuke_islamic_authors')->insertGetId(['name' => 'شيخ', 'advfile' => $counter]);
        $group = (int) $main->table('nuke_islamic_groups')->insertGetId(['title' => 'مجموعة', 'author_id' => $author, 'advfile' => $counter]);
        $series = (int) $main->table('nuke_islamic_series')->insertGetId(['title' => 'سلسلة', 'author_id' => $author, 'group_id' => $group, 'advfile' => $counter]);
        $item = (int) $main->table('nuke_islamic_khotab')->insertGetId(array_merge([
            'title' => 'درس', 'author' => $author, 'group_id' => $group, 'ser_id' => $series, 'vedio' => 1, 'advfile' => $state,
        ], $attributes));

        return ['item' => $item, 'author' => $author, 'group' => $group, 'series' => $series];
    }

    /** @param array<string, mixed> $attributes */
    public static function file(int $itemId, array $attributes = []): void
    {
        DB::connection('main')->table('nuke_islamic_advfile')->insert(array_merge([
            'id' => $itemId, 'code' => '<p>ملف</p>', 'credit' => 0, 'audio_url' => '', 'small_thumb' => 0, 'large_thumb' => 0,
        ], $attributes));
    }

    public static function state(int $itemId): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $itemId)->value('advfile');
    }

    public static function payload(int $itemId): ?object
    {
        return DB::connection('main')->table('nuke_islamic_advfile')->where('id', $itemId)->first();
    }

    /** @param array{author: int, group: int, series: int} $ids */
    public static function counters(array $ids): array
    {
        $main = DB::connection('main');

        return [
            (int) $main->table('nuke_islamic_authors')->where('id', $ids['author'])->value('advfile'),
            (int) $main->table('nuke_islamic_groups')->where('id', $ids['group'])->value('advfile'),
            (int) $main->table('nuke_islamic_series')->where('id', $ids['series'])->value('advfile'),
        ];
    }

    /** Every row of every table the workflow could touch. */
    public static function snapshot(): array
    {
        $main = DB::connection('main');
        $all = fn (string $t) => $main->table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return [
            'khotab' => $all('nuke_islamic_khotab'),
            'advfile' => $all('nuke_islamic_advfile'),
            'authors' => $all('nuke_islamic_authors'),
            'groups' => $all('nuke_islamic_groups'),
            'series' => $all('nuke_islamic_series'),
        ];
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
