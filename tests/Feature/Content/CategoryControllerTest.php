<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForCategoryController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
        'series_category_index' => MainSchema::seriesCategoryIndex(),
        // G-06 additions — showAnasheed() (var-category-{id}.htm).
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategoryController();
});

it('show: renders items and series linked to the category via the junction tables', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Fiqh Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 5]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'Fiqh Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 5]);

    $response = $this->get('/category-5.htm');

    $response->assertOk()->assertSee('Fiqh Lesson')->assertSee('Fiqh Series');
});

it('show: G-13-12 — series/item rows show a channel icon only when channel_id is set, matching categories/functions.php\'s own ListSeries()/ListKhotab()', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Fiqh Lesson', 'vedio' => 1, 'hidden' => 0, 'channel_id' => 9,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 5]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'Fiqh Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3, 'channel_id' => 0,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
});

it('show: items linked to a different category are excluded', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0],
        ['id' => 6, 'title' => 'Aqeedah', 'main_cat' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Aqeedah Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 6]);

    $this->get('/category-5.htm')->assertOk()->assertDontSee('Aqeedah Lesson');
});

it('show: breadcrumb trail walks main_cat up to the root, ancestors first', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 2, 'title' => 'Branch', 'main_cat' => 1],
        ['id' => 3, 'title' => 'Leaf', 'main_cat' => 2],
    ]);

    $content = $this->get('/category-3.htm')->assertOk()->getContent();

    // "Leaf" (the category being viewed) also appears earlier on the page
    // (<title>, <h1>) than in the breadcrumb nav itself, so the position
    // check is scoped to the nav element — a page-wide strpos comparison
    // would find the wrong (earlier) "Leaf" occurrence.
    preg_match('/<nav aria-label="التصنيفات الموضوعية">(.*?)<\/nav>/s', $content, $matches);
    $nav = $matches[1] ?? '';

    expect(strpos($nav, 'Root'))->toBeLessThan(strpos($nav, 'Branch'));
    expect(strpos($nav, 'Branch'))->toBeLessThan(strpos($nav, 'Leaf'));
});

it('show: 404s for a nonexistent category', function () {
    $this->get('/category-999.htm')->assertNotFound();
});

// Roadmap task 4.2 amendment (added post-Wave-4 — see
// docs/reviews/gap-closure-action-plan.md item 1). vars_categories/ is a
// confirmed superseded duplicate of categories/ — same category-id space
// — so its live route redirects here rather than getting its own
// controller.
it('IF-031 fix: vars-category-{id}.htm redirects to the equivalent category-{id}.htm, same id preserved', function () {
    $this->get('/vars-category-42.htm')->assertRedirect('/category-42.htm');
});

// ---- G-06 additions: the 2 remaining vars_categories redirects (IF-043) ----

it('IF-043 fix: vars-categories.htm redirects to categories.htm', function () {
    $this->get('/vars-categories.htm')->assertRedirect('/categories.htm');
});

it('IF-043 fix: vars-category-series-{id}-{id2}.htm redirects to category-series-{id}-{id2}.htm, same ids preserved', function () {
    $this->get('/vars-category-series-9-11.htm')->assertRedirect('/category-series-9-11.htm');
});

// ---- G-06 additions: CategoryController::showAnasheed() (var-category-{id}.htm) ----

it('showAnasheed: renders anasheed items linked to the category via the pipe-delimited cat_id column', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Anasheed In Category', 'cat_id' => '|7|', 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_anasheed_advanced')->insert(['id' => 1, 'adur' => '90000']);

    $this->get('/var-category-7.htm')->assertOk()->assertSee('Anasheed In Category');
});

it('showAnasheed: items linked to a different category are excluded (LIKE match is exact-id-scoped, not a prefix match)', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0],
        ['id' => 70, 'title' => 'Other', 'main_cat' => 0],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Wrong Category Item', 'cat_id' => '|70|', 'hidden' => 0,
    ]);

    $this->get('/var-category-7.htm')->assertOk()->assertDontSee('Wrong Category Item');
});

it('IF-036: showAnasheed() shows a KHOTAB sidebar (random featured/most downloaded/most recent) alongside the anasheed listing — confirmed intentional-as-written, not a porting error', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Khotab Sidebar Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 7]);

    // The sidebar widgets are scoped to the SAME category id as the anasheed
    // listing (khotab_category_index, not the anasheed cat_id column) —
    // this is legacy's own real behavior (IF-036), reproduced exactly.
    $this->get('/var-category-7.htm')->assertOk()->assertSee('Khotab Sidebar Item');
});

it('showAnasheed: 404s for a nonexistent category', function () {
    $this->get('/var-category-999.htm')->assertNotFound();
});
