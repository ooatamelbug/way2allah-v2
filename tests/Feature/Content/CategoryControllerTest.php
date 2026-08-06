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
