<?php

/*
 * F08 / B4 (owner-approved 2026-11-15) — the sub-group cards on `var-group-{id}` show each group's PUBLICLY VISIBLE
 * item count (`group_id = X AND hidden = 0`, one aggregate query), not the stored `nuke_anasheed_groups.anasheed`
 * counter, which includes hidden items and has drifted. Stored counters are never written.
 */

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AnasheedVisibilityHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $db = DB::connection('main');
    // The page (`var-group-5`) and its sub-groups. Group 57 reproduces the local discrepancy: stored 142, 137 visible.
    $db->table('nuke_anasheed_groups')->insert([
        ['id' => 5, 'title' => 'الصفحة', 'parent_id' => 0, 'anasheed' => 0, 'child' => 5],
        ['id' => 57, 'title' => 'كرتون', 'parent_id' => 5, 'anasheed' => 142, 'child' => 0],
        ['id' => 60, 'title' => 'بلا مخفي', 'parent_id' => 5, 'anasheed' => 3, 'child' => 0],
        ['id' => 61, 'title' => 'كله مخفي', 'parent_id' => 5, 'anasheed' => 2, 'child' => 0],
        ['id' => 62, 'title' => 'فارغ', 'parent_id' => 5, 'anasheed' => 0, 'child' => 0],
        ['id' => 63, 'title' => 'عداد منحرف', 'parent_id' => 5, 'anasheed' => 10, 'child' => 0],
    ]);
    $id = 100;
    $add = function (int $group, int $n, int $hidden = 0) use ($db, &$id) {
        for ($i = 0; $i < $n; $i++) {
            $db->table('nuke_anasheed_anasheed')->insert(['id' => ++$id, 'title' => 'م'.$id, 'group_id' => $group, 'parent_id' => 5, 'hidden' => $hidden, 'mytime' => $id]);
        }
    };
    $add(57, 137);
    $add(57, 3, 2);      // legacy hidden value 2
    $add(57, 2, 1);      // hidden by the B4 toggle
    $add(60, 3);
    $add(61, 2, 2);
    $add(63, 3);         // stored 10, only 3 rows: legacy drift
    $add(5, 30);         // the page's own items (pagination)
    $this->groupsBefore = DB::connection('main')->table('nuke_anasheed_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
});

/** @return array<int, int> group id ⇒ the number on its card, in page order */
function b4CardCounts(string $html): array
{
    preg_match_all('#href="/var-group-(\d+)\.htm" class="w2a-subgroup-card">.*?w2a-subgroup-count">.*?</i>\s*(\d+) مقطع#su', $html, $m, PREG_SET_ORDER);
    $cards = [];
    foreach ($m as [, $group, $count]) {
        $cards[(int) $group] = (int) $count;
    }

    return $cards;
}

it('each card shows its publicly visible count (group 57: stored 142 → 137), in the unchanged card order', function () {
    $cards = b4CardCounts($this->get('/var-group-5.htm')->assertOk()->getContent());

    expect($cards)->toBe([63 => 3, 62 => 0, 61 => 0, 60 => 3, 57 => 137]);   // orderByDesc('id') as before
});

it('the service counts hidden = 0 only, returns 0 for groups without visible items, and nothing for no ids', function () {
    $listing = app(ContentListingService::class);

    expect($listing->anasheedVisibleItemCountsByGroup([57, 60, 61, 62, 63, 999]))->toBe([57 => 137, 60 => 3, 61 => 0, 62 => 0, 63 => 3, 999 => 0])
        ->and($listing->anasheedVisibleItemCountsByGroup([]))->toBe([]);
});

it('hiding and unhiding change the displayed count immediately', function () {
    $this->actingAs(H::admin(), 'admin');
    $this->put(route('admin.anasheed.items.visibility', 101), ['hidden' => '1'])->assertRedirect();

    // The cards show the public count to every viewer (asserted below for holders), so the admin's page view is enough.
    expect(b4CardCounts($this->get('/var-group-5.htm')->getContent())[57])->toBe(136);

    $this->put(route('admin.anasheed.items.visibility', 101), ['hidden' => '0'])->assertRedirect();
    $this->put(route('admin.anasheed.items.visibility', 238), ['hidden' => '0'])->assertRedirect();   // a legacy `2`
    expect(b4CardCounts($this->get('/var-group-5.htm')->getContent())[57])->toBe(138);
});

it('never writes the stored counters (page views, hide, unhide)', function () {
    $this->get('/var-group-5.htm')->assertOk();
    $this->actingAs(H::admin(), 'admin');
    $this->put(route('admin.anasheed.items.visibility', 101), ['hidden' => '1']);
    $this->put(route('admin.anasheed.items.visibility', 101), ['hidden' => '0']);
    $this->get('/var-group-5.htm')->assertOk();

    $after = DB::connection('main')->table('nuke_anasheed_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    // Only the page group's own `hits` moves (the existing view counter), never `anasheed`.
    $strip = fn (array $rows) => array_map(fn ($r) => array_diff_key($r, ['hits' => 0]), $rows);
    expect($strip($after))->toBe($strip($this->groupsBefore));
});

it('one aggregate query for all cards, whatever their number (no N+1) — 5 vs 40 sub-groups', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_groups')->insert(['id' => 7, 'title' => 'كبير', 'parent_id' => 0]);
    for ($g = 0; $g < 40; $g++) {
        $db->table('nuke_anasheed_groups')->insert(['id' => 700 + $g, 'title' => 'ف'.$g, 'parent_id' => 7, 'anasheed' => 9]);
        $db->table('nuke_anasheed_anasheed')->insert(['id' => 5000 + $g, 'title' => 'x', 'group_id' => 700 + $g, 'hidden' => $g % 2]);
    }
    $log = [];
    DB::connection('main')->listen(function ($q) use (&$log) {
        $log[] = $q->sql;
    });
    $measure = function (string $url) use (&$log) {
        $log = [];
        $html = $this->get($url)->assertOk()->getContent();
        $items = array_values(array_filter($log, fn ($sql) => str_contains($sql, 'nuke_anasheed_anasheed')));

        return [count($log), count($items), count(array_filter($items, fn ($sql) => str_contains(strtolower($sql), 'group by'))), $html];
    };

    [$fewTotal, $fewItems, $fewAgg] = $measure('/var-group-5.htm');
    [$manyTotal, $manyItems, $manyAgg, $html] = $measure('/var-group-7.htm');

    expect($fewAgg)->toBe(1)->and($manyAgg)->toBe(1)
        ->and($manyItems)->toBeLessThanOrEqual($fewItems)   // 40 cards cost no more item queries than 5
        ->and($manyTotal)->toBeLessThanOrEqual($fewTotal)
        ->and(array_sum(b4CardCounts($html)))->toBe(20)   // every other item hidden
        ->and(count(b4CardCounts($html)))->toBe(40);
});

it('the page\'s own item list keeps its pagination (24) and order', function () {
    $first = $this->get('/var-group-5.htm')->assertOk()->getContent();
    $second = $this->get('/var-group-5-page-2.htm')->assertOk()->getContent();
    preg_match_all('#href="/var-item-(\d+)\.htm" class="w2a-media-item-card"#', $first, $p1);
    preg_match_all('#href="/var-item-(\d+)\.htm" class="w2a-media-item-card"#', $second, $p2);

    $ownItems = range(251, 280);   // the page's 30 own items, ids 251..280 (mytime = id)
    expect(array_map('intval', $p1[1]))->toBe(array_slice(array_reverse($ownItems), 0, 24))
        ->and(array_map('intval', $p2[1]))->toBe(array_slice(array_reverse($ownItems), 24));
});

it('a holder sees the same public counts on the cards (hidden items are counted nowhere)', function () {
    $this->actingAs(H::admin(), 'admin');

    expect(b4CardCounts($this->get('/var-group-5.htm')->assertOk()->getContent()))->toBe([63 => 3, 62 => 0, 61 => 0, 60 => 3, 57 => 137]);
});
