<?php

/*
 * B2 / F03-B (owner-approved 2026-11-16, decision-log #146) — on `khotab-group-{id}.htm` an administrator holding
 * `khotab.editseries` (or a super-admin) also sees the group's hidden («++مخفي++») and empty («فارغة») series; every
 * other request gets exactly the visitor list. «فارغة» = no item the series page would list for an admin (author,
 * media type, series, group; hidden items included), never inferred from the stored counter (F03-A: some are wrong).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'أحمد', 'prename' => 'الشيخ']);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'count' => 9]);
    $g = $this->group;

    $series = fn (string $title, array $extra) => H::insert('nuke_islamic_series', $extra + ['author_id' => 7, 'group_id' => $g, 'vedio' => 1, 'hidden' => 0, 'title' => $title, 'channel_id' => 3]);
    $item = fn (int $ser, array $extra = []) => H::insert('nuke_islamic_khotab', $extra + ['author' => 7, 'vedio' => 1, 'group_id' => $g, 'ser_id' => $ser, 'hidden' => 0, 'title' => 'مادة']);

    $this->s = [
        'normal' => $series('عادية', ['count' => 2, 'lastupdate' => 300]),
        'hidden' => $series('سلسلة سرية', ['count' => 3, 'lastupdate' => 250, 'hidden' => 1]),
        'second' => $series('ثانية', ['count' => 1, 'lastupdate' => 200]),
        'copy' => $series('عداد بلا مواد', ['count' => 5, 'lastupdate' => 150]),          // F03 "C": counted, no items
        'hiddenItems' => $series('موادها مخفية', ['count' => 2, 'lastupdate' => 100]),     // only hidden items
        'broken' => $series('عداد خاطئ', ['count' => -15, 'lastupdate' => 50]),            // F03-A: own items, counter ≤ 0
        'foreign' => $series('مواد غريبة', ['count' => 0, 'lastupdate' => 20]),            // only another author's items
        'empty' => $series('جديدة فارغة', ['count' => 0, 'lastupdate' => 0]),             // just created
    ];
    $item($this->s['normal']);
    $item($this->s['normal']);
    foreach (range(1, 3) as $n) {
        $item($this->s['hidden']);
    }
    $item($this->s['second']);
    $item($this->s['hiddenItems'], ['hidden' => 1]);
    $item($this->s['hiddenItems'], ['hidden' => 1]);
    foreach (range(1, 4) as $n) {
        $item($this->s['broken']);
    }
    H::insert('nuke_islamic_khotab', ['author' => 99, 'vedio' => 1, 'group_id' => 0, 'ser_id' => $this->s['foreign'], 'hidden' => 0, 'title' => 'غريبة']);
    // Never on this page: another media type, another group.
    H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $g, 'vedio' => 0, 'hidden' => 1, 'title' => 'صوتية', 'count' => 0, 'lastupdate' => 999]);
    H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $g + 100, 'vedio' => 1, 'hidden' => 1, 'title' => 'أخرى', 'count' => 0, 'lastupdate' => 999]);

    $this->url = "/khotab-group-{$g}.htm";
});

const F03B_PUBLIC = ['normal', 'second', 'copy', 'hiddenItems'];
const F03B_ADMIN = ['normal', 'hidden', 'second', 'copy', 'hiddenItems', 'broken', 'foreign', 'empty'];

/** The part of the page between two markers (the sidebar widgets also link to series and items). */
function f03bRegion(string $html, string $from, string $to): string
{
    $start = strpos($html, $from);
    if ($start === false) {
        return '';
    }
    $end = strpos($html, $to, $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/** Series ids in the group's series grid, in page order. */
function f03bOrder(string $html): array
{
    preg_match_all('~href="/khotab-series-(\d+)\.htm"~', f03bRegion($html, 'قائمة السلاسل', 'قائمة المواد'), $m);

    return array_map('intval', $m[1]);
}

/** Item links in a page's main item list. */
function f03bItemLinks(string $html): array
{
    preg_match_all('~href="/khotab-item-(\d+)\.htm"~', f03bRegion($html, 'w2a-items-list-wrap', 'col-lg-3'), $m);

    return array_values(array_unique($m[1]));
}

/** A fresh request scope, as each real request has (ContextualAdmin is a scoped binding). */
function f03bNextRequest(): void
{
    H::visitor();
    app()->forgetScopedInstances();
}

/** The count badge of one series card (its text, markers included). */
function f03bBadge(string $html, int $id): string
{
    preg_match('~<a href="/khotab-series-'.$id.'\.htm" class="w2a-cat-series-card">.*?<span class="w2a-cat-series-count">(.*?)</span>\s*</div>~su', $html, $m);

    return trim(preg_replace('~\s+~u', ' ', strip_tags($m[1] ?? '', '<span>')));
}

function f03bIds(array $keys): array
{
    return array_map(fn (string $k) => test()->s[$k], $keys);
}

// --- 1 / 2 / 9 / 11. Visitors and unauthorized admins: the visitor list, unchanged -----------------------------------

it('a visitor sees exactly the public list, in lastupdate order, with no markers', function () {
    $response = $this->get($this->url)->assertOk();
    $html = $response->getContent();

    expect(f03bOrder($html))->toBe(f03bIds(F03B_PUBLIC))
        ->and($html)->not->toContain('++مخفي++')->not->toContain('w2a-empty-series-marker')->not->toContain('فارغة')
        ->and($html)->not->toContain('سلسلة سرية')->not->toContain('جديدة فارغة')->not->toContain('عداد خاطئ')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('an admin without khotab.editseries sees the visitor list (other admin rights do not help)', function (array $permissions) {
    $html = $this->withSession(H::session(H::admin($permissions)))->get($this->url)->assertOk()->getContent();

    expect(f03bOrder($html))->toBe(f03bIds(F03B_PUBLIC))
        ->and($html)->not->toContain('++مخفي++')->not->toContain('w2a-empty-series-marker');
})->with([
    'no permission' => [[]],
    'other khotab rights' => [['khotab.addseries', 'khotab.additem', 'khotab.editgroup', 'khotab.moveitem', 'khotab.editauthor']],
]);

it('request parameters cannot unlock the admin list', function (string $query) {
    $html = $this->get($this->url.$query)->assertOk()->getContent();
    expect(f03bOrder($html))->toBe(f03bIds(F03B_PUBLIC))->and($html)->not->toContain('++مخفي++');

    $html = $this->withSession(H::session(H::admin([], 'plain')))->get($this->url.$query)->assertOk()->getContent();
    expect(f03bOrder($html))->toBe(f03bIds(F03B_PUBLIC));
})->with(['?admin=1', '?hidden=1&include_hidden=1', '?permission=khotab.editseries', '?page=2']);

// --- 3–7, 9, 13. Authorized admins ----------------------------------------------------------------------------------

it('an admin with khotab.editseries sees hidden and empty series too, in the same lastupdate order', function () {
    $html = $this->withSession(H::session(H::admin(['khotab.editseries'])))->get($this->url)->assertOk()->getContent();

    expect(f03bOrder($html))->toBe(f03bIds(F03B_ADMIN));
});

it('a super-admin sees the same list', function () {
    $html = $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk()->getContent();

    expect(f03bOrder($html))->toBe(f03bIds(F03B_ADMIN));
});

it('labels: «++مخفي++» on hidden series, «فارغة» exactly where the series page would list nothing', function () {
    $html = $this->withSession(H::session(H::admin(['khotab.editseries'])))->get($this->url)->assertOk()->getContent();
    $empty = '<span class="label label-warning w2a-empty-series-marker">فارغة</span>';
    $badge = fn (string $k) => f03bBadge($html, $this->s[$k]);

    expect($badge('normal'))->toBe('2 مادة')
        ->and($badge('hidden'))->toBe('3 مادة ++مخفي++')
        ->and($badge('second'))->toBe('1 مادة')
        ->and($badge('copy'))->toBe('5 مادة '.$empty)            // counted, but no item: empty
        ->and($badge('hiddenItems'))->toBe('2 مادة')              // only hidden items — an admin sees them on the series page
        ->and($badge('broken'))->toBe('-15 مادة')                 // F03-A: stored −15 but 4 own items → NOT empty, counter shown as stored
        ->and($badge('foreign'))->toBe('0 مادة '.$empty)          // another author's item is not listed by the series page
        ->and($badge('empty'))->toBe('0 مادة '.$empty)
        ->and(substr_count($html, $empty))->toBe(3);
});

it('the «فارغة» definition matches what the admin then sees on each series page', function () {
    $session = H::session(H::admin(['khotab.editseries']));

    foreach (['copy' => true, 'foreign' => true, 'empty' => true, 'broken' => false, 'hiddenItems' => false, 'normal' => false] as $key => $empty) {
        app()->forgetScopedInstances();
        $page = $this->withSession($session)->get("/khotab-series-{$this->s[$key]}.htm")->assertOk()->getContent();
        expect(f03bItemLinks($page) === [])->toBe($empty, $key);
    }
});

it('never changes the stored counters (no silent repair)', function () {
    $before = DB::connection('main')->table('nuke_islamic_series')->orderBy('id')->pluck('count', 'id')->all();

    $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_series')->orderBy('id')->pluck('count', 'id')->all())->toBe($before);
});

// --- 8. Pagination ---------------------------------------------------------------------------------------------------

it('pagination is unchanged: the series list has none, and the group\'s item list is identical for visitor and admin', function () {
    H::insert('nuke_islamic_khotab', ['author' => 7, 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0, 'hidden' => 0, 'title' => 'مادة مباشرة']);
    H::insert('nuke_islamic_khotab', ['author' => 7, 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0, 'hidden' => 1, 'title' => 'مادة مباشرة مخفية']);

    $visitor = $this->get($this->url)->getContent();
    f03bNextRequest();
    $admin = $this->withSession(H::session(H::admin(['khotab.editseries'])))->get($this->url)->getContent();

    expect(f03bItemLinks($admin))->toBe(f03bItemLinks($visitor))->and(f03bItemLinks($visitor))->not->toBe([])
        ->and($admin)->not->toContain('مادة مباشرة مخفية')
        ->and($visitor)->not->toContain('pagination')->and($admin)->not->toContain('pagination');
});

// --- 10. Cache isolation ---------------------------------------------------------------------------------------------

it('the admin response is private (no-store); the visitor response before and after it is unchanged', function () {
    $first = $this->get($this->url);
    $publicHeader = (string) $first->headers->get('Cache-Control');

    f03bNextRequest();
    $admin = $this->withSession(H::session(H::admin(['khotab.editseries'])))->get($this->url);
    expect((string) $admin->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    f03bNextRequest();
    $after = $this->get($this->url);
    expect((string) $after->headers->get('Cache-Control'))->toBe($publicHeader)
        ->and(f03bOrder($after->getContent()))->toBe(f03bIds(F03B_PUBLIC))
        ->and($after->getContent())->not->toContain('++مخفي++')->not->toContain('w2a-empty-series-marker');
});

// --- 11 / 14. No exposure elsewhere; series pages unchanged -----------------------------------------------------------

it('the hidden series stays unreachable for visitors; series pages behave as before', function () {
    $this->get("/khotab-series-{$this->s['hidden']}.htm")->assertNotFound();

    // Visitor: a visible series lists its visible items only (unchanged).
    $visitor = $this->get("/khotab-series-{$this->s['hiddenItems']}.htm")->assertOk()->getContent();
    expect(f03bItemLinks($visitor))->toBe([]);

    // Admin: hidden series and hidden items reachable, as before F03-B.
    f03bNextRequest();
    $session = H::session(H::admin(['khotab.editseries']));
    $this->withSession($session)->get("/khotab-series-{$this->s['hidden']}.htm")->assertOk();
    expect(f03bItemLinks($this->withSession($session)->get("/khotab-series-{$this->s['hiddenItems']}.htm")->getContent()))->toHaveCount(2);
});

// --- 12. Existing admin controls ---------------------------------------------------------------------------------------

it('keeps the existing per-series admin control on every row, including hidden and empty ones (super-admin)', function () {
    $html = $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk()->getContent();

    foreach (F03B_ADMIN as $key) {
        expect($html)->toContain(route('admin.khotab.series.detach', ['series' => $this->s[$key]]));
    }
});

it('the control stays gated by its own permission: khotab.editseries alone shows the series but not «إخراج»', function () {
    $html = $this->withSession(H::session(H::admin(['khotab.editseries'])))->get($this->url)->assertOk()->getContent();

    expect(f03bOrder($html))->toBe(f03bIds(F03B_ADMIN))
        ->and($html)->not->toContain(route('admin.khotab.series.detach', ['series' => $this->s['empty']]));
});

// --- Performance: set-based ------------------------------------------------------------------------------------------

it('the admin list costs two series-list queries in total, whatever the number of series (no N+1)', function () {
    foreach (range(1, 20) as $n) {
        H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'vedio' => 1, 'hidden' => 0, 'title' => 's'.$n, 'count' => 0, 'lastupdate' => 0]);
    }
    $session = H::session(H::admin(['khotab.editseries']));
    $queries = [];
    DB::connection('main')->listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $this->withSession($session)->get($this->url)->assertOk();

    $seriesQueries = array_filter($queries, fn (string $sql) => str_contains($sql, 'from "nuke_islamic_series" as "ser"') && str_contains($sql, '"ser"."group_id" = ?'));
    $aggregates = array_filter($queries, fn (string $sql) => str_contains($sql, 'COUNT(*) AS n') && str_contains($sql, 'nuke_islamic_khotab'));
    expect($seriesQueries)->toHaveCount(1)->and($aggregates)->toHaveCount(1);
});
