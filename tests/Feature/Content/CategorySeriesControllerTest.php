<?php

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
