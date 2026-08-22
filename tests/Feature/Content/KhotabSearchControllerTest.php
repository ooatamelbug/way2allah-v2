<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabSearch(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabSearch();
});

it('index: with no criteria, renders the form but no results', function () {
    $response = $this->get('/khotab/search');

    $response->assertOk()->assertSee('البحث المتقدم في المرئيات');
});

it('index: IF-023 fix — the page title reflects the search page itself, not a blank/undefined author', function () {
    $content = $this->get('/khotab/search')->assertOk()->getContent();

    expect($content)->toContain('<title>البحث المتقدم في المرئيات');
});

it('index: title search returns matching series and items', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'title' => 'Ramadan Lessons', 'vedio' => 1, 'hidden' => 0, 'count' => 5,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Ramadan Khotbah', 'vedio' => 1, 'hidden' => 0,
    ]);

    // Legacy-Source Reconstruction: search.php's title_sub() wraps the
    // matched search term in <sub class="red_sub">, so the plain
    // "Ramadan Lessons"/"Ramadan Khotbah" substrings no longer appear
    // contiguously — asserted as highlighted fragments instead
    // (LegacySearchRendering::highlight(), already source-proven/tested
    // elsewhere).
    $content = $this->get('/khotab/search?title=Ramadan')->assertOk()->getContent();

    expect($content)
        ->toContain('<sub class="red_sub">Ramadan</sub> Lessons')
        ->toContain('<sub class="red_sub">Ramadan</sub> Khotbah');
});

it('index: IF-024 fix — a channel-only search (no title) returns results instead of being rejected', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Channel Nine']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'channel_id' => 9, 'title' => 'Channel Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $response = $this->get('/khotab/search?channel=9');

    $response->assertOk()->assertSee('Channel Item')->assertDontSee('أربعة أحرف');
});

it('index: a title under 4 characters is rejected with a validation message', function () {
    $response = $this->get('/khotab/search?title=ab');

    $response->assertOk()->assertSee('أربعة أحرف');
});

it('index: IF-018 fix — an item search result\'s author link uses the correct author id', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 7, 'title' => 'Findable Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->toContain('khotab-video-7.htm');
});

// ---- Legacy-Source Reconstruction: search.php's page chrome, form portlet, and result-table markup ----

it('index: renders no <h3 class="page-title"> (LEGACY_BUG_NOT_FOR_REPRODUCTION for the confirmed $Author-null empty heading) but does render the real breadcrumb', function () {
    $content = $this->get('/khotab/search')->assertOk()->getContent();

    expect($content)->not->toContain('page-title');
    expect($content)->toContain('<li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/video-advanced-search.htm">البحث المتقدم في المرئيات</a><i class=""></i></li>');
});

it('index: the search form is wrapped in a real portlet (fa-child icon) with the exact Bootstrap form-horizontal/form-group structure, not a bare label/input list', function () {
    $content = $this->get('/khotab/search')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> البحث المتقدم في المرئيات</div>')
        ->toContain('<form class="form-horizontal" method="get" action="">')
        ->toContain('class="form-control datepikerinput" id="from" name="from"')
        ->toContain('class="form-control datepikerinput" id="to" name="to"')
        ->toContain('class="btn btn-primary" id="kh_search"');
});

it('index: loads the bootstrap-datepicker assets that the .datepikerinput fields actually target', function () {
    $content = $this->get('/khotab/search')->assertOk()->getContent();

    expect($content)
        ->toContain('/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')
        ->toContain('/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.js')
        // legacy's own AJAX-fragment functions have no caller in this GET-based architecture — not ported.
        ->not->toContain('advanced_search_khotab(p)')
        ->not->toContain('advanced_search_series(p)');
});

it('index: results render as table#tabelgrp rows (not bare <div>s), with the "الكل" total-count summary row', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Findable', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tabelgrp">')
        ->toContain('<th scope="row">الكل</th><td colspan="2">1 مادة</td>');
});

it('index: series channel badge links with the real, already-correct author_id (series query already selects it)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 3, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Chan']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 3, 'channel_id' => 9, 'title' => 'Findable Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1,
    ]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->toContain('<a href="/channel-9-3.htm">')
        ->toContain('/images/channels/9.png');
});

it('index: item channel badge applies the already-established IF-018 fix ($item->author, not the confirmed-undefined author_id) consistently', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 4, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 11, 'title' => 'Chan']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 4, 'channel_id' => 11, 'title' => 'Findable Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->toContain('<a href="/channel-11-4.htm">');
});

it('index: no channel badge at all when channel_id is 0, for either results table', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'channel_id' => 0, 'title' => 'Findable', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->not->toContain('fa-television');
});

it('index: empty state shows the exact red, inline-styled legacy message, positioned before the (empty) results table', function () {
    $content = $this->get('/khotab/search?channel=999')->assertOk()->getContent();

    expect($content)->toContain('style="color:RED; font-weight:bold; font-size:15px; margin:auto; width:50%"');

    $redPos = strpos($content, 'لا يوجد مواد تطابق نتائج البحث');
    $tablePos = strpos($content, '<table class="table table-striped table-hover" id="tabelgrp">', strpos($content, 'قائمة المواد'));
    expect($redPos)->toBeLessThan($tablePos);
});

it('index: hidden items and series are excluded from results', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Secret Item', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab/search?title=Secret')->assertOk()->assertDontSee('Secret Item');
});

// ---- G-09-01: video-advanced-search.htm (the real, nav-linked pretty URL) ----

it('G-09-01: GET /video-advanced-search.htm resolves via the actual HTTP route, reaches KhotabSearchController, and renders the same page as /khotab/search', function () {
    $response = $this->get('/video-advanced-search.htm');

    $response->assertOk()
        ->assertSee('البحث المتقدم في المرئيات')
        ->assertViewIs('khotab.search');
});

it('G-09-01: search behavior on video-advanced-search.htm is identical to /khotab/search — same filters, same results', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Ramadan Khotbah', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/video-advanced-search.htm?title=Ramadan')->assertOk()->getContent();

    // Highlighted, per title_sub()/LegacySearchRendering::highlight() — see the sibling /khotab/search test.
    expect($content)->toContain('<sub class="red_sub">Ramadan</sub> Khotbah');
});

// ---- G-09-02: concurrent/repeated cold-cache access must not 500 ----

/**
 * Phase 1 diagnosed this as a multi-process concurrent-write race; Phase 2
 * re-investigation (reproducing before fixing, per instruction) found the
 * real trigger needs no concurrency at all — a single already-bootstrapped
 * PHP process re-`unserialize()`-ing its own just-written, byte-valid
 * cache file of full Eloquent models. This test reproduces that exact
 * shape: the SAME test process (Pest runs one test in one PHP process)
 * issues multiple real HTTP requests against the real `file` cache driver
 * — cold, then warm, then warm again — the precise sequence that produced
 * HTTP 500 before the fix. `CACHE_STORE` is overridden locally to this
 * test only (`phpunit.xml`'s suite-wide `array` driver cannot exhibit
 * this bug at all) and the written cache file is removed afterward.
 */
it('G-09-02: repeated requests against a cold-then-warm file-driver cache never 500, on both routes', function () {
    config(['cache.default' => 'file']);
    Cache::forget('khotab-search-authors-menu');
    Cache::forget('khotab-search-channels-menu');

    $authors = [];
    for ($i = 1; $i <= 60; $i++) {
        $authors[] = ['id' => $i, 'name' => "Author $i", 'prename' => 'Sheikh'];
    }
    DB::connection('main')->table('nuke_islamic_authors')->insert($authors);

    $channels = [];
    for ($i = 1; $i <= 30; $i++) {
        $channels[] = ['id' => $i, 'title' => "Channel $i"];
    }
    DB::connection('main')->table('nuke_sat_channels')->insert($channels);

    try {
        // Request 1: cold cache, populates it.
        $this->get('/khotab/search')->assertOk()->assertDontSee('Attempt to read property');
        // Requests 2-4: warm-cache reads, same process — the exact
        // sequence that reproduced the pre-fix 500 with zero concurrency.
        $this->get('/khotab/search')->assertOk()->assertDontSee('Attempt to read property');
        $this->get('/video-advanced-search.htm')->assertOk()->assertDontSee('Attempt to read property');
        $this->get('/khotab/search?title=Author')->assertOk()->assertDontSee('Attempt to read property');
    } finally {
        Cache::forget('khotab-search-authors-menu');
        Cache::forget('khotab-search-channels-menu');
    }
});
