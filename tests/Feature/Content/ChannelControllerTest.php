<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForChannelController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForChannelController();
});

it('index: lists ALL channels, no eligibility filter at all (unlike live-stream)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Active', 'active' => 0, 'khotab' => 5],
        ['id' => 2, 'title' => 'Inactive too', 'active' => 1, 'khotab' => 10],
    ]);

    $response = $this->get('/channels.htm');

    $response->assertOk()->assertSee('Active')->assertSee('Inactive too');
});

it('index: G-13-08 — each row shows the flat images/channels/{id}.png logo, matching channels.php:43', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 7, 'title' => 'A Channel', 'active' => 0, 'khotab' => 1]);

    $this->get('/channels.htm')->assertOk()->assertSee('/images/channels/7.png', false);
});

it('index: loads gallery.css and renders the hover-reveal gallery-item/zoomix/channel-logo markup it targets (channels.php:9,41-51)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        'id' => 7, 'title' => 'A Channel', 'active' => 0, 'khotab' => 1,
        'freq' => '11000', 'polar' => 'V', 'srate' => '27500', 'fec' => '3/4',
    ]);

    $response = $this->get('/channels.htm');

    $response->assertOk()
        ->assertSee('/assets/frontend/pages/css/gallery.css', false)
        ->assertSee('gallery-item', false)
        ->assertSee('zoomix', false)
        ->assertSee('channel-logo', false)
        ->assertSee('قناة : A Channel')
        ->assertSee('التردد : 11000');
});

it('index: orders by khotab desc', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Fewer', 'khotab' => 2],
        ['id' => 2, 'title' => 'More', 'khotab' => 20],
    ]);

    $content = $this->get('/channels.htm')->getContent();

    expect(strpos($content, 'More'))->toBeLessThan(strpos($content, 'Fewer'));
});

it('show: renders groups/series/items scoped to the channel and a populated most-downloaded/newest sidebar', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 1]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Item One', 'vedio' => 1, 'hits' => 10, 'time' => 100, 'hidden' => 0],
    ]);

    $response = $this->get('/channel-5.htm');

    $response->assertOk()
        ->assertSee('Item One')
        ->assertSeeInOrder(['الأكثر تحميلا', 'Item One', 'جديد المواد', 'Item One']);
});

it('show: 404s for a nonexistent channel', function () {
    $this->get('/channel-999.htm')->assertNotFound();
});

// ---- Shared Page Chrome Parity Audit: channel.php:41-46's heading/breadcrumb (no-author branch) + document <title>'s real "مرئيات" prefix ----

it('show: renders the heading, breadcrumb, and the document-title "مرئيات" prefix, all distinct strings per channel.php', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    // channel.php:12's real document title has a "مرئيات" prefix the visible heading does not.
    expect($content)->toContain('<title>مرئيات قناة Chan - ')
        ->and($content)->toContain('<h3 class="page-title">قناة Chan</h3>')
        ->and($content)->not->toContain('<title>قناة Chan - ');

    expect($content)
        ->toContain('<li><a href="/channels.htm">القنوات الفضائية</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="">قناة Chan</a><i class=""></i></li>');
});

// ---- Final Sidebar Gap Closure (2026-08-22): channel.php's real sidebar
// presentation — box captions with icons, "بيانات القناة"'s real
// .thumbnail/.caption structure, and the topitems()-shaped media-list
// cards (thumb, hits/date metadata, link) for the other 2 boxes ----

it('show: all 3 sidebar portlets use the real channel.php icons — fa-child/fa-cloud-download/fa-flash, not bare captions', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> بيانات القناة</div>')
        ->toContain('<div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>')
        ->toContain('<div class="caption"><i class="fa fa-flash"></i> جديد المواد</div>');
});

it('show: "بيانات القناة" renders the real .thumbnail/.caption structure with an <h3> title and the 2 legacy-hardcoded satellite/orbital lines', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        'id' => 5, 'title' => 'Chan', 'khotab' => 0, 'freq' => '11000', 'polar' => 'V', 'srate' => '27500', 'fec' => '3/4', 'enc' => 'Free',
    ]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="thumbnail">')
        ->toContain('<img src="/images/channels/5.png" alt="Chan"')
        ->toContain('<h3>قناة Chan</h3>')
        // Not $channelModel-> fields — literal legacy-hardcoded strings (channel.php:81-82).
        ->toContain('<p>القمر الصناعي : النايل سات</p>')
        ->toContain('<p>الموقع المداري : 7 غرباً</p>')
        ->toContain('<p>التردد : 11000</p>')
        ->toContain('<p>الإستقطاب : V</p>')
        ->toContain('<p>معدل الترميز : 27500</p>')
        ->toContain('<p>معامل التصويب : 3/4</p>')
        ->toContain('<p>التشفير : Free</p>');
});

it('show: "الأكثر تحميلا"/"جديد المواد" use the real media-list card DOM — 60x40 thumb, <h5> title link, and mode-correct metadata (hits vs. date)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 100, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Downloaded Item', 'vedio' => 1, 'frame' => 0, 'hits' => 4200, 'time' => mktime(0, 0, 0, 6, 6, 2015), 'hidden' => 0],
    ]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<ul class="media-list">')
        ->toContain('<li class="media">')
        ->toContain('<img class="media-object" src="/images/way2_withoutimg.png" alt="Downloaded Item"')
        ->toContain('<a href="/khotab-item-100.htm"><h5 class="media-heading">Downloaded Item</h5></a>')
        // "الأكثر تحميلا" (mode='hits'): a formatted hit count, not a date.
        ->toContain('<small>عدد مرات التحميل: 4,200 مرة</small>')
        // "جديد المواد" (mode='time', confirmed from channel.php:110 directly): a real formatted date, not a hit count.
        ->toContain('<small>بتاريخ: '.\App\Domain\Content\Support\LegacyShortDateFormatter::format(mktime(0, 0, 0, 6, 6, 2015)).'</small>');
});

it('show: sidebar thumbnail resolves to a real bucketed frame path when frame=1 and the file exists on disk', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);
    $id = 55;
    $db->table('nuke_islamic_khotab')->insert([
        'id' => $id, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Framed Item', 'vedio' => 1, 'frame' => 1, 'hits' => 1, 'time' => 1, 'hidden' => 0,
    ]);

    $dir = public_path('media/khotab_frames/0');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/'.$id.'.jpg', 'fake-jpg-bytes');

    try {
        $content = $this->get('/channel-5.htm')->assertOk()->getContent();
        expect($content)->toContain('/media/khotab_frames/0/'.$id.'.jpg');
    } finally {
        @unlink($dir.'/'.$id.'.jpg');
    }
});

it('show: sidebar ordering — "الأكثر تحميلا" is hits DESC, "جديد المواد" is time DESC', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Low Hits Newer', 'vedio' => 1, 'frame' => 0, 'hits' => 1, 'time' => 200, 'hidden' => 0],
        ['id' => 2, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'High Hits Older', 'vedio' => 1, 'frame' => 0, 'hits' => 999, 'time' => 100, 'hidden' => 0],
    ]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    $mostDownloadedStart = strpos($content, 'الأكثر تحميلا');
    $mostRecentStart = strpos($content, 'جديد المواد');
    $mostDownloadedSection = substr($content, $mostDownloadedStart, $mostRecentStart - $mostDownloadedStart);
    $mostRecentSection = substr($content, $mostRecentStart);

    expect(strpos($mostDownloadedSection, 'High Hits Older'))->toBeLessThan(strpos($mostDownloadedSection, 'Low Hits Newer'));
    expect(strpos($mostRecentSection, 'Low Hits Newer'))->toBeLessThan(strpos($mostRecentSection, 'High Hits Older'));
});

it('show: main tables (#tableser/#tabelkht/#tabelgrp availability) and DataTables assets are unaffected by the sidebar fix', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);

    $content = $this->get('/channel-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('datatables.min.css')
        ->toContain('datatables.bootstrap-rtl.css')
        ->toContain('datatables.min.js')
        ->toContain('datatables.bootstrap.js')
        ->toContain('/scripts/khotab_tables.js');
});

it('showAuthor: channel-{id}-{author}.htm sidebar remains unaffected by the show() sidebar fix — still no media-list, no icons, no thumb, matching IF-012\'s confirmed-empty boxes', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 0]);
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Q', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'channel_id' => 5, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Author Item', 'vedio' => 1, 'frame' => 0, 'hits' => 500, 'time' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect($content)
        ->not->toContain('media-list')
        ->not->toContain('media-object')
        ->not->toContain('fa-cloud-download')
        ->not->toContain('fa-flash')
        ->not->toContain('عدد مرات التحميل');
});

it('index: renders the heading and the single-item (current page, empty-href) breadcrumb', function () {
    $content = $this->get('/channels.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">قائمة القنوات الفضائية</h3>')
        ->toContain('<li><a href="">القنوات الفضائية</a><i class=""></i></li>');
});

it('showAuthor: filters groups/series/items by author, and leaves the most-downloaded/newest boxes empty (IF-012)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan']);
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Q', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'title' => 'By author 9', 'vedio' => 1, 'hits' => 1, 'time' => 1, 'hidden' => 0],
        ['id' => 2, 'channel_id' => 5, 'author' => 42, 'ser_id' => 0, 'group_id' => 0, 'title' => 'By other author', 'vedio' => 1, 'hits' => 999, 'time' => 1, 'hidden' => 0],
    ]);

    $response = $this->get('/channel-5-9.htm');

    $response->assertOk()
        ->assertSee('By author 9')
        ->assertDontSee('By other author');

    // The item with the highest hits (999) belongs to a DIFFERENT author and
    // must never appear — if the sidebar were accidentally populated here
    // (like channel.php's page, but unlike author.php's), it would leak in.
    // (Swapped onto the excluded item, not the included one, now that Batch 1's
    // restored table markup legitimately renders each visible item's own hit count.)
    $response->assertDontSee('999');
});

it('showAuthor: 404s for a nonexistent channel', function () {
    $this->get('/channel-999-1.htm')->assertNotFound();
});
