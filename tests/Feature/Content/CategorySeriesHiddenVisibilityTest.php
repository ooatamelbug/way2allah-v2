<?php

/*
 * Admin hidden-content visibility V1 (owner, 2026-11-05; decision-log #93) — `category-series-{ser}-{cat}.htm`
 * follows the Khotab series rule (owner decision 2026-10-21, #55/#56): a hidden series is a 404 for visitors
 * and signed-in members; any signed-in administrator opens it, privately. Listed items keep `hidden = 0`.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::category(11, 'تصنيف الفقه');
    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ', 'prename' => '']);

    // A hidden series whose items are themselves visible (61 such series locally), and a visible one.
    $this->hiddenSeries = H::insert('nuke_islamic_series', ['title' => 'سلسلة مخفية سرية', 'vedio' => 1, 'hidden' => 1, 'count' => 2, 'cat' => '|11|', 'author_id' => 1]);
    $this->visibleSeries = H::insert('nuke_islamic_series', ['title' => 'سلسلة ظاهرة', 'vedio' => 1, 'hidden' => 0, 'count' => 2, 'cat' => '|11|', 'author_id' => 1]);

    foreach ([
        [$this->hiddenSeries, 'درس ظاهر في سلسلة مخفية', 0],
        [$this->visibleSeries, 'درس ظاهر', 0],
        [$this->visibleSeries, 'درس مخفي', 1],
    ] as [$series, $title, $hidden]) {
        $id = H::insert('nuke_islamic_khotab', ['author' => 1, 'title' => $title, 'ser_id' => $series, 'group_id' => 0, 'vedio' => 1, 'hidden' => $hidden]);
        DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => $id, 'category_id' => 11]);
    }

    $this->hiddenUrl = "/category-series-{$this->hiddenSeries}-11.htm";
    $this->visibleUrl = "/category-series-{$this->visibleSeries}-11.htm";
});

/** The page without its sidebar: the series' own item listing. (The category sidebar has no `hidden`
 *  filter — recorded separately in decision-log #93, out of V1's scope.) */
function v1Main(string $html): string
{
    return explode('<aside class="col-lg-3 col-md-4 col-sm-5 nopadding w2a-series-sidebar', $html)[0];
}

function v1Member(): VbUser
{
    return (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
}

it('V1: an anonymous visitor gets a 404 for a hidden series — no title, no items', function () {
    H::visitor();
    $response = $this->get($this->hiddenUrl)->assertNotFound();

    expect($response->getContent())->not->toContain('سلسلة مخفية سرية')->not->toContain('درس ظاهر في سلسلة مخفية');
});

it('V1: a signed-in member (not an administrator) also gets a 404 for a hidden series', function () {
    H::visitor();
    $response = $this->actingAs(v1Member(), 'vbulletin')->get($this->hiddenUrl)->assertNotFound();

    expect($response->getContent())->not->toContain('سلسلة مخفية سرية');
});

it('V1: any signed-in administrator opens a hidden series, privately, with its visible items only', function () {
    foreach ([H::admin([], 'plain-admin'), H::superAdmin('cs-boss')] as $admin) {
        $response = $this->withSession(H::session($admin))->get($this->hiddenUrl)->assertOk();

        expect($response->getContent())->toContain('سلسلة مخفية سرية')->toContain('درس ظاهر في سلسلة مخفية')
            ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    }
});

it('V1: a visible series is unchanged for a visitor — 200, public caching, hidden items still filtered', function () {
    H::visitor();
    $response = $this->get($this->visibleUrl)->assertOk();

    expect(v1Main($response->getContent()))->toContain('سلسلة ظاهرة')->toContain('درس ظاهر')->not->toContain('درس مخفي')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('V1: an administrator on a visible series still sees hidden items filtered (item rule unchanged), privately', function () {
    $response = $this->withSession(H::session(H::admin([], 'plain-admin')))->get($this->visibleUrl)->assertOk();

    expect(v1Main($response->getContent()))->toContain('درس ظاهر')->not->toContain('درس مخفي')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('V1: a nonexistent series or category is still a 404 for everyone', function () {
    H::visitor();
    $this->get('/category-series-99999-11.htm')->assertNotFound();
    $this->get("/category-series-{$this->visibleSeries}-99999.htm")->assertNotFound();

    $session = H::session(H::admin([], 'plain-admin'));
    $this->withSession($session)->get('/category-series-99999-11.htm')->assertNotFound();
    $this->withSession($session)->get("/category-series-{$this->hiddenSeries}-99999.htm")->assertNotFound();
});
