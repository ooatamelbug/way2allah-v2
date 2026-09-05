<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForFatwaChannelController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_sat_sats' => MainSchema::nukeSatSats(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaChannelController();
});

it('index: lists only channels with at least one fatwa question, plus the always-present "no channel" entry', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Has Questions'],
        ['id' => 2, 'title' => 'No Questions'],
    ]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 1]);

    $response = $this->get('/fatawa-channels.htm');

    $response->assertOk()
        ->assertSee('بدون قناة')
        ->assertSee('Has Questions')
        ->assertDontSee('No Questions');
});

// ---- Legacy-Source Reconstruction: fatawa-channels.php:12-13,21's document title vs breadcrumb label are genuinely different strings ----

it('index: document <title> is "عرض الفتاوى | حسب القنوات الفضائية", NOT the breadcrumb\'s "قائمة القنوات الفضائية" text', function () {
    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)->toContain('<title>عرض الفتاوى | حسب القنوات الفضائية - ')
        ->not->toContain('<title>قائمة القنوات الفضائية - ');
});

// ---- Legacy-Source Reconstruction: fatawa-channels.php:21's page_bar_channels('قائمة القنوات الفضائية') — $channel stays unset, so only 3 breadcrumb items ever render ----

it('index: renders the empty <h1> and the exact 3-item breadcrumb (Home, الفتاوى, قائمة القنوات الفضائية) — no 4th (channel) item, since $channel is never passed on this page', function () {
    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)->toContain('<h1 style=""></h1>')
        ->not->toContain('page-title');

    expect($content)
        ->toContain('<li><i class="fa fa-home"></i><a href="/"> الرئيسية</a></li>')
        ->toContain('<li> <i class="fa fa-angle-right"></i><a href="/fatawa.htm">الفتاوى </a></li>')
        ->toContain('<li> <i class="fa fa-angle-right"></i><a href="/fatawa-channels.htm">قائمة القنوات الفضائية</a></li>');

    expect(substr_count($content, 'page-breadcrumb'))->toBe(1);
    expect(substr_count($content, '<li>'))->toBeGreaterThanOrEqual(3);
});

// ---- Legacy-Source Reconstruction: fatawa-channels.php:29-67's real portlet + channel-logo grid — previously a bare <ul><li><a>text</a></li></ul> ----

it('index: renders the redesigned channel directory with semantic premium panel markup', function () {
    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-refresh-page w2a-fatawa-channels-page"')
        ->toContain('class="w2a-channel-grid"')
        ->toContain('<h2>قائمة القنوات الفضائية</h2>')
        ->not->toContain('co-sm-12');
});

it('index: renders each channel as a named, linked logo card', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 3, 'title' => 'Al Rahma']);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 3]);

    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/fatawa-channel-3.htm" class="w2a-channel-card">')
        ->toContain('<img src="/images/channels/3.png" width="120" height="120" alt="Al Rahma"')
        ->toContain('<strong>Al Rahma</strong>');
});

it('index: the "بدون قناة" (no-channel) entry uses the exact same card shape, id 0, and is always the first card', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 3, 'title' => 'Al Rahma']);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 3]);

    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)->toContain('<img src="/images/channels/0.png" width="120" height="120" alt="بدون قناة"');
    expect(strpos($content, 'fatawa-channel-0.htm'))->toBeLessThan(strpos($content, 'fatawa-channel-3.htm'));
});

it('index: no pagination markup when the channel count fits on one page (matches pagination()\'s own if($count > $perpage) guard)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 1]);

    $content = $this->get('/fatawa-channels.htm')->assertOk()->getContent();

    expect($content)->not->toContain('rel="next"')->not->toContain('rel="prev"');
});

it('show: lists general questions for the channel, resolved via the multi-step legacy query shape, with topic and author attached', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic X', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 100, 'question_text' => 'Channel-scoped question', 'topic_id' => '|10|',
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'channel_id' => 1, 'general_question_id' => '|100|', 'auther_id' => 5],
        ['id' => 2, 'channel_id' => 99, 'general_question_id' => '|200|', 'auther_id' => 5],
    ]);

    $response = $this->get('/fatawa-channel-1.htm');

    $response->assertOk()->assertSee('Channel-scoped question')->assertSee('Topic X')->assertSee('Shaikh');
});

it('show: 404s for a nonexistent channel', function () {
    $this->get('/fatawa-channel-999.htm')->assertNotFound();
});

it('show: G-13-10 — renders the channel logo (flat images/channels/{id}.png) and the HARDCODED beam image (images/beams/1.png, not per-channel dynamic)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 7, 'title' => 'Chan', 'beam' => 3]);

    $content = $this->get('/fatawa-channel-7.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/7.png')
        ->and($content)->toContain('/images/beams/1.png')
        // confirms this is NOT reproducing live-stream's per-channel `beam` column behavior
        ->and($content)->not->toContain('/images/beams/3.png');
});

it('show: most-downloaded sidebar is genuinely channel-scoped (WHERE channel_id is NOT commented out, unlike the author page)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'channel_id' => 1, 'question_text' => 'In this channel', 'num_download' => 100],
        ['id' => 2, 'channel_id' => 2, 'question_text' => 'Different channel entirely', 'num_download' => 999],
    ]);

    $response = $this->get('/fatawa-channel-1.htm');

    $response->assertOk()->assertSee('In this channel')->assertDontSee('Different channel entirely');
});

// ---- Legacy-Source Reconstruction: channel_fatawa.php:29's page_bar_channels() chrome (fatawa/functions.php:321-352) — a bespoke mechanism, NOT the shared <x-page-chrome> component ----

it('show: renders the empty <h1> (no shared page-title heading — page_bar_channels() has none) and the hand-rolled breadcrumb chain', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h1 style=""></h1>')
        ->not->toContain('page-title');

    // Home's own <li> has no trailing separator icon; every subsequent
    // item puts its fa-angle-right icon BEFORE the link, not after —
    // structurally different from the shared breadcrumb() shape.
    expect($content)->toContain('<li><i class="fa fa-home"></i><a href="/"> الرئيسية</a></li>')
        ->toContain('<li> <i class="fa fa-angle-right"></i><a href="/fatawa.htm">الفتاوى </a></li>')
        ->toContain('<li> <i class="fa fa-angle-right"></i><a href="/fatawa-channels.htm">قائمة القنوات الفضائية</a></li>')
        ->toContain('<li> <i class="fa fa-angle-right"></i><a href="/fatawa-channel-1.htm">Chan</a></li>');
});

it('show: falls back to "بدون قناه" when the channel title is empty, used consistently in the heading, breadcrumb, and both portlet captions', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => '']);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect(substr_count($content, 'بدون قناه'))->toBeGreaterThanOrEqual(4);
});

// ---- Legacy-Source Reconstruction: channel_fatawa.php:39-104's "بيانات قناة" info portlet — previously entirely missing ----

it('show: renders the full channel-info portlet — satellite name/position (W/E translated), frequency, polarization, symbol rate, FEC, encryption', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_sats')->insert(['id' => 9, 'title' => 'Nilesat', 'pos' => '7W']);
    $db->table('nuke_sat_channels')->insert([
        'id' => 1, 'title' => 'Chan', 'sat_id' => 9,
        'freq' => '11000', 'polar' => 'V', 'srate' => '27500', 'fec' => '3/4', 'enc' => 'Free',
    ]);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('بيانات قناة Chan')
        ->toContain('اسم القناة : Chan')
        ->toContain('<a href="/fatawa-channels.htm">Nilesat</a>')
        ->toContain('الموقع المداري : 7 غرباً')
        ->toContain('التردد : 11000')
        ->toContain('الإستقطاب : V')
        ->toContain('معدل الترميز : 27500')
        ->toContain('معامل التصويب : 3/4')
        ->toContain('التشفير : Free');
});

it('show: translates an East orbital position too, and tolerates a channel with no satellite row at all', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_sats')->insert(['id' => 2, 'title' => 'Eutelsat', 'pos' => '7E']);
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan', 'sat_id' => 2]);
    $db->table('nuke_sat_channels')->insert(['id' => 2, 'title' => 'Orphan Chan', 'sat_id' => null]);

    $this->get('/fatawa-channel-1.htm')->assertOk()->assertSee('الموقع المداري : 7 شرقاً');

    // No fatal error / exception when the channel has no matching satellite row.
    $this->get('/fatawa-channel-2.htm')->assertOk();
});

it('show: the channel logo links to this same page (translated from the raw legacy self-reference, not a dangling .php link)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan']);

    $content = $this->get('/fatawa-channel-5.htm')->assertOk()->getContent();

    expect($content)->toMatch('#<a href="/fatawa-channel-5\.htm"><img src="/images/channels/5\.png"#');
});

// ---- Legacy-Source Reconstruction: channel_fatawa.php:106-140's questions-list portlet — real table#sample_5 markup, not a bare <ul> ----

it('show: renders the questions list as a real table#sample_5 with no visible header row (legacy comments both <thead> and the bottom header <tr> out)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic X', 'parent_id' => 3]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'Q', 'topic_id' => '|10|']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 1, 'general_question_id' => '|100|', 'auther_id' => 5]);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="sample_5">')
        ->not->toContain('<th');
    expect($content)
        ->toContain('<a href="/fatawa-all-100.htm">Q</a>')
        ->toContain('<a href="/fatawa-group-10-3.htm">Topic X</a>')
        ->toContain('<a href="/auther-questions-5.htm">Dr. Shaikh</a>');
});

// ---- Legacy-Source Reconstruction: channel_fatawa.php:153,166's sidebar — real portlet wrapper + the real mostdownload()/recentlyadd() link shape ----

it('show: sidebar items link to /fatawa-all-{general_question_id}.htm#{id}, not /fatawa-download-{id}.htm — the query already exposes general_question_id, this was a view-only fix', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 42, 'channel_id' => 1, 'general_question_id' => '|100|', 'question_text' => 'Top Q', 'num_download' => 5,
    ]);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="/fatawa-all-100.htm#42" class="add">Top Q</a></li>')
        ->not->toContain('fatawa-download-');
});

it('show: sidebar portlets have the correct icons/captions ("الأكثر تحميلا"/fa-download, "جديد المواد"/fa-plus), wrapped in a real portlet, not a bare <h3>', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<i class="fa fa-download"></i>الأكثر تحميلا')
        ->toContain('<i class="fa fa-plus"></i>جديد المواد')
        ->toContain('<ul class="news">')
        ->not->toContain('<h3>الأكثر تحميلا</h3>');
});

it('show: empty state — no questions and no sidebar items renders without error, table body genuinely empty', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);

    $content = $this->get('/fatawa-channel-1.htm')->assertOk()->getContent();

    preg_match('#<table class="table table-striped table-hover" id="sample_5">.*?</table>#s', $content, $matches);
    expect($matches[0] ?? '')->not->toContain('<tr>');
});
