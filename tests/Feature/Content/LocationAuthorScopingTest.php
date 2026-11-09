<?php

/*
 * Locations family — location-author scoping fix (owner-approved, 2026-11-07; decision-log #114).
 * `location-{l}-author-{a}.htm` is scoped to location `l` (OLD Locations `Author($id, $location)`); the chat room's
 * `chat_author_{a}.htm` keeps location 10. Isolated in-memory fixtures only.
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
});

/**
 * Author 7 is registered at BOTH location 10 (the chat room) and location 5, with distinct content at each:
 * a group, a series, a standalone item and a hidden item per location. Author 8 is registered at location 5 only.
 */
function lasSeed(): void
{
    $db = DB::connection('main');
    $db->table('nuke_islamic_locations')->insert([
        ['id' => 10, 'title' => 'غرفة الهداية', 'hidden' => 0, 'type' => 2],
        ['id' => 5, 'title' => 'مسجد الموقع الخامس', 'hidden' => 0, 'type' => 1],
    ]);
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 7, 'name' => 'Both Places', 'prename' => 'Sheikh', 'hidden' => 0],
        ['id' => 8, 'name' => 'Only Five', 'prename' => 'Sheikh', 'hidden' => 0],
        ['id' => 9, 'name' => 'Hidden Author', 'prename' => 'Sheikh', 'hidden' => 1],
    ]);
    $db->table('nuke_islamic_authors_location')->insert([
        ['author_id' => 7, 'location_id' => 10, 'count' => 2],
        ['author_id' => 7, 'location_id' => 5, 'count' => 2],
        ['author_id' => 8, 'location_id' => 5, 'count' => 1],
        ['author_id' => 9, 'location_id' => 5, 'count' => 1],
    ]);
    $db->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'author_id' => 7, 'title' => 'Group At Ten', 'hidden' => 0],
        ['id' => 2, 'author_id' => 7, 'title' => 'Group At Five', 'hidden' => 0],
    ]);
    $db->table('nuke_islamic_groups_location')->insert([
        ['group_id' => 1, 'location_id' => 10, 'count' => 1],
        ['group_id' => 2, 'location_id' => 5, 'count' => 1],
    ]);
    $db->table('nuke_islamic_series')->insert([
        ['id' => 1, 'author_id' => 7, 'group_id' => 0, 'title' => 'Series At Ten', 'hidden' => 0],
        ['id' => 2, 'author_id' => 7, 'group_id' => 0, 'title' => 'Series At Five', 'hidden' => 0],
    ]);
    $db->table('nuke_islamic_series_location')->insert([
        ['series_id' => 1, 'location_id' => 10, 'count' => 1],
        ['series_id' => 2, 'location_id' => 5, 'count' => 1],
    ]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 101, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 10, 'title' => 'Lesson At Ten', 'hidden' => 0, 'hits' => 5],
        ['id' => 102, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 5, 'title' => 'Lesson At Five', 'hidden' => 0, 'hits' => 5],
        ['id' => 103, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 5, 'title' => 'Hidden Lesson At Five', 'hidden' => 1, 'hits' => 99],
        ['id' => 104, 'author' => 8, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 5, 'title' => 'Only Five Lesson', 'hidden' => 0, 'hits' => 1],
    ]);
}

it('location 10 author works as before — location-10-author shows location 10 content only', function () {
    lasSeed();

    $html = $this->get('/location-10-author-7.htm')->assertOk()->getContent();

    expect($html)->toContain('Group At Ten')->toContain('Series At Ten')->toContain('Lesson At Ten')
        ->not->toContain('Group At Five')->not->toContain('Series At Five')->not->toContain('Lesson At Five');
});

it('chat_author_{id}.htm still uses location 10 (unchanged), including its avatar link', function () {
    lasSeed();

    $html = $this->get('/chat_author_7.htm')->assertOk()->getContent();

    expect($html)->toContain('Lesson At Ten')->not->toContain('Lesson At Five')
        ->toContain('href="/chat_author_7.htm"');
    $this->get('/chat_author_8.htm')->assertNotFound();
});

it('an author linked only to another location loads at that location', function () {
    lasSeed();

    $this->get('/location-5-author-8.htm')->assertOk()->assertSee('Only Five')->assertSee('Only Five Lesson');
});

it('content is scoped to the requested location: groups, series, items and both sidebars', function () {
    lasSeed();

    $html = $this->get('/location-5-author-7.htm')->assertOk()->getContent();

    expect($html)->toContain('Group At Five')->toContain('Series At Five')->toContain('Lesson At Five');
});

it('an author linked to location 10 and another location shows no location 10 content on the other location\'s page', function () {
    lasSeed();

    $html = $this->get('/location-5-author-7.htm')->assertOk()->getContent();

    expect($html)->not->toContain('Group At Ten')->not->toContain('Series At Ten')->not->toContain('Lesson At Ten')
        ->not->toContain('/chat_author_7.htm')->toContain('href="/location-5-author-7.htm"');
});

it('an author not linked to the requested location is a 404', function () {
    lasSeed();

    $this->get('/location-10-author-8.htm')->assertNotFound();
    $this->get('/location-6-author-7.htm')->assertNotFound();
    $this->get('/location-5-author-999.htm')->assertNotFound();
});

it('hidden rules unchanged: a hidden author is a 404, a hidden lesson is not listed or in the sidebars', function () {
    lasSeed();

    $this->get('/location-5-author-9.htm')->assertNotFound();
    $html = $this->get('/location-5-author-7.htm')->assertOk()->getContent();

    expect($html)->not->toContain('Hidden Lesson At Five');
});

it('location pages link each author to the author page of THAT location, and the link resolves', function () {
    lasSeed();

    $html = $this->get('/location-5.htm')->assertOk()->getContent();

    expect($html)->toContain('href="/location-5-author-7.htm"')->toContain('href="/location-5-author-8.htm"')
        ->not->toContain('location-5-author-9.htm');
    $this->get('/location-5-author-7.htm')->assertOk();
    $this->get('/location-5-author-8.htm')->assertOk();
});

// Presentation correction (owner-requested, 2026-11-07): location 10 keeps the chat-room title/breadcrumb; another
// location names itself (from nuke_islamic_locations) and its breadcrumb links to location-{id}.htm.
it('location 10: the title and breadcrumb are unchanged (chat_author and location-10-author)', function () {
    lasSeed();

    foreach (['/chat_author_7.htm', '/location-10-author-7.htm'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toContain('<title>Sheikh Both Places - غرفة الهداية الدعوية - ')
            ->toContain('<h3 class="page-title">غرفة الهداية - Sheikh Both Places</h3>')
            ->toMatch('#'.preg_quote('<li><a href="/chat_room.htm">غرفة الهداية</a><i class="fa fa-angle-right"></i></li>', '#').'\s*'.preg_quote('<li>Sheikh Both Places<i class=""></i></li>', '#').'#u')
            ->not->toContain('مسجد الموقع الخامس')->not->toContain('href="/location-10.htm"');
    }
});

it('another location: the title and breadcrumb show the real location name and link to location-{id}.htm', function () {
    lasSeed();

    $html = $this->get('/location-5-author-7.htm')->assertOk()->getContent();

    expect($html)->toContain('<title>Sheikh Both Places - مسجد الموقع الخامس - ')
        ->toContain('<h3 class="page-title">مسجد الموقع الخامس - Sheikh Both Places</h3>')
        ->toMatch('#'.preg_quote('<li><a href="/location-5.htm">مسجد الموقع الخامس</a><i class="fa fa-angle-right"></i></li>', '#').'\s*'.preg_quote('<li>Sheikh Both Places<i class=""></i></li>', '#').'#u')
        ->not->toContain('غرفة الهداية الدعوية')->not->toContain('<a href="/chat_room.htm">غرفة الهداية</a><i');

    $other = $this->get('/location-5-author-8.htm')->assertOk()->getContent();
    expect($other)->toContain('<title>Sheikh Only Five - مسجد الموقع الخامس - ')
        ->toContain('<h3 class="page-title">مسجد الموقع الخامس - Sheikh Only Five</h3>')
        ->toMatch('#'.preg_quote('<li><a href="/location-5.htm">مسجد الموقع الخامس</a><i class="fa fa-angle-right"></i></li>', '#').'\s*'.preg_quote('<li>Sheikh Only Five<i class=""></i></li>', '#').'#u');
});

it('another location: the location name is escaped', function () {
    lasSeed();
    DB::connection('main')->table('nuke_islamic_locations')->insert(['id' => 6, 'title' => 'Masjid <b>&</b>', 'hidden' => 0, 'type' => 1]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 8, 'location_id' => 6, 'count' => 1]);

    $html = $this->get('/location-6-author-8.htm')->assertOk()->getContent();

    expect($html)->toContain('Masjid &lt;b&gt;&amp;&lt;/b&gt;')->not->toContain('Masjid <b>');
});

// Owner decision 2026-11-07 (missing-location edge case): a nonexistent location is a 404, orphaned links notwithstanding.
it('a nonexistent location is a 404, and an orphaned author-location link row does not bypass the existence check', function () {
    lasSeed();
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 8, 'location_id' => 77, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(
        ['id' => 105, 'author' => 8, 'ser_id' => 0, 'group_id' => 0, 'location_id' => 77, 'title' => 'Orphan Lesson', 'hidden' => 0, 'hits' => 1],
    );

    $this->get('/location-77-author-8.htm')->assertNotFound();   // orphan link row, no location row
    $this->get('/location-78-author-8.htm')->assertNotFound();   // no link row, no location row
    expect(DB::connection('main')->table('nuke_islamic_authors_location')->where('location_id', 77)->count())->toBe(1);
});

it('status matrix: existing+linked 200, existing+unlinked 404, missing 404; location 10 workflows unchanged', function () {
    lasSeed();

    $this->get('/location-5-author-8.htm')->assertOk();
    $this->get('/location-5-author-7.htm')->assertOk();
    $this->get('/location-10-author-8.htm')->assertNotFound();
    $this->get('/location-99-author-7.htm')->assertNotFound();
    $this->get('/location-10-author-7.htm')->assertOk()->assertSee('Lesson At Ten');
    $this->get('/chat_author_7.htm')->assertOk()->assertSee('Lesson At Ten');
    $this->get('/chat_author_8.htm')->assertNotFound();
    $this->get('/location-5-author-9.htm')->assertNotFound();      // hidden author, existing location
});

// Owner-approved targeted UI correction (2026-11-07): shared page chrome, premium panels, Khotab author card,
// empty groups/series hidden, items empty state, full-width location author table.
it('author page: the avatar uses the Khotab author card and keeps its location-aware link', function () {
    lasSeed();

    $chat = $this->get('/chat_author_7.htm')->assertOk()->getContent();
    $five = $this->get('/location-5-author-7.htm')->assertOk()->getContent();

    expect($chat)->toMatch('#<section class="w2a-refresh-panel w2a-author-profile-card" id="author-info">\s*<a href="/chat_author_7.htm"><img class="w2a-author-avatar" src="[^"]+" alt="Sheikh Both Places"></a>#u')
        ->and($five)->toMatch('#id="author-info">\s*<a href="/location-5-author-7.htm"><img class="w2a-author-avatar" #u')
        ->and($five)->not->toContain('href="/chat_author_7.htm"');
});

it('author page: groups, series and items sit in titled panels; empty groups/series are hidden, items always shown', function () {
    lasSeed();

    $full = $this->get('/location-5-author-7.htm')->assertOk()->getContent();
    expect($full)->toContain('<h2>قائمة المجموعات</h2>')->toContain('<h2>قائمة السلاسل</h2>')->toContain('<h2>قائمة المواد</h2>')
        ->toContain('<h2>أكثر الدروس مشاهدة</h2>')->toContain('<h2>أجدد التسجيلات</h2>')
        ->toContain('href="/khotab-group-2.htm"')->toContain('href="/khotab-series-2.htm"')->toContain('href="/chat_lesson_102.htm"');

    // Author 8 at location 5: no groups, no series, one item.
    $bare = $this->get('/location-5-author-8.htm')->assertOk()->getContent();
    expect($bare)->not->toContain('قائمة المجموعات')->not->toContain('قائمة السلاسل')
        ->toContain('<h2>قائمة المواد</h2>')->toContain('Only Five Lesson')->not->toContain('لا توجد مواد مطابقة');
});

it('author page: no items shows the existing styled empty-state message inside the items panel', function () {
    lasSeed();
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 11, 'name' => 'No Items', 'prename' => 'Sheikh', 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 11, 'location_id' => 5, 'count' => 0]);

    $html = $this->get('/location-5-author-11.htm')->assertOk()->getContent();

    expect($html)->toContain('<h2>قائمة المواد</h2>')->not->toContain('قائمة المجموعات')->not->toContain('قائمة السلاسل')
        ->toMatch('#<div class="w2a-tree-empty w2a-empty-state" role="status">\s*<i class="fa fa-info-circle" aria-hidden="true"></i>\s*<h5>لا توجد مواد مطابقة بقاعدة بيانات الموقع</h5>#u');
});

it('location page: breadcrumb names the location once, no sidebar column, author table full width in a titled panel', function () {
    lasSeed();

    $html = $this->get('/location-5.htm')->assertOk()->getContent();

    expect($html)->toContain('<li>مسجد الموقع الخامس<i class=""></i></li>')
        ->toContain('<h2>مسجد الموقع الخامس</h2>')->toContain('<li>الاسم: مسجد الموقع الخامس</li>')
        ->not->toContain('<h3>مسجد الموقع الخامس</h3>')->not->toContain('class="page-title"')
        ->toContain('<div class="col-xs-12 nopadding flex flex-column gap-5">')->not->toContain('col-lg-9')
        ->toContain('<h2>قائمة الدعاة</h2>')
        ->toMatch('#<div class="table-responsive">\s*<table class="table table-striped table-hover">#u')
        ->toMatch('#<img class="img-responsive center-block" src="[^"]+" width="250" alt="مسجد الموقع الخامس">#u');
    expect(substr_count($html, 'href="/location-5-author-'))->toBe(2);
});
