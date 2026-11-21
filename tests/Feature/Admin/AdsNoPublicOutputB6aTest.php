<?php

/*
 * F01 / B6a — administration only. With ENABLED ads stored at every one of OLD's twelve positions (popup 12
 * included), public pages render no advertisement, never read `nuke_ads`, and never write to it (no view or
 * click counters, no auto-hide). B6b / B6c (banners_ads, report_ads, public rendering, popup, banner strip,
 * click and view tracking) are not implemented.
 *
 * Updated in B6c-1: the switch-gated rendering foundation (`app/Domain/Content/Ads/`, `components/ads/`) is the
 * only allowed public reader of `nuke_ads`; it is OFF by default and placed on no page (tests/Feature/Ads/).
 */

use App\Domain\Admin\Http\Controllers\AdAdminController;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdAdminHarness;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_options' => MainSchema::nukeOptions(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
        'nuke_ads' => AdAdminHarness::nukeAdsProductionShape(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);

    for ($position = 1; $position <= 12; $position++) {
        DB::connection('main')->table('nuke_ads')->insert([
            'name' => 'B6A-AD-'.$position, 'type' => $position % 2, 'position' => $position, 'show' => 1, 'percentage' => 100,
            'image_path' => $position % 2 ? 'https://example.org/b6a-banner-'.$position.'.jpg' : '<ins class="b6a-code-'.$position.'"></ins>',
            'link' => 'https://example.org/b6a-link-'.$position, 'ads_show_type' => 'numview', 'required_num_view' => 1000, 'path_type' => 2,
        ]);
    }
});

it('public pages render no advertisement and neither read nor write nuke_ads, with every position enabled', function (string $page) {
    $before = DB::connection('main')->table('nuke_ads')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $touched = [];
    DB::connection('main')->listen(function ($query) use (&$touched) {
        if (str_contains($query->sql, 'nuke_ads')) {
            $touched[] = $query->sql;
        }
    });

    $html = $this->get($page)->assertOk()->getContent();

    expect($touched)->toBe([])
        ->and($html)->not->toContain('B6A-AD-')->not->toContain('b6a-banner-')->not->toContain('b6a-code-')->not->toContain('b6a-link-')
        ->not->toContain('increaseclick')->not->toContain('link_this_ads')->not->toContain('ads_link_url')
        ->and(DB::connection('main')->table('nuke_ads')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($before);
})->with(['home' => '/', 'shared layout page' => '/privacy']);

it('no public route, view or service references the advertising administration or nuke_ads', function () {
    foreach (app('router')->getRoutes() as $route) {
        $uses = (string) ($route->getAction('controller') ?? '');
        if (str_contains($uses, AdAdminController::class)) {
            expect($route->uri())->toStartWith('admincp/ads');
        }
        expect($route->uri())->not->toContain('ads/click')->not->toContain('ads_click');
    }

    $offenders = [];
    $scan = function (string $dir, array $skip) use (&$offenders) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            if (! str_ends_with($path, '.php') || collect($skip)->contains(fn ($s) => str_starts_with($path, $s))) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (preg_match('/nuke_ads\b|HomeAd\b|LegacyAds\b|AdsReport\b|admin\.ads\./', $code) === 1) {
                $offenders[] = $path;
            }
        }
    };
    $scan('app', ['app/Domain/Admin/', 'app/Domain/Content/Models/HomeAd.php', 'app/Domain/Content/Ads/']);
    $scan('resources/views', ['resources/views/admin/ads/', 'resources/views/components/ads/']);

    expect($offenders)->toBe([]);
});

it('the public advertising switches exist and are OFF (B6c-1 foundation; no click route)', function () {
    expect(config('ads.rendering_enabled'))->toBeFalse()
        ->and(config('ads.popup_enabled'))->toBeFalse()
        ->and(require config_path('ads.php'))->toBe(['rendering_enabled' => false, 'popup_enabled' => false]);
});
