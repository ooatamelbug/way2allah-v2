<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * F01 / B6a test harness. `nuke_ads` is created with the PRODUCTION shape (local 2025 copy,
 * `SHOW CREATE TABLE nuke_ads`): nullable columns, `percentage` DEFAULT 50, `ads_show_type` NOT NULL
 * DEFAULT 'period', `required_num_view` NOT NULL DEFAULT 0, nullable `show`, `num_view` / `num_click`
 * DEFAULT 0, `path_type` DEFAULT 0 — so defaults written (or not written) by the module are exercised
 * against the real table semantics. (`MainSchema::nukeAds()` is a looser shape used by public-page tests.)
 */
final class AdAdminHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_ads' => self::nukeAdsProductionShape(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    public static function nukeAdsProductionShape(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('image_path')->nullable();
            $table->integer('position')->nullable();
            $table->integer('percentage')->nullable()->default(50);
            $table->tinyInteger('type')->nullable();
            $table->string('ads_show_type', 100)->default('period');
            $table->integer('required_num_view')->default(0);
            $table->tinyInteger('show')->nullable();
            $table->text('link')->nullable();
            $table->date('startdate')->nullable();
            $table->date('enddate')->nullable();
            $table->integer('num_view')->nullable()->default(0);
            $table->integer('num_click')->nullable()->default(0);
            $table->tinyInteger('path_type')->nullable()->default(0);
        };
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = ['ads.manageads'], string $aid = 'ads-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'ads-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /** The production row as it exists in the 2025 copy (id 1; malformed `ttps://` link kept on purpose). */
    public static function legacyRow(array $overrides = []): int
    {
        return (int) DB::connection('main')->table('nuke_ads')->insertGetId(array_merge([
            'name' => 'إعلان قديم',
            'image_path' => 'https://way2allah.com/media/bann/1742505317.jpg',
            'position' => 4, 'percentage' => 0, 'type' => 1, 'ads_show_type' => 'numview', 'required_num_view' => 10000000,
            'show' => 0, 'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN', 'startdate' => null, 'enddate' => null,
            'num_view' => 76, 'num_click' => 0, 'path_type' => 1,
        ], $overrides));
    }

    public static function codeRow(array $overrides = []): int
    {
        return self::legacyRow(array_merge([
            'name' => 'شفرة', 'type' => 0, 'image_path' => '<script async src="https://pagead2.googlesyndication.com/x.js"></script><ins class="adsbygoogle"></ins>',
            'path_type' => 2, 'link' => '', 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-12-31',
            'required_num_view' => 0, 'show' => 1, 'num_view' => 0,
        ], $overrides));
    }

    public static function row(int $id): ?object
    {
        return DB::connection('main')->table('nuke_ads')->where('id', $id)->first();
    }

    /** @return list<array<string, mixed>> */
    public static function snapshot(): array
    {
        return DB::connection('main')->table('nuke_ads')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }

    /** A valid image-ad submission (URL image, period). */
    public static function imageInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'إعلان جديد', 'type' => '1', 'path_type' => '2', 'image_url' => 'https://example.org/banner.jpg',
            'link' => 'https://example.org/', 'position' => '4', 'show' => '1', 'ads_show_type' => 'period',
            'startdate' => '2026-11-01', 'enddate' => '2026-11-30',
        ], $overrides);
    }
}
