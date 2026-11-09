<?php

/*
 * Homepage Khotab visibility H1 (owner, 2026-11-05; decision-log #98) — the three homepage Khotab widgets
 * (latest videos, latest audios, latest PDF files) never offer visitors a hidden item's title or link, keep
 * showing up to 3 visible items in their existing order and filters, and ignore entries cached before the fix.
 * Anasheed (A6) and Telawah (T1, deferred LOW) homepage behaviour is unchanged.
 */

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const HKH_SECRET = 'عنوان مخفي سري';

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
        'nuke_ads' => MainSchema::nukeAds(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);
    Cache::flush();
    $this->service = app(ContentListingService::class);
});

/**
 * Per widget: four visible eligible rows (order column 400 → 100), a hidden eligible row that would lead the
 * list, and decoys each existing filter must still exclude.
 */
function hkhSeed(): void
{
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'الشيخ', 'prename' => '']);
    $row = fn (array $a) => $a + ['author' => 1, 'vedio' => 0, 'newslist' => 0, 'pdf' => 0, 'lastmirror' => 0, 'pdf_time' => 0, 'hidden' => 0, 'frame' => 0];

    $rows = [];
    foreach ([1 => 400, 2 => 300, 3 => 200, 4 => 100] as $i => $order) {
        $rows[] = $row(['id' => 10 + $i, 'title' => "مرئي {$i}", 'vedio' => 1, 'newslist' => 1, 'lastmirror' => $order]);
        $rows[] = $row(['id' => 20 + $i, 'title' => "صوتي {$i}", 'vedio' => 0, 'newslist' => 1, 'lastmirror' => $order]);
        $rows[] = $row(['id' => 30 + $i, 'title' => "مفرغ {$i}", 'pdf' => 1, 'pdf_time' => $order]);
    }
    // hidden, otherwise eligible, newest of all
    $rows[] = $row(['id' => 91, 'title' => HKH_SECRET.' مرئي', 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 999, 'hidden' => 1]);
    $rows[] = $row(['id' => 92, 'title' => HKH_SECRET.' صوتي', 'vedio' => 0, 'newslist' => 1, 'lastmirror' => 999, 'hidden' => 1]);
    $rows[] = $row(['id' => 93, 'title' => HKH_SECRET.' مفرغ', 'pdf' => 2, 'pdf_time' => 999, 'hidden' => 1]);
    // decoys for the existing filters (visible, newest)
    $rows[] = $row(['id' => 81, 'title' => 'خارج الأخبار', 'vedio' => 1, 'newslist' => 0, 'lastmirror' => 800]);
    $rows[] = $row(['id' => 82, 'title' => 'صوتي خارج الأخبار', 'vedio' => 0, 'newslist' => 0, 'lastmirror' => 800]);
    $rows[] = $row(['id' => 83, 'title' => 'بلا ملف', 'pdf' => 0, 'pdf_time' => 800]);
    $db->table('nuke_islamic_khotab')->insert($rows);
}

it('H1: each homepage Khotab widget excludes the hidden item and shows the next visible ones, in order, limit 3, filters unchanged', function (string $method, array $expected) {
    hkhSeed();

    $ids = $this->service->{$method}()->pluck('id')->map(fn ($id) => (int) $id)->all();

    expect($ids)->toBe($expected)->not->toContain(91)->not->toContain(92)->not->toContain(93);
})->with([
    'latest videos (vedio=1, newslist=1, lastmirror DESC)' => ['homeLatestVideos', [11, 12, 13]],
    'latest audios (vedio=0, newslist=1, lastmirror DESC)' => ['homeLatestAudios', [21, 22, 23]],
    'latest PDF files (pdf>0, pdf_time DESC)' => ['homeLatestDumpFiles', [31, 32, 33]],
]);

it('H1: the selected columns and joins are unchanged', function () {
    hkhSeed();

    expect(array_keys((array) $this->service->homeLatestVideos()->first()))->toBe(['id', 'time', 'ser_id', 'title', 'frame', 'thid', 'prename', 'name'])
        ->and(array_keys((array) $this->service->homeLatestAudios()->first()))->toBe(['id', 'title', 'time', 'prename', 'name'])
        ->and(array_keys((array) $this->service->homeLatestDumpFiles()->first()))->toBe(['id', 'title', 'frame', 'thid', 'prename', 'name']);
});

it('H1: rows cached under the pre-fix keys (holding a hidden item) are never read; the versioned keys are written, 300 s', function () {
    hkhSeed();
    $stale = [['id' => 91, 'title' => HKH_SECRET, 'time' => 1, 'ser_id' => 0, 'frame' => 0, 'thid' => 1, 'prename' => '', 'name' => 'الشيخ']];
    foreach (['home-latest-videos', 'home-latest-audios', 'home-latest-dump-files-3'] as $key) {
        Cache::put($key, $stale, 300);
    }

    expect($this->service->homeLatestVideos()->pluck('title')->all())->not->toContain(HKH_SECRET)->toContain('مرئي 1')
        ->and($this->service->homeLatestAudios()->pluck('title')->all())->not->toContain(HKH_SECRET)->toContain('صوتي 1')
        ->and($this->service->homeLatestDumpFiles()->pluck('title')->all())->not->toContain(HKH_SECRET)->toContain('مفرغ 1')
        ->and(Cache::has('home-latest-videos:hidden0'))->toBeTrue()
        ->and(Cache::has('home-latest-audios:hidden0'))->toBeTrue()
        ->and(Cache::has('home-latest-dump-files-3:hidden0'))->toBeTrue()
        // the old entries are left to expire on their own TTL — nothing flushed
        ->and(Cache::has('home-latest-videos'))->toBeTrue();
});

it('H1: the homepage HTML carries no hidden title or link; the three widgets still render their visible items', function () {
    hkhSeed();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain(HKH_SECRET)
        ->not->toContain('/khotab-item-91.htm')->not->toContain('/khotab-item-92.htm')->not->toContain('/khotab-item-93.htm')
        ->toContain('مرئي 1')->toContain('صوتي 1')->toContain('مفرغ 1')
        ->toContain('/khotab-item-11.htm')->toContain('/khotab-item-21.htm')->toContain('/khotab-item-31.htm')
        ->toContain('أحدث المرئيات على مدار الساعة')->toContain('جديد الصوتيات')->toContain('أحدث المواد المفرغة');
});

it('A6 preserved: hidden Anasheed items still appear in the homepage Anasheed widgets (owner-approved listing behaviour)', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'قسم', 'parent_id' => 57]);
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'نشيد مخفي في تشاهدون الآن', 'hidden' => 2, 'lastvisit' => 500, 'parent_id' => 0, 'group_id' => 0],
        ['id' => 2, 'title' => 'كارتون مخفي', 'hidden' => 2, 'lastvisit' => 0, 'parent_id' => 57, 'group_id' => 1],
    ]);
    $db->table('nuke_anasheed_advanced')->insert(['id' => 1, 'adur' => '1:00']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('نشيد مخفي في تشاهدون الآن')->toContain('/var-item-1.htm')
        ->toContain('كارتون مخفي')->toContain('/var-item-2.htm');
});

it('T1 unchanged: the homepage Telawah widget still lists the newest recitations (no hidden predicate — the fixture has no such column)', function () {
    $db = DB::connection('main');
    $db->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'قارئ']);
    $db->table('nuke_telawah_telawah')->insert([
        ['id' => 1, 'title' => 'تلاوة أولى', 'group_id' => 1],
        ['id' => 2, 'title' => 'تلاوة ثانية', 'group_id' => 1],
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('تلاوة ثانية')->toContain('/recite-item-2.htm')->toContain('تلاوة أولى')
        ->and($this->service->homeLatestTelawahs()->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([2, 1]);
});
