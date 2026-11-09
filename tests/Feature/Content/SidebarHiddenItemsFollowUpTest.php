<?php

/*
 * Hidden-content sidebar security fix, follow-up (owner, 2026-11-05; decision-log #95) — the remaining public
 * Khotab sidebar lists no longer offer visitors hidden items' titles and links: the five `topitems()` lists (by
 * author ×2, by video flag ×2, radio), the Satellite channel pair and the live-stream pair.
 */

use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

const SHF_SECRET = 'عنوان مخفي سري';

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create('series_category_index', MainSchema::seriesCategoryIndex());
    Cache::flush();
    $this->widget = new ContentSidebarWidget;
});

function shfItem(array $attributes): int
{
    return H::insert('nuke_islamic_khotab', $attributes + ['author' => 1, 'vedio' => 1, 'hidden' => 0, 'ser_id' => 0, 'group_id' => 0, 'gif' => 0, 'frame' => 0, 'channel_id' => 3]);
}

/**
 * Seven visible items (hits / time / id rising together) and, ahead of all of them, one hidden item; plus
 * decoys that a correct filter must still exclude (another author, another channel, the other video flag).
 *
 * @return array{visible: array<int, int>, hidden: int}
 */
function shfSeed(): array
{
    $visible = [];
    for ($i = 1; $i <= 7; $i++) {
        $visible[$i] = shfItem(['title' => "ظاهر {$i}", 'hits' => $i * 10, 'time' => $i * 100]);
    }
    $hidden = shfItem(['title' => SHF_SECRET, 'hits' => 9999, 'time' => 99999, 'hidden' => 1]);
    shfItem(['title' => 'شيخ آخر', 'author' => 2, 'hits' => 5000, 'time' => 50000, 'channel_id' => 4]);
    shfItem(['title' => 'صوتي', 'vedio' => 0, 'hits' => 6000, 'time' => 60000, 'channel_id' => 4]);

    return ['visible' => $visible, 'hidden' => $hidden];
}

// ─── the methods ────────────────────────────────────────────────────────────

it('every affected method excludes the hidden item and keeps its visible order, limit and filters', function (string $method, array $args, Closure $expected) {
    ['visible' => $v, 'hidden' => $hidden] = shfSeed();

    $ids = $this->widget->{$method}(...$args)->pluck('id')->all();

    expect($ids)->not->toContain($hidden)->toBe($expected($v));
})->with([
    'by author, hits' => ['khotabMostDownloadedByAuthor', [1, true], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3]]],
    'by author, time' => ['khotabMostRecentByAuthor', [1, true], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3]]],
    // id 9 is the other author's video item (any author counts here); id 10, the audio item, never does
    'by video flag, hits' => ['khotabMostDownloadedByVideoFlag', [true], fn ($v) => [9, $v[7], $v[6], $v[5], $v[4]]],
    'by video flag, time' => ['khotabMostRecentByVideoFlag', [true], fn ($v) => [9, $v[7], $v[6], $v[5], $v[4]]],
    'radio, time, 10' => ['radioMostRecentByVideoFlag', [true], fn ($v) => [9, $v[7], $v[6], $v[5], $v[4], $v[3], $v[2], $v[1]]],
    'channel, hits' => ['channelMostDownloadedKhotabItems', [3], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3]]],
    'channel, time' => ['channelMostRecentKhotabItems', [3], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3]]],
    'live stream, hits' => ['liveStreamMostViewed', [3], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3], $v[2], $v[1]]],
    'live stream, newest id' => ['liveStreamMostRecent', [3], fn ($v) => [$v[7], $v[6], $v[5], $v[4], $v[3], $v[2], $v[1]]],
    // no channel: no channel or video filter, as before — only the hidden item (id 8) is dropped
    'live stream, no channel' => ['liveStreamMostViewed', [0], fn ($v) => [10, 9, $v[7], $v[6], $v[5], $v[4], $v[3], $v[2], $v[1]]],
]);

it('cached hidden rows cannot reappear: entries written under the pre-fix keys are ignored', function () {
    ['visible' => $v, 'hidden' => $hidden] = shfSeed();
    $stale = [['id' => $hidden, 'title' => SHF_SECRET, 'author' => 1, 'frame' => 0, 'hits' => 9999, 'downcount' => 0, 'time' => 99999]];
    // the keys the old code wrote (cacheKey() ksorts its parts)
    foreach ([
        'sidebar:topitems:author=1:limit=5:order=hits:vedio=1',
        'sidebar:topitems:author=1:limit=5:order=time:vedio=1',
        'sidebar:topitems:limit=5:order=hits:vedio=1',
        'sidebar:topitems:limit=5:order=time:vedio=1',
        'sidebar:topitems:limit=10:order=time:vedio=1',
        'sidebar:channel-khotab:channel=3:limit=5:order=hits',
        'sidebar:channel-khotab:channel=3:limit=5:order=time',
    ] as $key) {
        Cache::put($key, $stale, 300);
    }

    foreach ([
        $this->widget->khotabMostDownloadedByAuthor(1, true),
        $this->widget->khotabMostRecentByAuthor(1, true),
        $this->widget->khotabMostDownloadedByVideoFlag(true),
        $this->widget->khotabMostRecentByVideoFlag(true),
        $this->widget->radioMostRecentByVideoFlag(true),
        $this->widget->channelMostDownloadedKhotabItems(3),
        $this->widget->channelMostRecentKhotabItems(3),
    ] as $rows) {
        expect($rows->pluck('id')->all())->not->toContain($hidden)->toContain($v[7]);
    }

    expect(Cache::has('sidebar:topitems:author=1:hidden=0:limit=5:order=hits:vedio=1'))->toBeTrue()
        ->and(Cache::has('sidebar:channel-khotab:channel=3:hidden=0:limit=5:order=time'))->toBeTrue();
});

it('the methods fixed earlier (#94) stay safe', function () {
    ['hidden' => $hidden] = shfSeed();
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $hidden)->update(['frame' => 1]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => $hidden, 'category_id' => 11]);

    expect($this->widget->khotabMostDownloadedByCategoryForSeries(11)->pluck('id')->all())->not->toContain($hidden)
        ->and($this->widget->khotabMostRecentByCategoryForSeries(11)->pluck('id')->all())->not->toContain($hidden);
    for ($i = 0; $i < 20; $i++) {
        expect($this->widget->khotabRandomFeatured()->pluck('id')->all())->not->toContain($hidden);
    }
});

// ─── public pages ───────────────────────────────────────────────────────────

it('pages: a visitor\'s page contains the hidden item\'s title and link nowhere; the page still renders its visible items', function (Closure $url) {
    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ', 'prename' => '']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 3, 'title' => 'قناة', 'active' => 0]);
    $group = H::insert('nuke_islamic_groups', ['author_id' => 1, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'count' => 1, 'channel_id' => 3]);
    $series = H::insert('nuke_islamic_series', ['author_id' => 1, 'group_id' => 0, 'title' => 'سلسلة ظاهرة', 'hidden' => 0, 'vedio' => 1, 'count' => 1, 'channel_id' => 3]);
    $today = (int) now()->startOfDay()->addHours(10)->timestamp;
    $item = shfItem(['title' => 'درس ظاهر', 'ser_id' => $series, 'hits' => 5, 'time' => $today, 'frame' => 1]);
    $hidden = shfItem(['title' => SHF_SECRET, 'hits' => 9999, 'time' => $today + 60, 'hidden' => 1, 'frame' => 1, 'gif' => 1]);

    H::visitor();
    $html = $this->get($url(['group' => $group, 'series' => $series, 'item' => $item]))->assertOk()->getContent();

    expect($html)->toContain('درس ظاهر')
        ->not->toContain(SHF_SECRET)
        ->not->toContain("/khotab-item-{$hidden}.htm");
})->with([
    'khotab author' => [fn (array $ids) => '/khotab-video-1.htm'],
    'khotab group' => [fn (array $ids) => "/khotab-group-{$ids['group']}.htm"],
    'khotab series' => [fn (array $ids) => "/khotab-series-{$ids['series']}.htm"],
    'khotab item' => [fn (array $ids) => "/khotab-item-{$ids['item']}.htm"],
    'khotab news' => [fn (array $ids) => '/khotab-video_news.htm'],
    'khotab day' => [fn (array $ids) => '/khotab-video-today.htm'],
    'radio' => [fn (array $ids) => '/radio.htm'],
    'satellite channel' => [fn (array $ids) => '/channel-3.htm'],
    'live channel' => [fn (array $ids) => '/live-channel-3.htm'],
]);
