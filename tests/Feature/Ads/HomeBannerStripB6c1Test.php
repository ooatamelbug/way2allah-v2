<?php

/*
 * F01 / B6c-1 — OLD's home banner strip, prepared but not placed. A selection row is drawn only if its item exists,
 * is publicly visible and still has a valid banner (B6b owner decisions 3–4); selection rows are never modified.
 */

use App\Domain\Content\Ads\HomeBannerStripService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdsRenderingHarness as H;

beforeEach(function () {
    $this->media = H::setUp();
    H::on();
    $this->member = function (int $mediaId): void {
        DB::connection('main')->table('nuke_banners_ads')->insert(['media_id' => $mediaId, 'creation_date' => '2019-12-01 06:43:29']);
    };
});

afterEach(fn () => H::removeMedia($this->media));

function b6c1Forum(array $attributes = []): void
{
    DB::connection('main')->table('nuke_banners')->insert(array_merge([
        'cat' => 'montada',
        'title1' => 'المنتدى 1', 'url1' => 'https://forums.example.org/1', 'image1' => 'http://archive.example.org/f1.png',
        'title2' => 'المنتدى 2', 'url2' => 'https://forums.example.org/2', 'image2' => 'https://archive.example.org/f2.png',
    ], $attributes));
}

it('renders OLD\'s strip: forum banner 1, the selected item\'s banner linking to the item, forum banner 2', function () {
    $item = H::item('خطبة البنر');
    H::bannerFile($this->media, $item);
    ($this->member)($item);
    b6c1Forum();
    DB::connection('main')->table('nuke_banners')->insert(['cat' => 'cds']);   // other categories are ignored

    $html = H::strip();

    $first = strpos($html, 'forums.example.org/1');
    $middle = strpos($html, 'w2a_large_banner');
    $last = strpos($html, 'forums.example.org/2');
    expect($html)->toStartWith('<div class="w2a_home_banners">')
        ->toContain('<a href="'.route('khotab.item.show', $item).'" class="w2a_large_banner"><img src="/media/banner/0/'.$item.'.gif" border="0" alt="خطبة البنر" width="1" height="1"></a>')
        ->toContain('src="https://archive.example.org/f1.png"')   // OLD's http → https
        ->toContain('alt="المنتدى 1"')
        ->not->toContain('<script')->not->toContain('onclick')
        ->and($first)->toBeLessThan($middle)->and($middle)->toBeLessThan($last);
});

it('never draws a selection whose item is missing, hidden, without banner, or whose banner file is absent', function () {
    $hidden = H::item('مخفية', 1, 1);
    H::bannerFile($this->media, $hidden);
    $noBanner = H::item('بلا بنر', 0);
    H::bannerFile($this->media, $noBanner);
    $requested = H::item('بنر مطلوب', -1);
    H::bannerFile($this->media, $requested);
    $noFile = H::item('بلا ملف', 1);
    foreach ([$hidden, $noBanner, $requested, $noFile, 424242] as $id) {
        ($this->member)($id);
    }
    $before = H::rows('nuke_banners_ads');
    $strips = app(HomeBannerStripService::class);

    for ($i = 0; $i < 30; $i++) {
        expect($strips->pickItem())->toBeNull();
    }
    expect(H::strip())->toBe('')                                   // nothing eligible and no forum row: nothing at all
        ->and(H::rows('nuke_banners_ads'))->toBe($before);        // historical selections preserved

    b6c1Forum();
    expect(H::strip())->toContain('forums.example.org/1')->not->toContain('w2a_large_banner')->not->toContain('/media/banner/');
});

it('draws uniformly among eligible selections only', function () {
    $ok = [];
    foreach (['أ', 'ب', 'ج'] as $title) {
        $id = H::item($title);
        H::bannerFile($this->media, $id);
        ($this->member)($id);
        $ok[] = $id;
    }
    $hidden = H::item('مخفية', 1, 1);
    H::bannerFile($this->media, $hidden);
    ($this->member)($hidden);
    $strips = app(HomeBannerStripService::class);

    $counts = array_fill_keys($ok, 0);
    for ($i = 0; $i < 300; $i++) {
        $counts[$strips->pickItem()['id']]++;
    }

    expect(array_sum($counts))->toBe(300);
    foreach ($counts as $n) {
        expect($n)->toBeGreaterThan(65)->toBeLessThan(135);
    }
});

it('omits a forum banner whose image or link is unsafe, and escapes titles', function () {
    b6c1Forum([
        'url1' => 'javascript:alert(1)',
        'title2' => '"><script>alert(2)</script>', 'image2' => 'https://archive.example.org/f2.png?a=1&b=2',
    ]);

    $html = H::strip();

    expect($html)->not->toContain('forums.example.org/1')->not->toContain('javascript:')
        ->not->toContain('<script>alert(2)</script>')->toContain(e('"><script>alert(2)</script>'))
        ->toContain('src="https://archive.example.org/f2.png?a=1&amp;b=2"');
});

it('escapes the item title', function () {
    $item = H::item('<img src=x onerror=alert(1)>');
    H::bannerFile($this->media, $item);
    ($this->member)($item);

    expect(H::strip())->not->toContain('<img src=x onerror')->toContain('alt="'.e('<img src=x onerror=alert(1)>').'"');
});

it('reads only: no write to the selection, the items or the forum banners', function () {
    $item = H::item('بنر');
    H::bannerFile($this->media, $item);
    ($this->member)($item);
    ($this->member)(424242);
    b6c1Forum();
    $before = [H::rows('nuke_banners_ads'), H::rows('nuke_islamic_khotab'), H::rows('nuke_banners')];
    H::$queries = [];

    for ($i = 0; $i < 10; $i++) {
        H::strip();
    }

    expect(H::writes())->toBe([])
        ->and([H::rows('nuke_banners_ads'), H::rows('nuke_islamic_khotab'), H::rows('nuke_banners')])->toBe($before);
});
