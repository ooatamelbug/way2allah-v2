<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/** OLD Admin Migration Phase 7 — shared setup for the Mobile Downloads tests. */
final class MobileHarness
{
    public const ANONYMOUS = 'زائر';

    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_config' => MainSchema::nukeConfig(),
            'nuke_downloads_categories' => MainSchema::nukeDownloadsCategories(),
            'nuke_downloads_downloads' => MainSchema::nukeDownloadsDownloads(),
            'nuke_downloads_newdownload' => MainSchema::nukeDownloadsNewdownload(),
            'nuke_downloads_modrequest' => MainSchema::nukeDownloadsModrequest(),
            'nuke_downloads_votedata' => MainSchema::nukeDownloadsVotedata(),
            'nuke_downloads_editorials' => MainSchema::nukeDownloadsEditorials(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);
        DB::connection('main')->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0, 'anonymous' => self::ANONYMOUS]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** A vBulletin member — OLD's `is_user()`. Never saved (VbUser is read-only). */
    public static function member(string $username = 'عضو', int $id = 501): VbUser
    {
        $user = new VbUser;
        $user->forceFill(['userid' => $id, 'username' => $username]);
        $user->exists = true;

        return $user;
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'mobile-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'mobile-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    public static function category(string $title = 'القرآن', int $parent = 0): int
    {
        return (int) DB::connection('main')->table('nuke_downloads_categories')->insertGetId(['title' => $title, 'cdescription' => '', 'parentid' => $parent], 'cid');
    }

    /** @param array<string, mixed> $attributes */
    public static function material(int $cid, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_downloads_downloads')->insertGetId(array_merge([
            'cid' => $cid, 'sid' => 0, 'title' => 'مادة', 'url' => 'https://archive.org/download/x/'.uniqid().'.mp3',
            'description' => 'وصف', 'date' => '2010-12-15 06:40:47', 'name' => '', 'email' => '', 'hits' => 0, 'submitter' => '',
            'downloadratingsummary' => 0, 'totalvotes' => 0, 'totalcomments' => 0, 'filesize' => 0, 'version' => '', 'homepage' => '',
        ], $attributes), 'lid');
    }

    /** @param array<string, mixed> $attributes */
    public static function vote(int $lid, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_downloads_votedata')->insertGetId(array_merge([
            'ratinglid' => $lid, 'ratinguser' => self::ANONYMOUS, 'rating' => 8, 'ratinghostname' => '10.0.0.1',
            'ratingcomments' => '', 'ratingtimestamp' => '2010-01-01 10:00:00',
        ], $attributes), 'ratingdbid');
    }

    public static function row(string $table, string $key, int $id): ?object
    {
        return DB::connection('main')->table($table)->where($key, $id)->first();
    }

    public static function snapshot(): array
    {
        $main = DB::connection('main');
        $all = fn (string $t, string $k) => $main->table($t)->orderBy($k)->get()->map(fn ($r) => (array) $r)->all();

        return [
            'categories' => $all('nuke_downloads_categories', 'cid'),
            'materials' => $all('nuke_downloads_downloads', 'lid'),
            'submissions' => $all('nuke_downloads_newdownload', 'lid'),
            'requests' => $all('nuke_downloads_modrequest', 'requestid'),
            'votes' => $all('nuke_downloads_votedata', 'ratingdbid'),
            'editorials' => $all('nuke_downloads_editorials', 'downloadid'),
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
