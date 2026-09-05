<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 4.10 (added post-Wave-4 — see
 * docs/reviews/gap-closure-action-plan.md item 2, IF-032).
 */
function useInMemoryMainConnectionForRadio(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForRadio();
});

function seedRadioPlaylistFixture(): void
{
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        // Audio item, main link is a real .mp3 — should play from main_link.
        ['id' => 1, 'author' => 1, 'title' => 'Audio Lesson', 'vedio' => 0, 'link' => 'https://example.com/audio.mp3', 'broken' => 0, 'hidden' => 0, 'linksize' => 10],
        // Video item — should always play from the mirror link, not main_link.
        ['id' => 2, 'author' => 1, 'title' => 'Video Lesson', 'vedio' => 1, 'link' => 'https://example.com/video.mp4', 'broken' => 0, 'hidden' => 0, 'linksize' => 20],
        // Broken — excluded.
        ['id' => 3, 'author' => 1, 'title' => 'Broken Lesson', 'vedio' => 0, 'link' => 'https://example.com/broken.mp3', 'broken' => 1, 'hidden' => 0, 'linksize' => 30],
        // Hidden — excluded.
        ['id' => 4, 'author' => 1, 'title' => 'Hidden Lesson', 'vedio' => 0, 'link' => 'https://example.com/hidden.mp3', 'broken' => 0, 'hidden' => 1, 'linksize' => 40],
        // Neither link is an mp3 — excluded (no mirror row helps either).
        ['id' => 5, 'author' => 1, 'title' => 'Not Audio Lesson', 'vedio' => 0, 'link' => 'https://example.com/doc.pdf', 'broken' => 0, 'hidden' => 0, 'linksize' => 50],
    ]);

    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        ['id' => 1, 'khid' => 1, 'link' => 'https://mirror.example.com/audio-mirror.mp3'],
        ['id' => 2, 'khid' => 2, 'link' => 'https://mirror.example.com/video-mirror.mp3'],
        ['id' => 3, 'khid' => 3, 'link' => 'https://mirror.example.com/broken-mirror.mp3'],
        ['id' => 4, 'khid' => 4, 'link' => 'https://mirror.example.com/hidden-mirror.mp3'],
        ['id' => 5, 'khid' => 5, 'link' => 'https://mirror.example.com/not-audio-mirror.pdf'],
    ]);
}

it('index: renders the continuous playlist, excluding broken/hidden/non-mp3 items', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->assertOk()->getContent();

    // Scoped to the playlist <ul> specifically — the sidebar's "newest
    // audio" box is a legitimately different, unfiltered-by-broken/hidden
    // query (same shape as khotabMostRecentByVideoFlag's existing
    // sibling, which also has no hidden/broken filter), so it can and
    // does list the excluded items too. Only the playlist JOIN query
    // itself filters broken=0/hidden=0/mp3-only.
    preg_match('/<ul class="playlist">(.*?)<\/ul>/s', $content, $matches);
    $playlistSection = $matches[1] ?? '';

    expect($playlistSection)
        ->toContain('Audio Lesson')
        ->toContain('Video Lesson')
        ->not->toContain('Broken Lesson')
        ->not->toContain('Hidden Lesson')
        ->not->toContain('Not Audio Lesson');
});

it('index: audio items play from the main link when it is an mp3; video items always play from the mirror link', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('audiourl="https://example.com/audio.mp3"') // audio item, main_link wins
        ->toContain('audiourl="https://mirror.example.com/video-mirror.mp3"'); // video item, always mirror_link
});

it('index: /radio-mobile.htm renders the same page as /radio.htm', function () {
    seedRadioPlaylistFixture();

    $this->get('/radio-mobile.htm')->assertOk()->assertSee('Audio Lesson');
});

it('index: sidebar boxes list the newest video/audio items, LIMIT 10, distinct from khotab item page\'s own LIMIT-5 sidebar', function () {
    foreach (range(1, 12) as $i) {
        DB::connection('main')->table('nuke_islamic_khotab')->insert([
            'id' => 100 + $i, 'title' => "Video Item $i", 'vedio' => 1, 'hidden' => 0, 'time' => $i,
        ]);
    }

    $content = $this->get('/radio.htm')->getContent();

    // Only the 10 most recent (highest time) should appear.
    expect($content)->toContain('Video Item 12')->toContain('Video Item 3')->not->toContain('Video Item 1<');
});

it('IF-032: the 3 personalized-playlist op-routes are not reproduced as working ops — they route to the same static page', function () {
    // These 3 .htaccess routes all target radio/index.php, which has no
    // op= handling at all (confirmed) — the file that does implement
    // these ops (radio/indexXX.php) has no route pointing to it and is
    // dead code. No Laravel route exists for these paths at all.
    $this->get('/remove-playlist-item-1-audio.htm')->assertNotFound();
    $this->get('/playlist-item-1.htm')->assertNotFound();
    $this->get('/save-last-listen.htm')->assertNotFound();
});

it('index: loads the two registered stylesheets and two registered scripts, in legacy\'s own registration order', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    // register_css('css/w2a_radio.css') then register_css('css/custom.css').
    // Exact quoted href= match — layouts.app's own sitewide, always-loaded
    // stylesheet is /assets/frontend/layout/css/custom.css, which contains
    // the bare string "/css/custom.css" as a trailing substring, so an
    // unquoted search would false-positive-match that unrelated, earlier tag.
    expect(strpos($content, 'href="/css/w2a_radio.css"'))->toBeLessThan(strpos($content, 'href="/css/custom.css"'));
    // register_script(jquery-ui) then register_script('scripts/w2a_radio.js').
    expect(strpos($content, 'src="/assets/global/plugins/jquery-ui/jquery-ui.min.js"'))
        ->toBeLessThan(strpos($content, 'src="/scripts/w2a_radio.js"'));
});

it('index: renders the full player widget markup w2a_radio.css/w2a_radio.js target (controls, tracker, volume, timer)', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('id="w2a_radio"')
        ->toContain('class="player"')
        ->toContain('class="play-loading"')
        ->toContain('class="tracker"')
        ->toContain('class="volume"')
        ->toContain('class="controls"')
        ->toContain('class="play"')
        ->toContain('class="pause"')
        ->toContain('class="rew"')
        ->toContain('class="fwd"')
        ->toContain('current-t')
        ->toContain('total-t');
});

it('index: #w2a_radio wraps only the player/playlist column, not the sidebar (w2a_radio.css scopes direction:ltr to it)', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    $w2aRadioStart = strpos($content, 'id="w2a_radio"');
    $sidebarStart = strpos($content, 'جديد المواد المرئية');
    // The sidebar heading must come after #w2a_radio's div has already closed,
    // i.e. well past its own small player+playlist markup, not nested inside it.
    $w2aRadioBlock = substr($content, $w2aRadioStart, $sidebarStart - $w2aRadioStart);
    expect($w2aRadioBlock)->not->toContain('جديد المواد المرئية');
});

it('index: playlist items carry the cover="cover1.jpg" attribute, matching radio/index.php:124 exactly', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)->toContain('cover="cover1.jpg"');
});

it('index: playlist uses the premium list with a visible title and author for every item', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('class="playlist"')
        ->toContain('fa-play-circle')
        ->toContain('w2a-pl-num">01</span>')
        ->toMatch('/<li[^>]+artist="Sheikh Author"[^>]+data-title="Audio Lesson"[^>]+data-artist="Sheikh Author"[^>]*>.*?w2a-pl-title">Audio Lesson<.*?w2a-pl-author">.*?Sheikh Author<\/span>/s');
});

it('index: renders the searchable playlist toolbar and accessible result feedback', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('class="w2a-playlist-container"')
        ->toContain('id="w2a_playlist_search_input"')
        ->toContain('id="w2a_playlist_search_clear"')
        ->toContain('id="w2a_playlist_result_status"')
        ->toContain('2 درس صوتي');
});

it('index: sidebar boxes retain their media icons and use the shared linked top-item cards', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 200, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0, 'time' => 1, 'hits' => 42,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 201, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0, 'time' => 1, 'hits' => 7,
    ]);

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('fa-video-camera')
        ->toContain('fa-headphones')
        ->toContain('media-list')
        ->toContain('media-heading')
        ->toContain('42 تحميل')
        ->toContain('7 تحميل');
    expect($content)->toMatch('#<a class="pull-left w2a-top-item-thumb-link" href="/khotab-item-200\.htm">\s*<img class="media-object w2a-top-item-thumb"#');
});

it('index: shows the premium live-radio banner', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->getContent();

    expect($content)
        ->toContain('w2a-radio-banner')
        ->toContain('راديو الطريق إلى الله المباشر')
        ->toContain('استمع زائرنا الكريم بشكل متواصل لأحدث الدروس والمحاضرات الصوتية المضافة للموقع.');
});

it('index: /radio.htm has no w2a_is_mobile hidden input; /radio-mobile.htm does (detect_if_mobile_view() gap closure)', function () {
    seedRadioPlaylistFixture();

    $this->get('/radio.htm')->assertDontSee('w2a_is_mobile', false);
    $this->get('/radio-mobile.htm')->assertSee('id="w2a_is_mobile"', false);
});

it('index: ?mobile_me=true on the plain /radio.htm path also sets the hidden input, matching detect_if_mobile_view()\'s real $_GET check', function () {
    seedRadioPlaylistFixture();

    $this->get('/radio.htm?mobile_me=true')->assertSee('id="w2a_is_mobile"', false);
});

// ---- Shared Page Chrome Parity Audit: radio/index.php:24-27's single-item (current page, no url key) breadcrumb ----

it('index: renders the heading and the single-item plain-text breadcrumb, before the player row', function () {
    seedRadioPlaylistFixture();

    $content = $this->get('/radio.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title">راديو الطريق الى الله</h3>');
    expect($content)->toContain('<li>راديو الطريق الى الله<i class=""></i></li>');
    expect(strpos($content, 'page-title'))->toBeLessThan(strpos($content, 'id="w2a_radio"'));
});
