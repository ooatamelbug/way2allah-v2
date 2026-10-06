<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-06 (test-hardening pass) — protects `CategorySeriesController`
 * (`category-series-{ser}-{cat}.htm`) against regression. No application
 * behavior is changed by this file — every assertion targets behavior
 * already documented in IF-039.
 */
function useInMemoryMainConnectionForCategorySeries(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategorySeries();
});

it('renders linked items in the searchable premium media grid', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Series Lesson', 'ser_id' => 9, 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('Series Lesson')
        ->toContain('A Series')
        ->toContain('class="w2a-cat-items-wrap"')
        ->toContain('id="w2a_cat_items_search_input"');
});

it('404s for a nonexistent series or a nonexistent category', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    $this->get('/category-series-99999-11.htm')->assertNotFound();

    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'A Series']);
    $this->get('/category-series-9-99999.htm')->assertNotFound();
});

it('empty case: no matching items renders no listing/no per-series-category breadcrumb block, but the sidebar still renders', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Empty Series', 'cat' => '|11|']);

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)->not->toContain('id="cats-breadtcrumb"')
        ->toContain('اخترنا لك هذه المادة'); // sidebar heading always renders
});

// ---- IF-039 quirk 1: main breadcrumb is the INVERSE of the tree pages' (IF-037) ----

it('IF-039: main breadcrumb links every category ancestor, with the final "سلسلة {title}" item left unlinked — opposite of the tree pages\' pattern', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 11, 'title' => 'Fiqh', 'main_cat' => 1],
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series']);

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)->toContain('<a href="/category-1.htm">Root</a>')
        ->toContain('<a href="/category-11.htm">Fiqh</a>')
        ->toContain('<li>سلسلة My Series</li>')
        ->not->toContain('<a href="/category-9.htm">سلسلة My Series</a>');
});

// ---- IF-039 quirk 2: per-series-category breadcrumbs are inverted AGAIN (ancestors unlinked, leaf linked) ----

it('IF-039: per-series-category breadcrumb trails link only the leaf category, leaving ancestors as plain text — the inverse of the main breadcrumb above', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 11, 'title' => 'Fiqh', 'main_cat' => 1],
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series', 'cat' => '|11|']);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Lesson', 'ser_id' => 9, 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)->toContain('id="cats-breadtcrumb"')
        // Ancestor "Root" is plain text (no <a> around it) within the per-series-category block...
        ->toContain('<li>Root<i class="fa fa-angle-right"></i></li>')
        // ...while the leaf "Fiqh" IS linked.
        ->toContain('<a href="/category-11.htm">Fiqh</a>');
});

// ---- IF-039 quirk 3: sidebar omits the hidden=0 filter its category.php sibling has ----

it('IF-039: sidebar (most-downloaded/most-recent) includes hidden items, unlike CategoryController::show()\'s own sidebar', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'A Series']);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Hidden Sidebar Item', 'vedio' => 1, 'hidden' => 1, 'hits' => 999,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $this->get('/category-series-9-11.htm')->assertOk()->assertSee('Hidden Sidebar Item');
});

it('renders the redesigned series downloads and discovery cards with useful metadata', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'A Series']);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1,
        'author' => 1,
        'title' => 'Popular Recent Lesson',
        'vedio' => 1,
        'frame' => 1,
        'hits' => 321,
        'time' => 1_700_000_000,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('/assets/frontend/layout/css/category-series.css')
        ->toContain('class="w2a-series-download-panel"')
        ->toContain('href="/khotab-series-9-11.grx"')
        ->toContain('href="/khotab-series-9.grx"')
        ->toContain('class="portlet box blue w2a-series-list-widget"')
        ->toContain('class="media w2a-top-item"')
        ->toContain('class="media-object w2a-top-item-thumb"')
        ->toContain('321 تحميل')
        ->toContain('fa-clock-o')
        ->not->toContain('<ul class="news">');
});

// ---- O-3: breadcrumb-trail resolution batched (see
// CategoryBreadcrumbTrailsForIdsTest for the resolver's own parity suite) ----

/** Shared fixture: root(1) <- mid(2) <- leafA(11), leafB(12); plus root(9). */
function seedSeriesTrailTree(string $cat): void
{
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 2, 'title' => 'Mid', 'main_cat' => 1],
        ['id' => 11, 'title' => 'Fiqh', 'main_cat' => 2],
        ['id' => 12, 'title' => 'Tafsir', 'main_cat' => 2],
        ['id' => 9, 'title' => 'Standalone', 'main_cat' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series', 'cat' => $cat]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Lesson', 'ser_id' => 9, 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);
}

it('O-3: renders one trail per pipe id, ancestors-first, leaf linked', function () {
    seedSeriesTrailTree('|11|12|9|');

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/category-11.htm">Fiqh</a>')
        ->toContain('<a href="/category-12.htm">Tafsir</a>')
        ->toContain('<a href="/category-9.htm">Standalone</a>')
        ->toContain('<li>Root<i class="fa fa-angle-right"></i></li>')
        ->toContain('<li>Mid<i class="fa fa-angle-right"></i></li>');
});

/**
 * The per-series-category trails live in `#cats-breadtcrumb`, closed before
 * the `<aside>` sidebar. Slicing to that block matters: the MAIN breadcrumb
 * above it renders the URL category's own link, so a whole-page assertion
 * would count that occurrence too.
 */
function seriesTrailsBlock(string $content): string
{
    $start = strpos($content, 'id="cats-breadtcrumb"');
    expect($start)->not->toBeFalse();

    $end = strpos($content, '<aside', $start);

    return substr($content, $start, $end === false ? null : $end - $start);
}

it('O-3: a repeated pipe id renders a repeated trail', function () {
    seedSeriesTrailTree('|11|11|');

    $block = seriesTrailsBlock($this->get('/category-series-9-11.htm')->assertOk()->getContent());

    // Two occurrences in, two rendered leaf links out, inside the trails block.
    expect(substr_count($block, '<a href="/category-11.htm">Fiqh</a>'))->toBe(2);
});

it('O-3: pipe order drives render order, not database order', function () {
    seedSeriesTrailTree('|12|11|');

    $block = seriesTrailsBlock($this->get('/category-series-9-11.htm')->assertOk()->getContent());

    // 12 is inserted after 11, so database order would render Fiqh first.
    expect(strpos($block, '/category-12.htm'))
        ->toBeLessThan(strpos($block, '/category-11.htm'));
});

it('O-3: a pipe id with no category row is silently dropped', function () {
    seedSeriesTrailTree('|11|77777|9|');

    $content = $this->get('/category-series-9-11.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/category-11.htm">Fiqh</a>')
        ->toContain('<a href="/category-9.htm">Standalone</a>')
        ->not->toContain('/category-77777.htm');
});

it('O-3: request query count does not grow with the number of pipe ids', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1000, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 1001, 'title' => 'Mid', 'main_cat' => 1000],
    ]);
    $leaves = [];
    for ($id = 1; $id <= 40; $id++) {
        $leaves[] = ['id' => $id, 'title' => 'Leaf '.$id, 'main_cat' => 1001];
    }
    DB::connection('main')->table('nuke_w2a_cat')->insert($leaves);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Lesson', 'ser_id' => 9, 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 1]);

    $countFor = function (string $cat): int {
        DB::connection('main')->table('nuke_islamic_series')->updateOrInsert(
            ['id' => 9], ['title' => 'My Series', 'cat' => $cat]
        );

        // Both measurements must start cold. C-1 caches the two category-scoped
        // sidebar queries for 300s, so without this the second request would be
        // 2 queries cheaper for a reason that has nothing to do with N — the
        // property under test here is only that the count is independent of the
        // number of pipe ids.
        Cache::flush();

        $n = 0;
        DB::connection('main')->listen(function () use (&$n) {
            $n++;
        });

        $this->get('/category-series-9-1.htm')->assertOk();

        return $n;
    };

    $narrow = $countFor(implode('|', range(1, 4)));
    $wide = $countFor(implode('|', range(1, 40)));

    // 10x the ids, same depth: the whole-request count must not grow with N.
    // Asserted as a relationship, not a fixed number, so unrelated framework
    // queries cannot make this brittle.
    expect($wide)->toBe($narrow);
});

// ---- C-1: the sidebar pair is category-scoped, so series pages under one
// category share the cached entries (ContentSidebarWidgetCacheTest covers the
// widget itself; this proves the sharing end-to-end through the route). ----

it('C-1: two different series under the same category share the cached sidebar queries', function () {
    Cache::flush();

    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 11, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 9, 'title' => 'Series Nine', 'vedio' => 1, 'hidden' => 0],
        ['id' => 10, 'title' => 'Series Ten', 'vedio' => 1, 'hidden' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Lesson Nine', 'ser_id' => 9, 'vedio' => 1, 'hidden' => 0, 'hits' => 50, 'time' => 100],
        ['id' => 2, 'author' => 1, 'title' => 'Lesson Ten', 'ser_id' => 10, 'vedio' => 1, 'hidden' => 0, 'hits' => 90, 'time' => 200],
    ]);
    DB::connection('main')->table('khotab_category_index')->insert([
        ['khotab_id' => 1, 'category_id' => 11],
        ['khotab_id' => 2, 'category_id' => 11],
    ]);

    $count = function (string $url): int {
        $n = 0;
        DB::connection('main')->listen(function () use (&$n) {
            $n++;
        });
        $this->get($url)->assertOk();

        return $n;
    };

    $first = $count('/category-series-9-11.htm');    // cold: fills both entries
    $second = $count('/category-series-10-11.htm');  // different series, same category

    // The second page issues strictly fewer queries: the two category-scoped
    // sidebar queries are served from the entries the first page filled.
    expect($second)->toBeLessThan($first)
        ->and($first - $second)->toBe(2);
});
