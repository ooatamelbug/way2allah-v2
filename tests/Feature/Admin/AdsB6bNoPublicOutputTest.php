<?php

/*
 * F01 / B6b — with a populated banner set and reports, public pages neither read nor write
 * `nuke_banners_ads` / `nuke_report_for_bad_ads` and render no banner strip (B6c not implemented).
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\AdAdminHarness;
use Tests\Support\BannerAdsHarness;
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
        'nuke_banners_ads' => BannerAdsHarness::nukeBannersAds(),
        'nuke_report_for_bad_ads' => BannerAdsHarness::nukeReportForBadAds(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);

    $main = DB::connection('main');
    $id = (int) $main->table('nuke_islamic_khotab')->insertGetId(['title' => 'B6B-BANNER-ITEM', 'banner' => 1, 'hidden' => 0, 'vedio' => 1]);
    $main->table('nuke_banners_ads')->insert(['media_id' => $id, 'creation_date' => '2019-12-01 06:43:29']);
    $main->table('nuke_report_for_bad_ads')->insert(['user_email' => 'r@example.org', 'ads_url' => 'https://example.org/b6b-reported']);
});

it('public pages neither touch the B6b tables nor render a banner strip', function (string $page) {
    $snapshot = fn () => [
        DB::connection('main')->table('nuke_banners_ads')->get()->map(fn ($r) => (array) $r)->all(),
        DB::connection('main')->table('nuke_report_for_bad_ads')->get()->map(fn ($r) => (array) $r)->all(),
    ];
    $before = $snapshot();
    $touched = [];
    DB::connection('main')->listen(function ($query) use (&$touched) {
        if (preg_match('/nuke_banners_ads|nuke_report_for_bad_ads/', $query->sql) === 1) {
            $touched[] = $query->sql;
        }
    });

    $html = $this->get($page)->assertOk()->getContent();

    expect($touched)->toBe([])
        ->and($html)->not->toContain('media/banner/')->not->toContain('b6b-reported')->not->toContain('report_for_bad_ads')
        ->and($snapshot())->toBe($before);
})->with(['home' => '/', 'shared layout page' => '/privacy']);
