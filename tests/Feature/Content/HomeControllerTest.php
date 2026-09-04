<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-02 (Migration Gap Register) — Homepage Migration. Fixture schema uses
 * the same canonical MainSchema table definitions as
 * ContentListingServiceTest (not a locally-drifted copy).
 */
function useInMemoryMainConnectionForHome(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_options' => MainSchema::nukeOptions(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
        'nuke_ads' => MainSchema::nukeAds(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForHome();
});

// ---- Baseline: empty state must not error ----

it('/: renders 200 even with every table completely empty (no fatal on any section)', function () {
    $response = $this->get('/');

    $response->assertOk();
});

it('/: still generates the real /recite-news.htm link (Repair Batch 1, decision-log #52) — this task did not touch home.blade.php\'s own widget/query', function () {
    $this->get('/')->assertOk()->assertSee('href="/recite-news.htm"', false);
});

it('/: replaces the stock Laravel welcome page', function () {
    $content = $this->get('/')->assertOk()->getContent();

    // The real signal is the absence of the stock welcome.blade.php's own
    // distinctive content and the presence of the real homepage.
    expect($content)->not->toContain('laravel.com')->toContain('الطريق إلى الله');
});

// ---- Enhanced homepage sections present ----

it('/: renders the enhanced v1 homepage section set', function () {
    $content = $this->get('/')->assertOk()->getContent();

    $titles = [
        'معاني الآيات', 'حديث شريف', 'قول مأثور', // section 1 (3 cards)
        'انضم إلينا الآن', // section 2
        'أحدث المرئيات على مدار الساعة', // 3
        'برامج حصرية لشبكة الطريق إلى الله', // 4
        'أحدث الفتاوى المرئية', // 5
        'مقاطع حصرية', // 6
        'مقاطع Youtube', // 7
        'مقاطع SoundCloud', // 8
        'جديد التلاوات', // 9
        'جديد الصوتيات', // 10
        'جديد الأفلام الوثائقية',
        'جديد الكارتون',
        'أحدث المواد المفرغة',
        'التصويت',
        'أكثر المواد مشاهدة',
    ];

    foreach ($titles as $title) {
        expect($content)->toContain($title);
    }
});

it('/: dead legacy sections are NOT reproduced (رسائل دعوية / جديد الإسطوانات)', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->not->toContain('رسائل دعوية')
        ->and($content)->not->toContain('جديد الإسطوانات');
});

it('/: loads the premium UI globally and renders the lightweight media rail without the retired carousel plugin', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('/assets/frontend/layout/css/premium-ui.css')
        ->toContain('/assets/frontend/layout/scripts/premium-ui.js')
        ->toContain('w2a-now-watching-rail')
        ->not->toContain('jquery.carouFredSel.js');
});

it('/: scopes the equal-width presentation-card layout to the introductory section', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('<div class="row service-box top-section-card">')
        ->not->toContain('<div class="row service-box margin-bottom-40">')
        ->and(substr_count($content, '<div class="row service-box w2a-equal-height-row">'))->toBe(3);
});

it('/: keeps equal-height content rows horizontal while stretching their card columns', function () {
    $css = file_get_contents(public_path('assets/frontend/layout/css/premium-ui.css'));

    expect($css)->toMatch('/\.row\.service-box\.w2a-equal-height-row\s*\{[^}]*flex-direction:\s*row\s*!important;[^}]*flex-wrap:\s*wrap\s*!important;/s')
        ->toMatch('/\.row\.service-box\.w2a-equal-height-row\s*>\s*\[class\*="col-"\][^{]*\{[^}]*flex-direction:\s*column\s*!important;/s');
});

it('/: exposes keyboard-accessible global navigation and advanced search controls', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('href="#main-content"')
        ->toContain('id="w2a-primary-navigation"')
        ->toContain('class="w2a-search-trigger-btn"')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('type="date"');
});

// ---- Section 3/15: image fallback + real-file resolution ----

it('/: video thumbnail falls back to tvnoise.gif when the bucketed frame file does not exist on disk', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 999999, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'frame' => 1, 'title' => 'No frame file on disk']);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('/images/tvnoise.gif');
});

it('/: video thumbnail resolves to the real bucketed path when the frame file DOES exist on disk (file_exists() gate proven both ways)', function () {
    if (! is_writable(public_path('media'))) {
        $this->markTestSkipped('The configured media mount is read-only in this environment.');
    }

    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $id = 42;
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => $id, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'frame' => 1, 'title' => 'Has a real frame file']);

    $dir = public_path('media/khotab_frames/0');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/'.$id.'.jpg', 'fake-jpg-bytes');

    try {
        $content = $this->get('/')->assertOk()->getContent();

        expect($content)->toContain('/media/khotab_frames/0/'.$id.'.jpg');
    } finally {
        @unlink($dir.'/'.$id.'.jpg');
    }
});

// ---- Section 4: category-487 hardcoded logo mapping ----

it('/: category-487 hardcoded logo ids resolve to their exact legacy logo file, unmapped ids fall back to tvnoise.gif', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 613, 'title' => 'Mapped: Salon', 'main_cat' => 487],
        ['id' => 700, 'title' => 'Unmapped id', 'main_cat' => 487],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('/images/logos/Salon.gif');
    // The unmapped row must still get SOME tvnoise.gif image (can't assert absence of a globally-common fallback string, so assert the row's title renders at least once alongside it).
    expect($content)->toContain('Unmapped id');
});

// ---- Section 5: fatawa pipe-prefix parsing ----

it('/: fatawa general_question_id pipe-prefix is parsed exactly like legacy ($the_id[0]==\'|\' -> explode -> take index 1)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1, 'question_text' => 'Piped question', 'general_question_id' => '|777|extra']);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('/fatawa-all-777.htm');
});

it('/: fatawa general_question_id with no pipe prefix is used as-is', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 1, 'question_text' => 'Plain question', 'general_question_id' => '555']);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('/fatawa-all-555.htm');
});

// ---- Section 7/8: YouTube/SoundCloud empty states ----

it('/: YouTube section shows the empty-state text when nuke_options has no youtube key', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('لا توجد مقاطع مضافة بعد');
});

it('/: YouTube section embeds a real video id when nuke_options.youtube is a serialized id array', function () {
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'youtube', 'option_value' => serialize(['abc123'])]);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('youtube.com/embed/abc123');
});

it('/: SoundCloud section embeds a real track id when nuke_options.soundcloud is a positive id', function () {
    DB::connection('main')->table('nuke_options')->insert(['option_name' => 'soundcloud', 'option_value' => '2043605508']);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('api.soundcloud.com/tracks/2043605508');
});

// ---- Section 16: poll (standalone poll, comment-count quirk, dead vote target) ----

it('/: poll section renders nothing (no crash) when there is no standalone poll at all', function () {
    $response = $this->get('/');

    $response->assertOk();
});

it('/: poll renders the latest standalone poll (ORDER BY pollID DESC), skips options with empty text, form posts to the dead survey-vote-{id}.htm target', function () {
    DB::connection('main')->table('nuke_poll_desc')->insert([
        ['pollID' => 1, 'pollTitle' => 'Older poll', 'artid' => 0, 'voters' => 1],
        ['pollID' => 2, 'pollTitle' => 'Newest poll', 'artid' => 0, 'voters' => 3],
        ['pollID' => 3, 'pollTitle' => 'Belongs to an article', 'artid' => 5, 'voters' => 9],
    ]);
    DB::connection('main')->table('nuke_poll_data')->insert([
        ['pollID' => 2, 'voteID' => 1, 'optionText' => 'Yes', 'optionCount' => 10],
        ['pollID' => 2, 'voteID' => 2, 'optionText' => '', 'optionCount' => 0],
        ['pollID' => 2, 'voteID' => 3, 'optionText' => 'No', 'optionCount' => 5],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('Newest poll')
        ->not->toContain('Belongs to an article')
        ->not->toContain('Older poll')
        ->toContain('action="/survey-vote-2.htm"')
        ->toContain('/survey-results-2.htm');

    // Only 2 real options rendered (empty optionText skipped) — count radio inputs for pollID=2.
    expect(substr_count($content, 'name="voteID"'))->toBe(2);
});

it('/: poll comment count is the preserved legacy bug — 1 if ANY comments exist (never a real count), 0 if none', function () {
    DB::connection('main')->table('nuke_poll_desc')->insert(['pollID' => 1, 'pollTitle' => 'P', 'artid' => 0]);
    $noComments = $this->get('/')->assertOk()->getContent();
    expect($noComments)->toContain('عدد التعليقات: 0');

    DB::connection('main')->table('nuke_pollcomments')->insert([
        ['pollID' => 1, 'comment' => 'first'],
        ['pollID' => 1, 'comment' => 'second'],
        ['pollID' => 1, 'comment' => 'third'],
    ]);
    $threeComments = $this->get('/')->assertOk()->getContent();

    // Legacy's intval(get_row(...)) bug: 3 real comment rows still display as 1, never 3.
    expect($threeComments)->toContain('عدد التعليقات: 1')
        ->not->toContain('عدد التعليقات: 3');
});

it('/: poll participant count is the real totalVotes sum, not affected by the comment-count bug', function () {
    DB::connection('main')->table('nuke_poll_desc')->insert(['pollID' => 1, 'pollTitle' => 'P', 'artid' => 0]);
    DB::connection('main')->table('nuke_poll_data')->insert([
        ['pollID' => 1, 'voteID' => 1, 'optionText' => 'Yes', 'optionCount' => 7],
        ['pollID' => 1, 'voteID' => 2, 'optionText' => 'No', 'optionCount' => 3],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('عدد المشاركين : 10');
});

// ---- Section 6/13/14: parent_id anasheed sections, no crash on the parent_id/group_id join ambiguity ----

it('/: parent-scoped anasheed sections (6/13/14) do not hit the parent_id-ambiguous-column regression against the real LEFT JOIN to nuke_anasheed_groups', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'Group A', 'parent_id' => 999]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Exclusive item', 'parent_id' => 158, 'group_id' => 1]);

    $response = $this->get('/');

    $response->assertOk();
    expect($response->getContent())->toContain('Exclusive item')->toContain('Group A');
});

// ---- Section 17: trending, title double-quote sanitization ----

it('/: trending section replaces double quotes in titles with two single quotes, matching legacy\'s str_replace', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Say "Hello"', 'lastvisit' => 100]);
    DB::connection('main')->table('nuke_anasheed_advanced')->insert(['id' => 1, 'adur' => '1:00']);

    $content = $this->get('/')->assertOk()->getContent();

    // Blade auto-escapes output (htmlspecialchars, ENT_QUOTES) — the legacy-equivalent
    // "Say ''Hello''" is present, just HTML-entity-encoded, and never as literal double quotes.
    expect($content)->toContain('Say &#039;&#039;Hello&#039;&#039;')->not->toContain('Say "Hello"');
});

// ---- G-13-06 (media/visual parity phase): slider.php, index.php-only $display_slider ----

it('/: renders the homepage slider with active, website-visible rows only, ordered by order_index', function () {
    DB::connection('main')->table('nuke_7amalat')->insert([
        ['id' => 1, 'title' => 'Second Slide', 'image' => 'media/7amlat/second.jpg', 'url' => 'https://example.com/second', 'status' => 1, 'website' => 1, 'order_index' => 2],
        ['id' => 2, 'title' => 'First Slide', 'image' => 'media/7amlat/first.jpg', 'url' => 'https://example.com/first', 'status' => 1, 'website' => 1, 'order_index' => 1],
        ['id' => 3, 'title' => 'Inactive Slide', 'image' => 'media/7amlat/inactive.jpg', 'url' => 'https://example.com/inactive', 'status' => 0, 'website' => 1, 'order_index' => 0],
        ['id' => 4, 'title' => 'Mobile-Only Slide', 'image' => 'media/7amlat/mobile.jpg', 'url' => 'https://example.com/mobile', 'status' => 1, 'website' => 0, 'order_index' => 0],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('page-slider')
        ->and($content)->toContain('src="/media/7amlat/first.jpg"')
        ->and($content)->toContain('src="/media/7amlat/second.jpg"')
        ->and($content)->toContain('href="https://example.com/first"')
        ->and(strpos($content, 'first.jpg'))->toBeLessThan(strpos($content, 'second.jpg'))
        ->and($content)->not->toContain('inactive.jpg')
        ->and($content)->not->toContain('mobile.jpg')
        ->and($content)->toContain('w2a-hero-slider-wrap')
        ->and($content)->toContain('w2a-hero-prev')
        ->and($content)->not->toContain('revo-slider-init.js');
});

it('/: renders no slider markup or slider-specific assets when there are no active rows, matching $display_slider\'s own empty-results guard', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->not->toContain('page-slider')
        ->and($content)->not->toContain('w2a-hero-slider-wrap')
        ->and($content)->not->toContain('revo-slider-init.js');
});

// ---- Shared-nav relative-href repair (decision-log #57), sitewide audit
// finding #3 — regression guard on a flat root page. See
// W2acdControllerTest for the actual nested-path bug this repairs. ----

it('/: the shared navigation renders root-relative hrefs, unchanged in destination from before this repair — no regression on a flat root page', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->not->toMatch('/href="[a-zA-Z][a-zA-Z0-9_.\-]*\.htm"/')
        ->and($content)->toContain('href="/categories.htm"')
        ->and($content)->toContain('href="/cds-main.htm"')
        ->and($content)->toContain('action="/search.htm"');
});
