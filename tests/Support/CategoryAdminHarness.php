<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * OLD Admin Migration Phase 3 — shared setup for the category-assignment
 * tests. Same shape as MirrorAdminHarness; every table from MainSchema.
 */
final class CategoryAdminHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
            'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
            'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'cat-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'cat-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /** Categories with explicit ids, so tests read like the owner's examples. */
    public static function categories(int ...$ids): void
    {
        foreach ($ids as $id) {
            DB::connection('main')->table('nuke_w2a_cat')->insert([
                'id' => $id, 'title' => "تصنيف {$id}", 'main_cat' => 0, 'video_count' => 10, 'anasheed_count' => 10,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    public static function khotabItem(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->insertGetId(array_merge([
            'author' => 7, 'title' => 'درس', 'vedio' => 1, 'cat' => null,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function anasheedItem(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_anasheed_anasheed')->insertGetId(array_merge([
            'title' => 'نشيد', 'vedio' => 0, 'cat_id' => null,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function series(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_series')->insertGetId(array_merge([
            'title' => 'سلسلة', 'cat' => null,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public static function group(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_groups')->insertGetId(array_merge([
            'title' => 'مجموعة', 'cat' => null,
        ], $attributes));
    }

    public static function cat(string $table, int $id): ?string
    {
        $column = $table === 'nuke_anasheed_anasheed' ? 'cat_id' : 'cat';

        return DB::connection('main')->table($table)->where('id', $id)->value($column);
    }

    public static function videoCount(int $categoryId): int
    {
        return (int) DB::connection('main')->table('nuke_w2a_cat')->where('id', $categoryId)->value('video_count');
    }

    public static function anasheedCount(int $categoryId): int
    {
        return (int) DB::connection('main')->table('nuke_w2a_cat')->where('id', $categoryId)->value('anasheed_count');
    }

    /** Every category-list value and counter in the fixture — the "nothing else moved" baseline. */
    public static function snapshot(): array
    {
        $main = DB::connection('main');

        return [
            'khotab' => $main->table('nuke_islamic_khotab')->orderBy('id')->pluck('cat', 'id')->all(),
            'anasheed' => $main->table('nuke_anasheed_anasheed')->orderBy('id')->pluck('cat_id', 'id')->all(),
            'series' => $main->table('nuke_islamic_series')->orderBy('id')->pluck('cat', 'id')->all(),
            'groups' => $main->table('nuke_islamic_groups')->orderBy('id')->pluck('cat', 'id')->all(),
            'counters' => $main->table('nuke_w2a_cat')->orderBy('id')->get(['id', 'video_count', 'anasheed_count'])
                ->mapWithKeys(fn ($r) => [$r->id => [(int) $r->video_count, (int) $r->anasheed_count]])->all(),
        ];
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }

    /**
     * Records every write statement issued on `main` while $callback runs.
     *
     * @return list<array{sql: string, bindings: array<int, mixed>}>
     */
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
