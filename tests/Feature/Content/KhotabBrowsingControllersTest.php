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

it('group show: G-13-11 — the series list shows a channel icon only when the series has a channel_id, matching ListSeries() exactly', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 1, 'author_id' => 1, 'group_id' => 20, 'title' => 'With Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9],
        ['id' => 2, 'author_id' => 1, 'group_id' => 20, 'title' => 'No Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0],
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png');
    // "No Channel" row must not accidentally render a channels/0.png link.
    expect(substr_count($content, 'images/channels/'))->toBe(1);
});

// ---- G-13-03 (media/visual parity phase): the "الملف الشخصي" author-photo box was missing entirely on series/group ----

it('series show: G-13-03 — renders the previously-missing "الملف الشخصي" author-photo box, ignoring author_image (series.php\'s own unconditional get_author_img())', function () {
    // id 999999 is deliberately far outside any real bucket in the now-populated
    // media library (authors/ only has bucket 0/), so this can't collide with a
    // genuine media/authors/sq/{id}.png and silently take the "real file" branch.
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 999999, 'name' => 'Author', 'author_image' => 'https://example.com/custom.png',
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 999999, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-series-10.htm')->assertOk()->getContent();

    expect($content)->toContain('الملف الشخصي')
        ->and($content)->not->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

it('group show: G-13-03 — renders the previously-missing "الملف الشخصي" author-photo box, ignoring author_image (group.php\'s own unconditional get_author_img())', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 999999, 'name' => 'Author', 'author_image' => 'https://example.com/custom.png',
    ]);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 999999, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('الملف الشخصي')
        ->and($content)->not->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
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

it('author show: G-13-11 — group/series lists show a channel icon only when channel_id is set, matching ListGroup()/ListSeries() exactly', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0]);
    // groupsByAuthor() inner-joins nuke_islamic_khotab on group_id — a real row is required for the group to appear at all.
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
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

// ---- Visual parity audit (khotab-video-17.htm, 2026-08-18): author show() Batch 1 — page-title/breadcrumb/portlet-wrapper/promo-banner restored, previously missing ----

it('author show: renders author.php:56\'s <h3 class="page-title"> and the matching <title> tag, one phrase per op (video/audio/pdf)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Video Item', 'vedio' => 1, 'pdf' => 0, 'pdf_time' => 0, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Audio Item', 'vedio' => 0, 'pdf' => 0, 'pdf_time' => 0, 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'title' => 'PDF Item', 'vedio' => 0, 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video-1.htm')->assertOk()->getContent();
    expect($video)->toContain('<h3 class="page-title">مرئيات Sheikh Author</h3>')
        ->and($video)->toContain('<title>مرئيات Sheikh Author - ')
        ->not->toContain('class=a fa-gift');

    $audio = $this->get('/khotab-audio-1.htm')->assertOk()->getContent();
    expect($audio)->toContain('<h3 class="page-title">صوتيات Sheikh Author</h3>');

    $pdf = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();
    // Legacy's own literal double space (functions.php's 'المواد المفرغة لـ  ' — not a typo, reproduced as-is).
    expect($pdf)->toContain('<h3 class="page-title">المواد المفرغة لـ  Sheikh Author</h3>');
});

it('author show: renders author.php:53-57\'s breadcrumb — first two segments both link to /khotab-{op}.htm, final segment is the author\'s own name with href=""', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="/khotab-video.htm">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->and($content)->toContain('<li><a href="/khotab-video.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>')
        ->and($content)->toContain('<li><a href="">Sheikh Author</a><i class=""></i></li>');
});

it('author show: every section (groups/series/items + all 4 always-present sidebar widgets) is wrapped in .portlet.box.blue with the correct fa-child icon and legacy caption text', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة المجموعات</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> جديد المواد</div>')
        // 7 always-present portlets for a video/audio op with no description (the promo banner is the 8th, checked separately below).
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(8);
});

it('author show: the pdf op has no promo-banner widget (legacy author.php:110-138 only has video/audio branches) — 7 portlets, not 8', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $content = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('images/video.gif')
        ->and($content)->not->toContain('images/audio.gif')
        // pdf op: no groups/series portlets (op !== 'pdf' gate) and no
        // promo banner — just "قائمة المواد" + the 4 always-present
        // sidebar widgets (profile/"اخترنا لك"/"الأكثر تحميلا"/"جديد المواد").
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(5);
});

it('author show: renders the video/audio promotional banner (author.php:110-138) with the correct image/dimensions/self-link, previously missing entirely', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 42, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 42, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 42, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video-42.htm')->assertOk()->getContent();
    expect($video)->toContain('<div class="caption"><i class="fa fa-child"></i> مرئيات الداعية</div>')
        ->and($video)->toContain('<div class="portlet-body text-center">')
        ->and($video)->toContain('<a href="/khotab-video-42.htm">')
        ->and($video)->toContain('<img border="0" src="/images/video.gif" width="192" height="71" alt="">');

    $audio = $this->get('/khotab-audio-42.htm')->assertOk()->getContent();
    expect($audio)->toContain('<div class="caption"><i class="fa fa-child"></i> صوتيات الداعية</div>')
        ->and($audio)->toContain('<a href="/khotab-audio-42.htm">')
        ->and($audio)->toContain('<img border="0" src="/images/audio.gif" width="192" height="71" alt="">');
});

it('author show: the description block (when present) is wrapped in .portlet.box.blue with NO caption/icon header, matching author.php:80-90\'s own hand-rolled markup (unlike every other portlet on this page)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'description' => 'A biography.']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('A biography.')
        // 8 base (video op, no group/series/item channel data needed here) + 1 description portlet.
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(9)
        // The description portlet has no portlet-title/caption at all — one
        // fewer portlet-title than total portlets (9 portlets, 8 titles).
        ->and(substr_count($content, 'class="portlet-title"'))->toBe(8);
});

it('author show: no duplicate element ids on the page', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    preg_match_all('/\sid="([^"]+)"/', $content, $matches);
    $ids = $matches[1];

    expect($ids)->toBe(array_unique($ids));
});

// ---- Visual parity audit (khotab-video-17.htm, 2026-08-18): author show() Batch 2 — Groups/Series/Items rich row markup restored, previously simplified ----

it('author show: renders ListGroup()\'s exact row markup (khotab/functions.php:360-402) — table#tabelgrp, count with fa-play-circle-o, channel badge only when channel_id is set', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'author_id' => 1, 'title' => 'With Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9],
        ['id' => 2, 'author_id' => 1, 'title' => 'No Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item A', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'group_id' => 1, 'title' => 'Item B', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'group_id' => 2, 'title' => 'Item C', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tabelgrp">')
        ->and($content)->toContain('<i class="fa fa-play-circle-o"></i>')
        ->and($content)->toContain('المواد:')
        ->and($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
});

it('author show: renders ListSeries()\'s exact row markup (khotab/functions.php:452-495) — table#tableser, date + last-updated + count + conditional channel badge', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 3, 15, 2026);
    $lastupdate = mktime(0, 0, 0, 4, 1, 2026);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
        'count' => 7, 'channel_id' => 9, 'time' => $time, 'lastupdate' => $lastupdate,
    ]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tableser">')
        ->and($content)->toContain('<i class="fa fa-calendar"></i>')
        ->and($content)->toContain(date('Y-m-d', $time))
        ->and($content)->toContain('<i class="fa fa-refresh"></i>')
        ->and($content)->toContain(date('Y-m-d', $lastupdate))
        ->and($content)->toContain('<i class="fa fa-play-circle-o"></i>')
        ->and($content)->toContain('المواد:')
        ->and($content)->toContain('/images/channels/9.png');
});

it('author show: renders ListKhotab()\'s exact default-branch row markup (khotab/functions.php:643-706) — table#tabelkht, date/comments/views always shown, channel badge and duration conditional', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 4, 22, 2026);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'An Item', 'vedio' => 1, 'hidden' => 0,
        'comments' => 4, 'hits' => 1872, 'channel_id' => 9, 'time' => $time,
    ]);
    DB::connection('main')->table('nuke_islamic_advanced')->insert(['id' => 1, 'adur' => 3621662]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tabelkht">')
        ->and($content)->toContain('<i class="fa fa-calendar"></i>')
        ->and($content)->toContain(date('Y-m-d', $time))
        ->and($content)->toContain('<i class="fa fa-commenting-o"></i>')
        ->and($content)->toContain('التعليقات:')
        ->and($content)->toContain('4')
        ->and($content)->toContain('<i class="fa fa-eye"></i>')
        ->and($content)->toContain('مشاهدات:')
        ->and($content)->toContain(number_format(1872))
        ->and($content)->toContain('/images/channels/9.png')
        ->and($content)->toContain('<i class="fa fa-clock-o"></i>')
        // Verified against real olddb data (khotab-item-158635, adur=3621662ms) — matches live legacy's displayed "01:00:21" exactly.
        ->and($content)->toContain('01:00:21');
});

it('author show: an item with no nuke_islamic_advanced row (adur missing) never shows a duration — LegacyDurationFormatter(0) is "00:00:00", hidden per ListKhotab()\'s own check', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'No Advanced Row', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('fa-clock-o');
});

it('LegacyDurationFormatter::format(): matches Duration() (functions.php:357-365) exactly, verified against 2 real olddb items\' raw adur values', function () {
    // khotab-item-158635 (>1hr branch, 12-hour "h" format — legacy's own date("h:i:s",...), not 24-hour).
    expect(\App\Domain\Content\Support\LegacyDurationFormatter::format(3621662))->toBe('01:00:21')
        // khotab-item-158739 (<=1hr branch, "00:i:s").
        ->and(\App\Domain\Content\Support\LegacyDurationFormatter::format(3405995))->toBe('00:56:45')
        ->and(\App\Domain\Content\Support\LegacyDurationFormatter::format(0))->toBe('00:00:00');
});

it('author show: pdf op\'s item list still renders (khotabPdfItemsByAuthor() has no adur column) — no fatal error, duration silently omitted', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $content = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();

    expect($content)->toContain('PDF Item')
        ->and($content)->not->toContain('fa-clock-o');
});

it('authors index: lists authors with a positive count for the requested op', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Video Author', 'vedio' => 3, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Video Author', 'vedio' => 0, 'hidden' => 0],
    ]);

    $response = $this->get('/khotab-video.htm');

    $response->assertOk()->assertSee('Video Author')->assertDontSee('No Video Author');
});

it('authors index: G-13-03 — each row renders a photo, preferring author_image over get_author_img() (matches authors.php:77\'s own ternary)', function () {
    // Ids deliberately far outside any real bucket in the now-populated media
    // library, so the "no custom image" row can't collide with a genuine
    // media/authors/sq/{id}.png and silently take the "real file" branch.
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 999998, 'name' => 'Has Custom Image', 'vedio' => 1, 'hidden' => 0, 'author_image' => 'https://example.com/custom.png'],
        ['id' => 999999, 'name' => 'No Custom Image', 'vedio' => 1, 'hidden' => 0, 'author_image' => null],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($content)->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

// ---- Visual parity audit (khotab-video.htm, 2026-08-18): authors.php's page-title/breadcrumb/portlet/grouping/A-Z-nav/count, previously entirely missing ----

it('authors index: renders authors.php\'s page-title, breadcrumb, and portlet wrapper, op-specific per authors.php:6-27', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'vedio' => 1, 'hidden' => 0]);

    $video = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($video)->toContain('<title>قسم المرئيات - ')
        ->and($video)->toContain('<h3 class="page-title">قائمة الدعاة بقسم المرئيات</h3>')
        ->and($video)->toContain('<div class="page-bar">')
        ->and($video)->toContain('<li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>')
        ->and($video)->toContain('<li><a href="">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->and($video)->toContain('<li><a href="">قائمة الدعاة</a><i class=""></i></li>')
        ->and($video)->toContain('<div class="portlet box blue">')
        ->and($video)->toContain('<i class="fa fa-child"></i>');

    // Malformed legacy icon (functions.php:541-543's stray \f escape) is a
    // legacy authoring bug, deliberately not reproduced.
    expect($video)->not->toContain('class=a fa-gift');

    $audio = $this->get('/khotab-audio.htm')->assertOk()->getContent();

    expect($audio)->toContain('<title>قسم الصوتيات - ')
        ->and($audio)->toContain('<h3 class="page-title">قائمة الدعاة بقسم الصوتيات</h3>')
        ->and($audio)->toContain('<li><a href="">الصوتيات</a><i class="fa fa-angle-right"></i></li>');
});

it('authors index: groups authors by first letter exactly like authors.php:58-74, including the ه→هـ normalization', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'أحمد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'أنس', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'name' => 'هشام', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    // BINARY-ordered: أ (0x...627) sorts before ه (0x...647) — two groups,
    // "أحمد"/"أنس" both fall under one أ group (index 0), "هشام" starts a
    // new group at index 2, normalized to هـ (authors.php:61).
    expect(substr_count($content, '<h1 id="0">أ</h1>'))->toBe(1)
        ->and(substr_count($content, '<h1 id="2">هـ</h1>'))->toBe(1)
        // Only 2 groups total — "أنس" must NOT start its own group.
        ->and(substr_count($content, '<h1 id='))->toBe(2);
});

it('authors index: a single shared first letter across all authors renders exactly one group, not one per author', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'خالد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'خليل', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'name' => 'خميس', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect(substr_count($content, '<h1 id='))->toBe(1)
        ->and($content)->toContain('<h1 id="0">خ</h1>');
});

it('authors index: renders the real vedio/audio/pdf column value as the per-author count, with the op-specific label word', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Video Author', 'vedio' => 42, 'audio' => 7, 'pdf' => 3, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video.htm')->assertOk()->getContent();
    expect($video)->toContain('<span class="testimonials-post">42 فيديو</span>');

    $audio = $this->get('/khotab-audio.htm')->assertOk()->getContent();
    expect($audio)->toContain('<span class="testimonials-post">7 صوت</span>');

    $pdf = $this->get('/khotab-pdf.htm')->assertOk()->getContent();
    expect($pdf)->toContain('<span class="testimonials-post">3 منشور</span>');
});

it('authors index: author link points at khotab-{op}-{id}.htm', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 55, 'name' => 'Author', 'vedio' => 1, 'audio' => 1, 'hidden' => 0]);

    expect($this->get('/khotab-video.htm')->assertOk()->getContent())->toContain('href="/khotab-video-55.htm"');
    expect($this->get('/khotab-audio.htm')->assertOk()->getContent())->toContain('href="/khotab-audio-55.htm"');
});

it('authors index: renders the A-Z jump-nav container and its populating script exactly once, after jquery.min.js', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'أحمد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'بلال', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect(substr_count($content, 'class="abc text-center"'))->toBe(1)
        ->and(substr_count($content, 'var letterList'))->toBe(1)
        ->and($content)->toContain('<a href="#0">أ</a>&nbsp;-&nbsp;<a href="#1">ب</a>');

    $jqueryPos = strpos($content, 'jquery.min.js');
    $scriptPos = strpos($content, 'var letterList');

    expect($jqueryPos)->not->toBeFalse()
        ->and($scriptPos)->not->toBeFalse()
        ->and($jqueryPos)->toBeLessThan($scriptPos);
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

// ---- G-12-04 (G-12 investigation): dumped-lectures.htm is a real, live
// .htaccess rule (`.htaccess:221`) with a real homepage link
// (home_functions.php:398) — the pretty path now resolves to the exact same
// KhotabDumpController::index() as /khotab/dump, not a duplicated copy. ----

it('dumped-lectures.htm: resolves via KhotabDumpController::index(), identical to /khotab/dump', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Older PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Newer PDF', 'pdf' => 1, 'pdf_time' => 200, 'hidden' => 0],
    ]);

    $prettyContent = $this->get('/dumped-lectures.htm')->assertOk()->getContent();
    $rawContent = $this->get('/khotab/dump')->assertOk()->getContent();

    expect($prettyContent)->toBe($rawContent);
    expect(strpos($prettyContent, 'Newer PDF'))->toBeLessThan(strpos($prettyContent, 'Older PDF'));
});

it('/khotab/dump: still resolves unchanged after dumped-lectures.htm registration', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Only PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0,
    ]);

    $this->get('/khotab/dump')->assertOk()->assertSee('Only PDF');
});
