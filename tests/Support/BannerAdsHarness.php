<?php

namespace Tests\Support;

use App\Domain\Admin\Actions\Ads\UpdateBannerAdsAction;
use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * F01 / B6b test harness. `nuke_banners_ads` and `nuke_report_for_bad_ads` use the PRODUCTION shapes
 * (local 2025 copy, `SHOW CREATE TABLE`); `nuke_ads` (B6a) is present so isolation can be asserted.
 */
final class BannerAdsHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
            'khotab_category_index' => MainSchema::khotabCategoryIndex(),
            'nuke_banners_ads' => self::nukeBannersAds(),
            'nuke_report_for_bad_ads' => self::nukeReportForBadAds(),
            'nuke_ads' => AdAdminHarness::nukeAdsProductionShape(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    public static function nukeBannersAds(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->integer('media_id')->nullable()->default(0);
            $table->timestamp('creation_date')->nullable()->useCurrent();
        };
    }

    public static function nukeReportForBadAds(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_email', 150)->nullable();
            $table->string('ads_url', 255)->nullable();
            $table->timestamp('db_creation_date')->nullable()->useCurrent();
        };
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions, string $aid = 'b6b-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');
        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'b6b-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    public static function item(string $title, int $banner = 1, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->insertGetId(array_merge([
            'title' => $title, 'banner' => $banner, 'author' => 0, 'hidden' => 0, 'vedio' => 1,
        ], $attributes));
    }

    /** A legacy set row, as OLD's one-shot replacement left them (2019-12-01 06:43:29). */
    public static function member(int $mediaId, int $id = 0): int
    {
        $row = ['media_id' => $mediaId, 'creation_date' => '2019-12-01 06:43:29'];
        if ($id > 0) {
            $row['id'] = $id;
        }

        return (int) DB::connection('main')->table('nuke_banners_ads')->insertGetId($row);
    }

    public static function report(string $email, string $url, string $date = '2020-01-01 10:00:00'): int
    {
        return (int) DB::connection('main')->table('nuke_report_for_bad_ads')->insertGetId([
            'user_email' => $email, 'ads_url' => $url, 'db_creation_date' => $date,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function set(): array
    {
        return DB::connection('main')->table('nuke_banners_ads')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** The `set_version` the banner form would carry for the current set. */
    public static function version(): string
    {
        return UpdateBannerAdsAction::fingerprint(DB::connection('main')->table('nuke_banners_ads')->pluck('media_id'));
    }

    /** @return list<array<string, mixed>> */
    public static function reports(): array
    {
        return DB::connection('main')->table('nuke_report_for_bad_ads')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }
}
