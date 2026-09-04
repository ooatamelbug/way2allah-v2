<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForAnasheedGroup(): void
{
    InMemoryConnection::setup('main', [
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAnasheedGroup();
});

it('show: renders sub-groups and items, increments the group\'s hits', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Parent Group', 'parent_id' => 0, 'hits' => 3],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'hits' => 0],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Group Item', 'group_id' => 1,
    ]);

    $response = $this->get('/var-group-1.htm');

    $response->assertOk()->assertSee('Sub Group')->assertSee('Group Item');
    expect(DB::connection('main')->table('nuke_anasheed_groups')->find(1)->hits)->toBe(4);
});

// ---- Shared Page Chrome Parity Audit: group.php:22-26's heading + ancestor breadcrumb (AnasheedGroup::breadcrumbTrail(), already proven correct by var-item-{id}.htm) ----

it('show: renders the heading and the full parent_id ancestor chain, ancestors linked, current group plain', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Grandparent', 'parent_id' => 0, 'hits' => 0],
        ['id' => 2, 'title' => 'Parent', 'parent_id' => 1, 'hits' => 0],
        ['id' => 3, 'title' => 'Current Group', 'parent_id' => 2, 'hits' => 0],
    ]);

    $content = $this->get('/var-group-3.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title">Current Group</h3>');
    expect($content)
        ->toContain('<li><a href="/var-group-1.htm">Grandparent</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/var-group-2.htm">Parent</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li>Current Group<i class=""></i></li>');

    $order = ['Grandparent</a>', 'Parent</a>', 'Current Group<i'];
    $positions = array_map(fn ($needle) => strpos($content, $needle), $order);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('show: a top-level group (parent_id=0) has a breadcrumb with only itself, current/plain', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Top Level Group', 'parent_id' => 0, 'hits' => 0],
    ]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li>Top Level Group<i class=""></i></li>')
        ->not->toContain('<a href="/var-group-1.htm">Top Level Group</a>');
});

// ---- var-group-{id}.htm parity: css/custom.css + the real portlet/card/download-block structure ----

it('show: renders premium subgroup, download, and searchable media-card sections', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Parent Group', 'parent_id' => 0, 'child' => 0, 'anasheed' => 0, 'hits' => 0, 'des' => null],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'child' => 1, 'anasheed' => 5, 'hits' => 9, 'des' => 'A comment'],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Group Item', 'group_id' => 1]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/css/custom.css')
        ->and(substr_count($content, 'portlet-title'))->toBe(3) // sub-groups + download + items
        ->and($content)->toContain('w2a-subgroup-card')
        ->and($content)->toContain('w2a-series-download-banner')
        ->and($content)->toContain('w2a-media-item-card')
        ->and($content)->toContain('w2a_anasheed_search_input')
        ->and($content)->toContain('/var-series-1.grx')
        ->and($content)->toContain('1 قسم فرعي')
        ->and($content)->toContain('5 مقطع')
        ->and($content)->toContain('9 زيارة');
});

it('show: the GetRight-download and items portlets do NOT render when the group has zero items (group.php:60-78\'s shared gate)', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Empty Group', 'parent_id' => 0]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('w2a-series-download-banner')
        ->and($content)->not->toContain('تحميل سلسلة')
        ->and($content)->not->toContain('قائمة المواد :');
});

// ---- G-13 closure (Anasheed Group Sidebar parity fix): group.php never calls most_downloaded_recent_sidebar() ----

it('show: does NOT render a "most downloaded"/"most recent" sidebar — group.php has no such box, unlike item.php', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Group Item', 'group_id' => 1, 'hits' => 1, 'downcount' => 1, 'mytime' => 1,
    ]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('الأكثر تحميلا')
        ->and($content)->not->toContain('احدث المواد');
});

it('show: an item beyond the first pagination page (never in "قائمة المواد" page 1) does not leak onto the page via a sidebar — proves no most-downloaded/most-recent query runs at all, not just that its heading is hidden', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group', 'parent_id' => 0]);

    $items = [];
    for ($i = 1; $i <= 30; $i++) {
        $items[] = ['id' => $i, 'title' => "Item {$i}", 'group_id' => 1, 'mytime' => $i, 'hits' => 1, 'downcount' => 1];
    }
    // Highest hits/downcount/mytime of all — would be the #1 "most downloaded"/"most
    // recent" sidebar candidate if that query still ran, but is on paginated page 2
    // (orderByDesc('mytime') puts it first, past the 30-per-page main-list limit is
    // avoided by giving it the LOWEST mytime instead, so it's the last item overall).
    $items[] = ['id' => 31, 'title' => 'Page Two Leaker', 'group_id' => 1, 'mytime' => 0, 'hits' => 999, 'downcount' => 999];
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert($items);

    $this->get('/var-group-1.htm')->assertOk()->assertDontSee('Page Two Leaker');
});

it('show: media cards use the raw frame path, optimized fallback, and formatted duration', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 100, 'title' => 'Framed Item', 'group_id' => 1, 'frame' => 1],
        ['id' => 200, 'title' => 'No-Frame Item', 'group_id' => 1, 'frame' => 0],
    ]);
    DB::connection('main')->table('nuke_anasheed_advanced')->insert(['id' => 100, 'adur' => 90000]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->toContain('src="/media/anasheed/frame/0/100.jpg"')
        ->and($content)->not->toContain('thumbnails.php') // this listing bypasses the resize proxy entirely
        ->and($content)->toContain('/assets/img/defult_shaik.png')
        ->and($content)->toContain('00:01:30');
});

// ---- G-13-04 (media/visual parity phase): sub-group icon thumbnail, anasheed/functions.php:208-212 ----

it('show: subgroup cards use a consistent folder icon instead of legacy image thumbnails', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Parent Group', 'parent_id' => 0, 'icon' => 0],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'icon' => 1],
    ]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('w2a-subgroup-icon-wrap')
        ->toContain('fa-folder-open')
        ->not->toContain('/media/anasheed/icons/0/2.jpg');
});

it('show: subgroup cards no longer load the legacy pix001 fallback image', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Parent Group', 'parent_id' => 0, 'icon' => 0],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'icon' => 0],
    ]);

    $content = $this->get('/var-group-1.htm')->assertOk()->getContent();

    expect($content)->toContain('w2a-subgroup-card')->not->toContain('/images/pix001.gif');
});

it('show: group 98\'s items also include group 16\'s items — the confirmed hardcoded special case', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 98, 'title' => 'News Group', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item In 98', 'group_id' => 98],
        ['id' => 2, 'title' => 'Item In 16', 'group_id' => 16],
        ['id' => 3, 'title' => 'Item In Other Group', 'group_id' => 5],
    ]);

    $response = $this->get('/var-group-98.htm');

    $response->assertOk()->assertSee('Item In 98')->assertSee('Item In 16')->assertDontSee('Item In Other Group');
});

it('show: a non-special group only shows its own items', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 5, 'title' => 'Group Five', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item In Five', 'group_id' => 5],
        ['id' => 2, 'title' => 'Item In Sixteen', 'group_id' => 16],
    ]);

    $response = $this->get('/var-group-5.htm');

    $response->assertOk()->assertSee('Item In Five')->assertDontSee('Item In Sixteen');
});

it('show: 404s for a nonexistent group', function () {
    $this->get('/var-group-999.htm')->assertNotFound();
});

// ---- G-12-01 (G-12 investigation): var-group-{id}-page-{page}.htm ----

it('show: paged route\'s page 2 shows the next 30 items, not page 1\'s items again', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group', 'parent_id' => 0]);

    $items = [];
    for ($i = 1; $i <= 31; $i++) {
        $items[] = ['id' => $i, 'title' => "Item {$i}", 'group_id' => 1, 'mytime' => $i, 'order_in_group' => 0];
    }
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert($items);

    // orderByDesc('mytime') — item 31 (highest mytime) is first, so page 1
    // (items 31..2, 30 items) does not include item 1; page 2 does. No
    // extraction/scoping needed — group.php confirmed has no sidebar at
    // all (G-13 closure), so there's no unrelated "most downloaded" box
    // that could coincidentally leak item 1 into the assertion.
    $page1 = $this->get('/var-group-1-page-1.htm')->assertOk()->getContent();
    $page2 = $this->get('/var-group-1-page-2.htm')->assertOk()->getContent();

    expect($page1)->not->toContain('Item 1<')->and($page1)->toContain('Item 31');
    expect($page2)->toContain('Item 1<')->and($page2)->not->toContain('Item 31');
});

it('show: paged route still increments hits and 404s for a nonexistent group, matching the base route', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group', 'parent_id' => 0, 'hits' => 0]);

    $this->get('/var-group-1-page-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_anasheed_groups')->find(1)->hits)->toBe(1);
    $this->get('/var-group-999-page-1.htm')->assertNotFound();
});
