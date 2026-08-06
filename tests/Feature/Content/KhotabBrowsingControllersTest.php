<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabBrowsing(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabBrowsing();
});

// ---- IF-015: series.php's "Most Downloaded" sidebar now always uses the series' own author ----

it('series show: IF-015 fix — an UNGROUPED series still shows its author\'s "Most Downloaded" items, not an empty result', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'ser_id' => 10, 'group_id' => 0, 'title' => 'Series Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 0],
        ['id' => 2, 'author' => 1, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Author Top Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 50],
    ]);

    $response = $this->get('/khotab-series-10.htm');

    $response->assertOk()->assertSee('Author Top Item');
});

it('series show: 404s for a hidden series', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'Hidden', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab-series-10.htm')->assertNotFound();
});

// ---- group.php (no bug — sanity check it still renders) ----

it('group show: renders series and items scoped to the group', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'group_id' => 20, 'title' => 'Group Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $this->get('/khotab-group-20.htm')->assertOk()->assertSee('Group Item');
});

// ---- IF-016 + IF-022: day.php ----

it('day: IF-016 fix — the page title reflects the browsed date, not a blank/undefined author', function () {
    $response = $this->get('/khotab-video-today.htm');

    $response->assertOk()->assertSee('المواد المنشورة بتاريخ');
});

it('day: IF-022 fix — a dated URL scopes the main list to that date\'s items, not today\'s', function () {
    // Sidebar "Most Downloaded"/"Newest" boxes are global (unscoped by
    // date), matching legacy exactly — so this asserts within the
    // "قائمة المواد" list section specifically, not the full page, since a
    // page-wide assertDontSee would be defeated by the (correctly)
    // date-unscoped sidebar showing every vedio=1 item regardless.
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);

    $oldDay = mktime(0, 0, 0, 1, 15, 2020);
    $today = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Old Day Item', 'vedio' => 1, 'hidden' => 0, 'time' => $oldDay + 100],
        ['id' => 2, 'author' => 1, 'title' => 'Today Item', 'vedio' => 1, 'hidden' => 0, 'time' => $today + 100],
    ]);

    $content = $this->get('/khotab-videodate-15-1-2020.htm')->assertOk()->getContent();

    preg_match('/قائمة المواد">(.*?)<\/section>/s', $content, $matches);
    $listSection = $matches[1] ?? '';

    expect($listSection)->toContain('Old Day Item');
    expect($listSection)->not->toContain('Today Item');
});

// ---- IF-017 + IF-021: news.php / author.php pdf-op sidebars ----

it('news: IF-017 fix — the pdf op\'s "Most Downloaded" sidebar is scoped to pdf content, not coerced to audio', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 5],
        ['id' => 2, 'author' => 1, 'title' => 'Unrelated Audio Item', 'vedio' => 0, 'pdf' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-pdf_news.htm');

    $response->assertOk()->assertSee('PDF Item')->assertDontSee('Unrelated Audio Item');
});

it('author show: IF-021 fix — the pdf op\'s sidebar is scoped to this author\'s pdf items, not coerced to audio', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author One']);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 2, 'name' => 'Author Two']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Author One PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 5],
        ['id' => 2, 'author' => 2, 'title' => 'Author Two PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Author One Audio Item', 'vedio' => 0, 'pdf' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-pdf-1.htm');

    $response->assertOk()
        ->assertSee('Author One PDF')
        ->assertDontSee('Author Two PDF')
        ->assertDontSee('Author One Audio Item');
});

it('authors index: lists authors with a positive count for the requested op', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Video Author', 'vedio' => 3, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Video Author', 'vedio' => 0, 'hidden' => 0],
    ]);

    $response = $this->get('/khotab-video.htm');

    $response->assertOk()->assertSee('Video Author')->assertDontSee('No Video Author');
});

it('dump: lists pdf items ordered by pdf_time, with a pdf-scoped sidebar', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Older PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Newer PDF', 'pdf' => 1, 'pdf_time' => 200, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab/dump')->assertOk()->getContent();

    expect(strpos($content, 'Newer PDF'))->toBeLessThan(strpos($content, 'Older PDF'));
});
