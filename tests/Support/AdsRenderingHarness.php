<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\Fixtures\MainSchema;

/**
 * F01 / B6c-1 test harness: production-shaped `nuke_ads` (B6a), `nuke_banners_ads` (B6b) and `nuke_banners`
 * (local 2025 copy, `SHOW CREATE TABLE`), a temporary media root for banner GIFs, a seeded randomizer, and a
 * recorder for every query on `main`.
 */
final class AdsRenderingHarness
{
    public const TODAY = '2026-11-14';

    /** 1×1 GIF. */
    public const GIF = 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

    /** @var list<string> */
    public static array $queries = [];

    public static function setUp(): string
    {
        InMemoryConnection::setup('main', [
            'nuke_ads' => AdAdminHarness::nukeAdsProductionShape(),
            'nuke_banners_ads' => BannerAdsHarness::nukeBannersAds(),
            'nuke_banners' => self::nukeBanners(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        ]);

        // Rendering uses now(); freeze it at TODAY so date-based eligibility never depends on the wall clock.
        Carbon::setTestNow(self::TODAY.' 12:00:00');

        $media = sys_get_temp_dir().'/w2a-b6c1-'.uniqid();
        mkdir($media, 0775, true);
        config(['admin_khotab.media_root' => $media, 'media.base_url' => '/media', 'ads.rendering_enabled' => false, 'ads.popup_enabled' => false]);
        self::seed(20261114);

        self::$queries = [];
        DB::connection('main')->listen(function ($query) {
            self::$queries[] = $query->sql;
        });

        return $media;
    }

    public static function seed(int $seed): void
    {
        app()->instance(Randomizer::class, new Randomizer(new Mt19937($seed)));
    }

    public static function on(bool $popup = false): void
    {
        config(['ads.rendering_enabled' => true, 'ads.popup_enabled' => $popup]);
    }

    public static function nukeBanners(): \Closure
    {
        return function (Blueprint $table) {
            $table->increments('id');
            $table->string('cat', 250)->nullable();
            foreach ([1, 2, 3] as $n) {
                $table->string('title'.$n, 250)->nullable();
                $table->text('url'.$n)->nullable();
                $table->string('image'.$n, 250)->nullable();
            }
        };
    }

    /** An eligible image ad (numview, far below its limit) unless overridden. */
    public static function ad(int $position, array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_ads')->insertGetId(array_merge([
            'name' => 'B6C1-AD-'.$position, 'type' => 1, 'position' => $position, 'show' => 1, 'percentage' => 100,
            'image_path' => 'https://example.org/b6c1-'.$position.'.jpg', 'link' => 'https://example.org/go-'.$position,
            'ads_show_type' => 'numview', 'required_num_view' => 1000, 'num_view' => 0, 'num_click' => 0, 'path_type' => 2,
        ], $attributes));
    }

    public static function item(string $title, int $banner = 1, int $hidden = 0): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_khotab')->insertGetId([
            'title' => $title, 'banner' => $banner, 'hidden' => $hidden, 'author' => 0, 'vedio' => 1,
        ]);
    }

    public static function bannerFile(string $media, int $itemId): void
    {
        $dir = $media.'/banner/'.intdiv($itemId, 1000);
        is_dir($dir) || mkdir($dir, 0775, true);
        file_put_contents($dir.'/'.$itemId.'.gif', base64_decode(self::GIF));
    }

    public static function slot(int $position): string
    {
        return trim(Blade::render('<x-ads.slot :position="$position" />', ['position' => $position]));
    }

    public static function strip(): string
    {
        return trim(Blade::render('<x-ads.banner-strip />'));
    }

    /** @return list<string> */
    public static function writes(): array
    {
        return array_values(array_filter(self::$queries, fn ($sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1));
    }

    /** @return list<array<string, mixed>> */
    public static function rows(string $table): array
    {
        return DB::connection('main')->table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public static function removeMedia(string $media): void
    {
        if (! is_dir($media)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($media);
    }
}
