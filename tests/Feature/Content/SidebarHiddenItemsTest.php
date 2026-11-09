<?php

/*
 * Hidden-content sidebar security fix (owner, 2026-11-05; decision-log #94) — the three public Khotab sidebar
 * queries that offered visitors hidden items' titles and links now exclude them:
 * `khotabMostDownloadedByCategoryForSeries()`, `khotabMostRecentByCategoryForSeries()` and `khotabRandomFeatured()`.
 */

use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

const SHI_SECRET = 'عنوان مخفي سري';

beforeEach(function () {
    H::setUp();
    // the category page's series listing reads this index; the admin harness does not create it
    Schema::connection('main')->create('series_category_index', MainSchema::seriesCategoryIndex());
    Cache::flush();
    $this->widget = new ContentSidebarWidget;
});

/** One Khotab item filed in category 11 (and optionally another category). */
function shiItem(array $attributes, array $categories = [11]): int
{
    $id = H::insert('nuke_islamic_khotab', $attributes + ['author' => 1, 'vedio' => 1, 'hidden' => 0, 'ser_id' => 0, 'group_id' => 0, 'gif' => 0, 'frame' => 0]);
    foreach ($categories as $category) {
        DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => $id, 'category_id' => $category]);
    }

    return $id;
}

// ─── the two category-series lists ──────────────────────────────────────────

it('ForSeries: hidden items are excluded from both lists; visible ordering, limit 5, category and video filters unchanged', function () {
    $visible = [];
    for ($i = 1; $i <= 7; $i++) {
        $visible[$i] = shiItem(['title' => "ظاهر {$i}", 'hits' => $i * 10, 'time' => $i * 100]);
    }
    // the highest hits and newest time of all — would lead both lists if hidden were not filtered
    $hidden = shiItem(['title' => SHI_SECRET, 'hits' => 9999, 'time' => 99999, 'hidden' => 1]);
    $otherCategory = shiItem(['title' => 'تصنيف آخر', 'hits' => 5000, 'time' => 50000], [12]);
    $audio = shiItem(['title' => 'صوتي', 'hits' => 6000, 'time' => 60000, 'vedio' => 0]);

    $byHits = $this->widget->khotabMostDownloadedByCategoryForSeries(11)->pluck('id')->all();
    $byTime = $this->widget->khotabMostRecentByCategoryForSeries(11)->pluck('id')->all();

    expect($byHits)->toBe([$visible[7], $visible[6], $visible[5], $visible[4], $visible[3]])
        ->and($byTime)->toBe([$visible[7], $visible[6], $visible[5], $visible[4], $visible[3]])
        ->and($byHits)->not->toContain($hidden)->not->toContain($otherCategory)->not->toContain($audio)
        ->and($byTime)->not->toContain($hidden);
});

it('ForSeries: an entry cached before the fix (which held a hidden row) is never read again', function () {
    $visible = shiItem(['title' => 'ظاهر', 'hits' => 10, 'time' => 100]);
    $hidden = shiItem(['title' => SHI_SECRET, 'hits' => 999, 'time' => 999, 'hidden' => 1]);
    $staleRow = ['id' => $hidden, 'title' => SHI_SECRET, 'author' => 1, 'frame' => 0, 'hits' => 999, 'downcount' => 0, 'time' => 999];
    // the pre-fix keys (no `hidden` part), as the old code wrote them
    Cache::put('sidebar:category-series-khotab:category=11:limit=5:order=hits', [$staleRow], 300);
    Cache::put('sidebar:category-series-khotab:category=11:limit=5:order=time', [$staleRow], 300);

    expect($this->widget->khotabMostDownloadedByCategoryForSeries(11)->pluck('id')->all())->toBe([$visible])
        ->and($this->widget->khotabMostRecentByCategoryForSeries(11)->pluck('id')->all())->toBe([$visible])
        ->and(Cache::has('sidebar:category-series-khotab:category=11:hidden=0:limit=5:order=hits'))->toBeTrue()
        ->and(Cache::has('sidebar:category-series-khotab:category=11:hidden=0:limit=5:order=time'))->toBeTrue();
});

// ─── random featured ────────────────────────────────────────────────────────

it('RandomFeatured: never returns a hidden item, whichever id the random start lands on', function () {
    // hidden items with a frame on both sides of the only visible one
    shiItem(['title' => SHI_SECRET.' 1', 'frame' => 1, 'hidden' => 1]);
    $visible = shiItem(['title' => 'مميز ظاهر', 'frame' => 1]);
    shiItem(['title' => SHI_SECRET.' 2', 'frame' => 1, 'hidden' => 1]);
    shiItem(['title' => 'بلا صورة', 'frame' => 0, 'gif' => 0]);

    for ($i = 0; $i < 40; $i++) {
        expect($this->widget->khotabRandomFeatured()->pluck('id')->all())->toBe([$visible]);
    }
});

it('RandomFeatured: with only hidden items carrying a visual, nothing is featured', function () {
    shiItem(['title' => SHI_SECRET, 'gif' => 1, 'hidden' => 1]);
    shiItem(['title' => 'بلا صورة']);

    for ($i = 0; $i < 10; $i++) {
        expect($this->widget->khotabRandomFeatured())->toBeEmpty();
    }
});

// ─── public pages ───────────────────────────────────────────────────────────

/** The featured-item cards only (other sidebars on Khotab pages are outside this fix — decision-log #94). */
function shiFeatured(string $html): string
{
    preg_match_all('#<article class="w2a-featured-item-card">.*?</article>#s', $html, $m);

    return implode("\n", $m[0]);
}

it('pages: no affected sidebar offers a visitor a hidden item\'s title or link; visible pages still render', function (string $family, Closure $url) {
    H::category(11, 'تصنيف');
    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ', 'prename' => '']);
    $group = H::insert('nuke_islamic_groups', ['author_id' => 1, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'count' => 1, 'cat' => '|11|']);
    $series = H::insert('nuke_islamic_series', ['author_id' => 1, 'group_id' => 0, 'title' => 'سلسلة ظاهرة', 'hidden' => 0, 'vedio' => 1, 'count' => 1, 'cat' => '|11|']);
    $item = shiItem(['title' => 'درس ظاهر', 'frame' => 1, 'ser_id' => $series, 'hits' => 5, 'time' => 500]);
    $hidden = shiItem(['title' => SHI_SECRET, 'frame' => 1, 'gif' => 1, 'hidden' => 1, 'hits' => 9999, 'time' => 99999]);

    H::visitor();
    $html = $this->get($url(['group' => $group, 'series' => $series, 'item' => $item]))->assertOk()->getContent();

    expect(shiFeatured($html))->toContain('درس ظاهر')
        ->not->toContain(SHI_SECRET)
        ->not->toContain("khotab-item-{$hidden}.htm");

    // category and category-series: every sidebar on the page is covered by this fix — the whole page is clean
    if (str_starts_with($family, 'category')) {
        expect($html)->not->toContain(SHI_SECRET)->not->toContain("/khotab-item-{$hidden}.htm");
    }
})->with([
    'khotab author' => ['khotab author', fn (array $ids) => '/khotab-video-1.htm'],
    'khotab group' => ['khotab group', fn (array $ids) => "/khotab-group-{$ids['group']}.htm"],
    'khotab news' => ['khotab news', fn (array $ids) => '/khotab-video_news.htm'],
    'khotab series' => ['khotab series', fn (array $ids) => "/khotab-series-{$ids['series']}.htm"],
    'khotab item' => ['khotab item', fn (array $ids) => "/khotab-item-{$ids['item']}.htm"],
    'category' => ['category', fn (array $ids) => '/category-11.htm'],
    'category-series' => ['category-series', fn (array $ids) => "/category-series-{$ids['series']}-11.htm"],
]);
