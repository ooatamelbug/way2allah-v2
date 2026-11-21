<?php

/*
 * F01 / B6c-1 — the global switch `ads.rendering_enabled` (and `ads.popup_enabled`) default OFF. While off, no
 * advertising markup, no advertising query and no write, for every position and the banner strip, even with
 * enabled ads and an eligible banner selection stored. B6c-1 places no slot on any public page.
 */

use App\Domain\Content\Ads\AdsSwitch;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdAdminHarness;
use Tests\Support\AdsRenderingHarness as H;
use Tests\Support\BannerAdsHarness;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    $this->media = H::setUp();
    for ($p = 1; $p <= 12; $p++) {
        H::ad($p);
    }
    H::ad(4, ['type' => 0, 'image_path' => '<script>alert("code")</script>']);
    $item = H::item('بنر');
    H::bannerFile($this->media, $item);
    DB::connection('main')->table('nuke_banners_ads')->insert(['media_id' => $item, 'creation_date' => '2019-12-01 06:43:29']);
    DB::connection('main')->table('nuke_banners')->insert(['cat' => 'montada', 'title1' => 'f1', 'url1' => 'https://forums.example.org/1', 'image1' => 'https://example.org/f1.png']);
    H::$queries = [];
});

afterEach(fn () => H::removeMedia($this->media));

it('ships both switches OFF by default, from config/ads.php', function () {
    $config = require config_path('ads.php');

    expect($config)->toBe(['rendering_enabled' => false, 'popup_enabled' => false]);
});

it('only a strict boolean true turns advertising on', function (mixed $value, bool $on) {
    config(['ads.rendering_enabled' => $value, 'ads.popup_enabled' => $value]);

    expect(AdsSwitch::renderingEnabled())->toBe($on)->and(AdsSwitch::popupEnabled())->toBe($on);
})->with([
    'missing' => [null, false], 'false' => [false, false], 'string "true"' => ['true', false], 'one' => [1, false],
    'string "1"' => ['1', false], 'true' => [true, true],
]);

it('the popup switch alone enables nothing', function () {
    config(['ads.rendering_enabled' => false, 'ads.popup_enabled' => true]);

    expect(AdsSwitch::popupEnabled())->toBeFalse()->and(H::slot(12))->toBe('')->and(H::$queries)->toBe([]);
});

it('while OFF, every position and the strip render nothing, with zero queries and zero writes', function () {
    $before = [H::rows('nuke_ads'), H::rows('nuke_banners_ads')];
    H::$queries = [];

    foreach (range(0, 13) as $p) {
        expect(H::slot($p))->toBe('');
    }
    expect(H::strip())->toBe('')
        ->and(H::$queries)->toBe([])
        ->and([H::rows('nuke_ads'), H::rows('nuke_banners_ads')])->toBe($before);
});

it('public pages contain no advertising slot in B6c-1, whatever the switch', function (bool $on) {
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
        'nuke_banners_ads' => BannerAdsHarness::nukeBannersAds(),
        'nuke_banners' => H::nukeBanners(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);
    for ($p = 1; $p <= 12; $p++) {
        H::ad($p);
    }
    config(['ads.rendering_enabled' => $on, 'ads.popup_enabled' => $on]);
    $touched = [];
    DB::connection('main')->listen(function ($q) use (&$touched) {
        if (preg_match('/nuke_ads|nuke_banners/', $q->sql) === 1) {
            $touched[] = $q->sql;
        }
    });

    foreach (['/', '/privacy'] as $page) {
        expect($this->get($page)->assertOk()->getContent())->not->toContain('B6C1-AD-')->not->toContain('b6c1-')
            ->not->toContain('w2a-ad-popup')->not->toContain('w2a_home_banners')->not->toContain('<dialog');
    }
    expect($touched)->toBe([]);
})->with(['switch off' => false, 'switch on' => true]);

it('no public view places an advertising component yet (placement is B6c-2)', function () {
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = str_replace(resource_path('views').'/', '', $file->getPathname());
        if (str_starts_with($path, 'components/ads/') || str_starts_with($path, 'admin/')) {
            continue;
        }
        if (preg_match('/<x-ads\.|components\.ads\.|AdSlotService|HomeBannerStripService/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
