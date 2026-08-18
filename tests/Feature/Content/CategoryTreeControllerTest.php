<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-06 (test-hardening pass) — protects `CategoryTreeController`'s 3
 * already-implemented, already-verified branches (`categories.htm`,
 * `var-categories.htm`, `fatawa-categories.htm`) against regression. No
 * application behavior is changed by this file — every assertion targets
 * behavior already documented in IF-036/IF-037/IF-038.
 */
function useInMemoryMainConnectionForCategoryTree(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategoryTree();
});

// ---- categories.htm (video_count filter, category- slug) ----

it('categories.htm: lists only categories with video_count > 0, linking to category-{id}.htm', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Has Video', 'main_cat' => 0, 'video_count' => 5],
        ['id' => 2, 'title' => 'No Video', 'main_cat' => 0, 'video_count' => 0],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/category-1.htm"')->toContain('Has Video')
        ->not->toContain('href="/category-2.htm"')->not->toContain('No Video');
});

it('categories.htm: ordered by title ASC then id DESC (categoryTree()\'s own confirmed ordering)', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Zebra', 'main_cat' => 0, 'video_count' => 1],
        ['id' => 2, 'title' => 'Alpha', 'main_cat' => 0, 'video_count' => 1],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect(strpos($content, 'Alpha'))->toBeLessThan(strpos($content, 'Zebra'));
});

it('categories.htm: IF-037 — breadcrumb items render a literal empty href, not an omitted link', function () {
    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect($content)->toContain('<a href="">المرئيات</a>')
        ->toContain('<a href="">التصنيفات الموضوعية</a>');
});

it('categories.htm: IF-037 — each leaf category gets its OWN <ul class="sub_group_list"> wrapper, not one shared wrapper for all siblings', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Top', 'main_cat' => 0, 'video_count' => 1],
        ['id' => 2, 'title' => 'Group', 'main_cat' => 1, 'video_count' => 1],
        ['id' => 3, 'title' => 'Leaf One', 'main_cat' => 2, 'video_count' => 1],
        ['id' => 4, 'title' => 'Leaf Two', 'main_cat' => 2, 'video_count' => 1],
    ]);

    $content = $this->get('/categories.htm')->assertOk()->getContent();

    expect(substr_count($content, 'class="sub_group_list"'))->toBe(2);
});

// ---- var-categories.htm (anasheed_count filter, var-category- slug) ----

it('var-categories.htm: lists only categories with anasheed_count > 0, linking to var-category-{id}.htm', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Has Anasheed', 'main_cat' => 0, 'anasheed_count' => 3],
        ['id' => 2, 'title' => 'No Anasheed', 'main_cat' => 0, 'anasheed_count' => 0],
    ]);

    $content = $this->get('/var-categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/var-category-1.htm"')->toContain('Has Anasheed')
        ->not->toContain('href="/var-category-2.htm"')->not->toContain('No Anasheed');
});

// ---- fatawa-categories.htm (q_count filter, fatawa-category- slug — a confirmed dead link target, IF-038) ----

it('fatawa-categories.htm: lists only categories with q_count > 0, linking to fatawa-category-{id}.htm', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Has Fatawa', 'main_cat' => 0, 'q_count' => 7],
        ['id' => 2, 'title' => 'No Fatawa', 'main_cat' => 0, 'q_count' => 0],
    ]);

    $content = $this->get('/fatawa-categories.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/fatawa-category-1.htm"')->toContain('Has Fatawa')
        ->not->toContain('href="/fatawa-category-2.htm"')->not->toContain('No Fatawa');
});

it('fatawa-categories.htm: IF-038 — the generated fatawa-category-{id}.htm links are confirmed dead (404), preserved as-is, not redirected or invented', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Has Fatawa', 'main_cat' => 0, 'q_count' => 7]);

    $this->get('/fatawa-categories.htm')->assertOk()->assertSee('href="/fatawa-category-1.htm"', false);

    $this->get('/fatawa-category-1.htm')->assertNotFound();
});
