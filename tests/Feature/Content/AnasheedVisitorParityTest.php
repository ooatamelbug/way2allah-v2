<?php

/*
 * Anasheed visitor parity (owner, 2026-11-04; decision-log #87) — OLD `modules/Anasheed` governs:
 * A1 news pages by department, A2 archive.org links normalised for downloads and the player,
 * A3 / A4 / A7 group listing (order, no 98 / 16 merge, 24 per page), A5 item notes,
 * A6 hidden items, A7 24 comments per page, A8 the `.grx` playlist.
 */

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;
use Tests\Support\ProxiedDownloadProbe;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
        'ips' => MainSchema::ips(),
    ]);
});

function avpDb(): Connection
{
    return DB::connection('main');
}

/** @return list<int> item ids linked in page order */
function avpItemIds(string $html): array
{
    preg_match_all('#href="/var-item-(\d+)\.htm"#', $html, $m);

    return array_values(array_unique(array_map('intval', $m[1])));
}

// ─── A1 — news pages by department ───────────────────────────────────────────

it('A1: each news page lists its whole department — pinned items, then the newest 24 by date', function (string $url, int $root) {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => $root, 'title' => 'Department', 'parent_id' => 0]);

    $rows = [];
    // 30 items spread over sub-groups of the department, newest has the highest mytime.
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['id' => $i, 'title' => "Item {$i}", 'group_id' => 1000 + ($i % 3), 'parent_id' => $root, 'fixed' => 0, 'mytime' => 5000 + $i];
    }
    // pinned items anywhere in the department, more than Gen 2's old cap would matter for order only
    $rows[] = ['id' => 101, 'title' => 'Pinned old', 'group_id' => 1001, 'parent_id' => $root, 'fixed' => 1, 'mytime' => 10];
    $rows[] = ['id' => 102, 'title' => 'Pinned new', 'group_id' => $root, 'parent_id' => $root, 'fixed' => 1, 'mytime' => 20];
    // another department's items never appear
    $rows[] = ['id' => 201, 'title' => 'Elsewhere', 'group_id' => 5, 'parent_id' => 5, 'fixed' => 1, 'mytime' => 99999];
    avpDb()->table('nuke_anasheed_anasheed')->insert($rows);

    $html = $this->get($url)->assertOk()->getContent();
    preg_match('/المواد المثبتة">(.*?)<\/section>/s', $html, $pinned);
    preg_match('/المواد الجديدة">(.*?)<\/section>/s', $html, $newest);

    expect(avpItemIds($pinned[1] ?? ''))->toBe([102, 101])
        // newest 24 by mytime DESC across the department: ids 30..7 (the pinned items are older)
        ->and(avpItemIds($newest[1] ?? ''))->toBe(range(30, 7))
        ->and($html)->not->toContain('Elsewhere');
})->with([
    'exclusive-news' => ['/exclusive-news.htm', 158],
    'cartoon-news' => ['/cartoon-news.htm', 57],
    'documentary-news' => ['/documentary-news.htm', 12],
]);

it('A1: anasheed-news.htm takes department 98 together with department 16', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 98, 'title' => 'Anasheed', 'parent_id' => 0]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Dept 98 item', 'group_id' => 300, 'parent_id' => 98, 'fixed' => 1, 'mytime' => 3],
        ['id' => 2, 'title' => 'Dept 16 item', 'group_id' => 400, 'parent_id' => 16, 'fixed' => 1, 'mytime' => 2],
        ['id' => 3, 'title' => 'Dept 57 item', 'group_id' => 57, 'parent_id' => 57, 'fixed' => 1, 'mytime' => 9],
    ]);

    $html = $this->get('/anasheed-news.htm')->assertOk()->getContent();

    expect($html)->toContain('Dept 98 item')->toContain('Dept 16 item')->not->toContain('Dept 57 item');
});

it('A1 UI: news items show as media cards with their link, title and — only when known — date; an empty list is not shown', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 57, 'title' => 'Cartoon', 'parent_id' => 0]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Dated item', 'group_id' => 57, 'parent_id' => 57, 'fixed' => 0, 'mytime' => 1700000000, 'frame' => 0],
        ['id' => 2, 'title' => 'Undated item', 'group_id' => 57, 'parent_id' => 57, 'fixed' => 0, 'mytime' => 0, 'frame' => 0],
    ]);

    $html = $this->get('/cartoon-news.htm')->assertOk()->getContent();
    preg_match('/المواد الجديدة">(.*?)<\/section>/s', $html, $newest);
    $cards = preg_split('/(?=<a href="\/var-item-)/', $newest[1] ?? '');

    expect($html)->toContain('<title>أحدث المواد بقسم Cartoon')
        // no pinned items: that panel is left out, as OLD ListNews() did
        ->not->toContain('aria-label="المواد المثبتة"')
        ->and(avpItemIds($newest[1] ?? ''))->toBe([1, 2])
        ->and($newest[1] ?? '')->toContain('class="w2a-media-item-card"')->toContain('Dated item')->toContain('Undated item')
        ->and(collect($cards)->first(fn ($c) => str_contains($c, 'Dated item')))->toContain('fa-calendar')
        ->and(collect($cards)->first(fn ($c) => str_contains($c, 'Undated item')))->not->toContain('fa-calendar');
});

// ─── A2 — archive.org links ──────────────────────────────────────────────────

it('A2: item and mirror downloads redirect to the canonical archive.org URL, still counting the download', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 3, 'title' => 'Old node', 'hidden' => 0, 'downcount' => 4,
        'link' => 'http://ia331419.us.archive.org/3/items/way2allah_islamics505/3_4_abt-nfsa.rmvb']);
    avpDb()->table('nuke_anasheed_mirror')->insert(['id' => 8276, 'khid' => 3, 'title' => 'mp3', 'hidden' => 0, 'hits' => 1,
        'link' => 'http://ia601500.us.archive.org/1/items/elrafk2/Rahma.mp3']);

    // F04: downloaded through the protected proxy from the canonical URL (refused here: the pool is full, not counted).
    expect(ProxiedDownloadProbe::resolvedLink('/var-download-3.htm'))->toBe('https://archive.org/download/way2allah_islamics505/3_4_abt-nfsa.rmvb')
        ->and(ProxiedDownloadProbe::resolvedLink('/var-mirror-3-8276.htm'))->toBe('https://archive.org/download/elrafk2/Rahma.mp3');

    expect((int) avpDb()->table('nuke_anasheed_anasheed')->where('id', 3)->value('downcount'))->toBe(4)
        // the stored links are never rewritten
        ->and(avpDb()->table('nuke_anasheed_anasheed')->where('id', 3)->value('link'))->toBe('http://ia331419.us.archive.org/3/items/way2allah_islamics505/3_4_abt-nfsa.rmvb')
        ->and(avpDb()->table('nuke_anasheed_mirror')->where('id', 8276)->value('link'))->toBe('http://ia601500.us.archive.org/1/items/elrafk2/Rahma.mp3');
});

it('A2: details-page and non-archive links keep their previous redirect (https upgrade only)', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 6662, 'title' => 'Item', 'hidden' => 0, 'link' => 'https://cdn.example.com/a.mp4'],
    ]);
    avpDb()->table('nuke_anasheed_mirror')->insert(['id' => 8493, 'khid' => 6662, 'title' => 'flv', 'hidden' => 0,
        'link' => 'http://archive.org/details/Han3alekRasolEllahUshtam.flv']);

    expect(ProxiedDownloadProbe::resolvedLink('/var-download-6662.htm'))->toBe('https://cdn.example.com/a.mp4')
        ->and(ProxiedDownloadProbe::resolvedLink('/var-mirror-6662-8493.htm'))->toBe('https://archive.org/details/Han3alekRasolEllahUshtam.flv');
});

it('A2: the player plays item and mirror archive.org links from the canonical URL', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 17327, 'title' => 'Video', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://ia601301.us.archive.org/23/items/tghyan_alnfs/meqat_almo7been.mp4']);
    avpDb()->table('nuke_anasheed_mirror')->insert(['id' => 8276, 'khid' => 17327, 'title' => 'mp3', 'vedio' => 0, 'hidden' => 0,
        'link' => 'http://ia601500.us.archive.org/1/items/elrafk2/Rahma.mp3']);

    $item = $this->post('/media-player', ['id' => 17327, 'type' => 'anasheed'])->assertOk()->getContent();
    $mirror = $this->post('/media-player', ['id' => 8276, 'type' => 'anasheed_mirror'])->assertOk()->getContent();

    expect($item)->toContain('https://archive.org/download/tghyan_alnfs/meqat_almo7been.mp4')->not->toContain('ia601301')
        ->and($mirror)->toContain('https://archive.org/download/elrafk2/Rahma.mp3')->not->toContain('ia601500');
});

// ─── A3 / A4 / A7 — group listing ────────────────────────────────────────────

it('A3: a group lists its items by order_in_group DESC, then mytime DESC', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 300, 'title' => 'Group', 'parent_id' => 98]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Newest unordered', 'group_id' => 300, 'order_in_group' => 0, 'mytime' => 900],
        ['id' => 2, 'title' => 'Ordered first', 'group_id' => 300, 'order_in_group' => 5, 'mytime' => 100],
        ['id' => 3, 'title' => 'Ordered second, newer', 'group_id' => 300, 'order_in_group' => 3, 'mytime' => 300],
        ['id' => 4, 'title' => 'Ordered second, older', 'group_id' => 300, 'order_in_group' => 3, 'mytime' => 200],
    ]);

    expect(avpItemIds($this->get('/var-group-300.htm')->assertOk()->getContent()))->toBe([2, 3, 4, 1]);
});

it('A7: a group page holds 24 items, the rest on page 2', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 300, 'title' => 'Group', 'parent_id' => 98]);
    $rows = [];
    for ($i = 1; $i <= 26; $i++) {
        $rows[] = ['id' => $i, 'title' => "Item {$i}", 'group_id' => 300, 'order_in_group' => 0, 'mytime' => $i];
    }
    avpDb()->table('nuke_anasheed_anasheed')->insert($rows);

    expect(avpItemIds($this->get('/var-group-300.htm')->assertOk()->getContent()))->toHaveCount(24)
        ->and(avpItemIds($this->get('/var-group-300-page-2.htm')->assertOk()->getContent()))->toBe([2, 1]);
});

it('A4: group 98\'s page lists only its own items, while anasheed-news.htm still takes 98 with 16', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 98, 'title' => 'Anasheed', 'parent_id' => 0]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Own item', 'group_id' => 98, 'parent_id' => 98, 'mytime' => 2],
        ['id' => 2, 'title' => 'Group 16 item', 'group_id' => 16, 'parent_id' => 16, 'mytime' => 1],
    ]);

    expect(avpItemIds($this->get('/var-group-98.htm')->assertOk()->getContent()))->toBe([1])
        ->and($this->get('/anasheed-news.htm')->assertOk()->getContent())->toContain('Group 16 item');
});

// ─── A5 — notes ──────────────────────────────────────────────────────────────

it('A5: an item\'s notes show as «تعليق على المادة», escaped; no block without notes', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'With notes', 'hidden' => 0, 'notes' => 'ملاحظة <b>مهمة</b>'],
        ['id' => 2, 'title' => 'Without notes', 'hidden' => 0, 'notes' => ''],
    ]);

    $with = $this->get('/var-item-1.htm')->assertOk()->getContent();
    $without = $this->get('/var-item-2.htm')->assertOk()->getContent();

    expect($with)->toContain('تعليق على المادة')->toContain('ملاحظة &lt;b&gt;مهمة&lt;/b&gt;')->not->toContain('<b>مهمة</b>')
        ->and($without)->not->toContain('تعليق على المادة');
});

// ─── A6 — hidden items ───────────────────────────────────────────────────────

// F08 / B4 (owner-approved, 2026-11-14): for VISITORS a hidden item is now a 404, and its hit counter does not move.
// The A6 page below (chrome kept, details suppressed) is what a signed-in administrator sees: AnasheedVisibilityB4Test.
it('A6 → B4: a hidden item is a 404 for visitors and its hits do not count', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Hidden item', 'hidden' => 2, 'hits' => 7]);

    $this->get('/var-item-1.htm')->assertNotFound()->assertDontSee('Hidden item');
    expect((int) avpDb()->table('nuke_anasheed_anasheed')->where('id', 1)->value('hits'))->toBe(7);
});

it('A6: a hidden item\'s media is not served — download, mirror download, player and mirror player', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Hidden', 'hidden' => 1, 'vedio' => 0, 'downcount' => 0, 'link' => 'https://cdn.example.com/h.mp3']);
    avpDb()->table('nuke_anasheed_mirror')->insert(['id' => 1, 'khid' => 1, 'title' => 'Mirror', 'hidden' => 0, 'vedio' => 0, 'hits' => 0, 'link' => 'https://cdn.example.com/m.mp3']);

    $this->get('/var-download-1.htm')->assertNotFound();
    $this->get('/var-mirror-1-1.htm')->assertNotFound();
    expect($this->post('/media-player', ['id' => 1, 'type' => 'anasheed'])->assertOk()->getContent())->toBe('')
        ->and($this->post('/media-player', ['id' => 1, 'type' => 'anasheed_mirror'])->assertOk()->getContent())->toBe('')
        ->and((int) avpDb()->table('nuke_anasheed_anasheed')->where('id', 1)->value('downcount'))->toBe(0)
        ->and((int) avpDb()->table('nuke_anasheed_mirror')->where('id', 1)->value('hits'))->toBe(0);
});

it('A6: a visible item is unaffected — details, download and its mirror all work', function () {
    avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Visible', 'hidden' => 0, 'mirror' => 1, 'link' => 'https://cdn.example.com/v.mp3']);
    avpDb()->table('nuke_anasheed_mirror')->insert(['id' => 1, 'khid' => 1, 'title' => 'Visible mirror', 'hidden' => 0, 'link' => 'https://cdn.example.com/m.mp3']);

    expect($this->get('/var-item-1.htm')->assertOk()->getContent())->toContain('تفاصيل المادة')->toContain('Visible mirror');
    expect(ProxiedDownloadProbe::resolvedLink('/var-download-1.htm'))->toBe('https://cdn.example.com/v.mp3')
        ->and(ProxiedDownloadProbe::resolvedLink('/var-mirror-1-1.htm'))->toBe('https://cdn.example.com/m.mp3');
});

// ─── A8 — the .grx playlist ──────────────────────────────────────────────────

it('A8: the .grx playlist skips hidden items, orders by weight ASC and uses OLD\'s «الأناشيد» folder', function () {
    avpDb()->table('nuke_anasheed_groups')->insert(['id' => 300, 'title' => 'Series', 'parent_id' => 98]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Heavy', 'group_id' => 300, 'hidden' => 0, 'weight' => 30, 'order_in_group' => 9, 'link' => 'https://x/a.mp3'],
        ['id' => 2, 'title' => 'Light', 'group_id' => 300, 'hidden' => 0, 'weight' => 10, 'order_in_group' => 1, 'link' => 'https://x/b.mp3'],
        ['id' => 3, 'title' => 'Hidden', 'group_id' => 300, 'hidden' => 2, 'weight' => 20, 'order_in_group' => 5, 'link' => 'https://x/c.mp3'],
    ]);

    $grx = $this->get('/var-series-300.grx')->assertOk()->getContent();

    preg_match_all('#/var-download-(\d+)\.htm#', $grx, $m);
    expect(array_map('intval', $m[1]))->toBe([2, 1])
        ->and($grx)->toContain('File: C:\\Way2Allah\\الأناشيد\\Series\\Light.mp3.GetRight')
        ->not->toContain('المنوعات')->not->toContain('Hidden');
});

// ─── A9–A12 — LOW closure sprint (owner, 2026-11-04) ─────────────────────────

it('A9: sub-group cards show the group icon, «التعليق» and zero counts, as OLD group_cont.htm did', function () {
    avpDb()->table('nuke_anasheed_groups')->insert([
        ['id' => 300, 'title' => 'Parent', 'parent_id' => 98, 'icon' => 0, 'des' => null, 'anasheed' => 0, 'child' => 2],
        ['id' => 1200, 'title' => 'With icon', 'parent_id' => 300, 'icon' => 1, 'des' => 'وصف <b>القسم</b>', 'anasheed' => 4, 'child' => 0],
        ['id' => 301, 'title' => 'Empty', 'parent_id' => 300, 'icon' => 0, 'des' => '', 'anasheed' => 0, 'child' => 0],
    ]);
    // F08 / B4 (2026-11-15): the card count is now the group's publicly visible items, so the fixture has 4 real ones.
    foreach ([1, 2, 3, 4] as $n) {
        avpDb()->table('nuke_anasheed_anasheed')->insert(['id' => 9000 + $n, 'title' => 'Item '.$n, 'group_id' => 1200, 'hidden' => 0]);
    }

    $html = $this->get('/var-group-300.htm')->assertOk()->getContent();

    expect($html)->toContain('media/anasheed/icons/1/1200.jpg')
        ->toContain('/images/pix001.gif')
        ->toContain('<strong>التعليق :</strong> وصف &lt;b&gt;القسم&lt;/b&gt;')
        ->toContain('<strong>التعليق :</strong> بدون تعليق')
        ->toContain('0 مقطع')
        ->toContain('4 مقطع')
        ->not->toContain('fa-folder-open');
});

it('A10: the series download block shows on a sub-group with OLD\'s GetRight link and notice, never on a root department', function () {
    avpDb()->table('nuke_anasheed_groups')->insert([
        ['id' => 158, 'title' => 'Root', 'parent_id' => 0],
        ['id' => 300, 'title' => 'Series', 'parent_id' => 158],
    ]);
    avpDb()->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Root item', 'group_id' => 158],
        ['id' => 2, 'title' => 'Series item', 'group_id' => 300],
    ]);

    $root = $this->get('/var-group-158.htm')->assertOk()->getContent();
    $series = $this->get('/var-group-300.htm')->assertOk()->getContent();

    expect($root)->toContain('Root item')->not->toContain('/var-series-158.grx')->not->toContain('data-getright-link')
        ->and($series)->toContain('/var-series-300.grx')
        ->toContain('href="https://getright.com/"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('رابط تحميل برنامج التحميل')
        ->toContain('قبل تحميل السلسلة يجب أن يكون برنامج (getright) مثبت على جهازك')
        ->not->toContain('getright-download.exe');
});

it('A11: the group pager shows above and below the items when there is more than one page, and not otherwise', function () {
    avpDb()->table('nuke_anasheed_groups')->insert([
        ['id' => 300, 'title' => 'Big', 'parent_id' => 98],
        ['id' => 301, 'title' => 'Small', 'parent_id' => 98],
    ]);
    $rows = [];
    for ($i = 1; $i <= 26; $i++) {
        $rows[] = ['id' => $i, 'title' => "Item {$i}", 'group_id' => $i <= 25 ? 300 : 301, 'mytime' => $i];
    }
    avpDb()->table('nuke_anasheed_anasheed')->insert($rows);

    $big = $this->get('/var-group-300.htm')->assertOk()->getContent();
    preg_match('/قائمة المواد : Big(.*)$/s', $big, $list);
    $firstPager = strpos($list[1], 'class="w2a-pagination"');
    $firstItem = strpos($list[1], 'href="/var-item-');

    expect(substr_count($big, 'class="w2a-pagination"'))->toBe(2)
        ->and($firstPager)->toBeLessThan($firstItem)
        ->and(substr_count($this->get('/var-group-301.htm')->assertOk()->getContent(), 'class="w2a-pagination"'))->toBe(0);
});

it('A12: the item sidebar covers the department\'s direct sub-groups, six rows each, the «new» list titled per department', function (int $department, string $title) {
    avpDb()->table('nuke_anasheed_groups')->insert([
        ['id' => $department, 'title' => 'Department', 'parent_id' => 0],
        ['id' => 1100, 'title' => 'Sub A', 'parent_id' => $department],
        ['id' => 1101, 'title' => 'Sub B', 'parent_id' => $department],
        ['id' => 1102, 'title' => 'Deeper', 'parent_id' => 1100],
        ['id' => 1900, 'title' => 'Other department', 'parent_id' => 0],
        ['id' => 1901, 'title' => 'Other sub', 'parent_id' => 1900],
    ]);
    $rows = [];
    for ($i = 1; $i <= 8; $i++) {
        $rows[] = ['id' => $i, 'title' => "Dept item {$i}", 'group_id' => $i % 2 === 0 ? 1100 : 1101, 'hits' => $i, 'mytime' => 100 + $i];
    }
    $rows[] = ['id' => 50, 'title' => 'Deeper item', 'group_id' => 1102, 'hits' => 999, 'mytime' => 999];
    $rows[] = ['id' => 51, 'title' => 'Root item', 'group_id' => $department, 'hits' => 998, 'mytime' => 998];
    $rows[] = ['id' => 60, 'title' => 'Other item', 'group_id' => 1901, 'hits' => 997, 'mytime' => 997];
    avpDb()->table('nuke_anasheed_anasheed')->insert($rows);

    // viewed from the deeper group: the department is still the root of its chain
    $html = $this->get('/var-item-50.htm')->assertOk()->getContent();
    preg_match('/aria-label="الشريط الجانبي">(.*)<\/aside>/s', $html, $aside);
    preg_match_all('#href="/var-item-(\d+)\.htm"#', $aside[1], $m);
    $ids = array_map('intval', $m[1]);

    expect(array_slice($ids, 0, 6))->toBe([8, 7, 6, 5, 4, 3])   // most downloaded: hits DESC
        ->and(array_slice($ids, 6))->toBe([8, 7, 6, 5, 4, 3])   // newest: mytime DESC
        ->and($aside[1])->toContain('الأكثر تحميلا')->toContain($title)
        ->not->toContain('Other item')->not->toContain('Deeper item')->not->toContain('Root item');
})->with([
    'anasheed' => [98, 'جديد الأناشيد'],
    'moving clips' => [16, 'جديد المقاطع المؤثرة'],
    'documentaries' => [12, 'جديد الأفلام الوثائقية'],
    'cartoon' => [57, 'جديد الكرتون'],
    'site releases' => [158, 'جديد إصدارات الموقع'],
    'any other department' => [77, 'جديد الأناشيد'],
]);
