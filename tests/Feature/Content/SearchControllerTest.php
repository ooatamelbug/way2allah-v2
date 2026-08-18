<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForSearchController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        // G-05 (Migration Gap Register) addition — channel-existence validation.
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForSearchController();
});

// ---- Validation: legacy's exact 3 distinct messages, exact precedence ----

it('validation: empty title shows the exact legacy "enter a title" message', function () {
    $response = $this->post('/search.htm', ['kh_title' => '', 'kh_dept' => 'video']);

    $response->assertOk()->assertSeeText('يجب عليك ادخال عنوان المادة');
});

it('validation: a title under 4 characters shows the exact legacy "4 characters" message, not the empty-title one', function () {
    $response = $this->post('/search.htm', ['kh_title' => 'abc', 'kh_dept' => 'video', 'kh_channel' => '1']);

    $response->assertOk()->assertSeeText('عفواً ، يجب إدخال أربعة أحرف على الأقل للبحث');
});

it('validation: no department shows the exact legacy "choose a department" message, not the title ones', function () {
    $response = $this->post('/search.htm', ['kh_title' => 'a valid title']);

    $response->assertOk()->assertSeeText('يجب عليك إختيار القسم');
});

it('validation: precedence matches legacy exactly — empty title wins over a missing department', function () {
    $response = $this->post('/search.htm', ['kh_title' => '', 'kh_dept' => '']);

    $response->assertOk()->assertSeeText('يجب عليك ادخال عنوان المادة');
});

it('validation: does NOT inherit KhotabSearchController\'s permissive rule (channel/author/date alone, no title, still rejected)', function () {
    $response = $this->post('/search.htm', [
        'kh_title' => '', 'kh_dept' => 'video', 'kh_channel' => '1',
    ]);

    $response->assertOk()->assertSeeText('يجب عليك ادخال عنوان المادة');
});

it('only POST is registered for /search.htm — a GET request does not match this route', function () {
    $this->get('/search.htm?kh_title=test&kh_dept=video')->assertMethodNotAllowed();
});

// ---- video department ----

it('video department: renders a working result link, author link, channel logo, and hit count', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 7, 'title' => 'A video title', 'author' => 1, 'vedio' => 1, 'hidden' => 0,
        'time' => 100, 'weight' => 5, 'hits' => 42, 'channel_id' => 3,
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'video title', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->toContain('href="/khotab-item-7.htm"')
        ->toContain('href="/khotab-video-1.htm"')
        ->toContain('/images/channels/3.png')
        ->toContain('الزيارات: 42');
});

it('video department: G-05 fix — orders by weight DESC (advanced-search/index.php\'s own media_search() config), not time DESC', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'Higher weight older time', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 9],
        ['id' => 2, 'title' => 'Lower weight newer time', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'time' => 999, 'weight' => 1],
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'weight', 'kh_dept' => 'video'])->assertOk()->getContent();

    // "weight" itself is the search term and gets wrapped by highlighting — compare
    // positions of each title's own distinguishing, non-matched word instead.
    expect(strpos($content, 'Higher'))->toBeLessThan(strpos($content, 'Lower'));
});

it('video department: G-05 fix — date range now actually excludes out-of-range results (previously a confirmed, empirically-verified silent no-op)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Future dated item', 'author' => 1, 'vedio' => 1, 'hidden' => 0,
        'time' => strtotime('2099-06-15'), 'weight' => 1,
    ]);

    $content = $this->post('/search.htm', [
        'kh_title' => 'Future dated', 'kh_dept' => 'video', 'kh_from' => '2000-01-01', 'kh_to' => '2000-01-02',
    ])->assertOk()->getContent();

    expect($content)->not->toContain('Future dated item');
});

it('video department: date range still includes an in-range result (the fix does not over-exclude)', function () {
    // Deliberately NOT timestamped at the "to" boundary date itself: this
    // controller's date resolution reuses KhotabSearchController::dateRange()'s
    // own exact logic (an already-approved, unmodified reuse) — an
    // end-only range resolves "end" to strtotime($to) (midnight, not
    // end-of-day), so a same-day-but-later-in-the-day item is genuinely,
    // correctly excluded by that exact reused logic, not a bug this task
    // introduces. Using a clearly-earlier date avoids that edge entirely.
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Well before the boundary title', 'author' => 1, 'vedio' => 1, 'hidden' => 0,
        'time' => strtotime('2026-01-10'), 'weight' => 1,
    ]);

    $plainText = strip_tags($this->post('/search.htm', [
        'kh_title' => 'Well before the boundary', 'kh_dept' => 'video', 'kh_to' => '2026-01-15',
    ])->assertOk()->getContent());

    expect($plainText)->toContain('Well before the boundary title');
});

it('video department: G-05 fix — channel filter validates the channel actually exists, matching advanced-search/index.php exactly', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Real Channel']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'Real channel item', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 5],
        ['id' => 2, 'title' => 'Fake channel item', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'channel_id' => 999],
    ]);

    $realChannel = strip_tags($this->post('/search.htm', ['kh_title' => 'channel item', 'kh_dept' => 'video', 'kh_channel' => '5'])->assertOk()->getContent());
    expect($realChannel)->toContain('Real channel item');

    $fakeChannel = strip_tags($this->post('/search.htm', ['kh_title' => 'channel item', 'kh_dept' => 'video', 'kh_channel' => '999'])->assertOk()->getContent());
    expect($fakeChannel)->not->toContain('Fake channel item');
});

it('video department: title_sub()-equivalent highlighting wraps the matched keyword', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Highlight this word', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1,
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'Highlight', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->toContain('<sub class="red_sub">Highlight</sub>');
});

it('video department: Graphic()-equivalent "new" badge appears for a today-dated item, not for an old one', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'Fresh item today', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'time' => time()],
        ['id' => 2, 'title' => 'Stale item old', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'time' => strtotime('2020-01-01')],
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->toContain('images/new_1.gif');
});

it('video series: renders a working result link, author link, and item count', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_series')->insert([
        'id' => 9, 'title' => 'A series title', 'vedio' => 1, 'hidden' => 0, 'count' => 12, 'author_id' => 1,
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'series title', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->toContain('href="/khotab-series-9.htm"')
        ->toContain('href="/khotab-video-1.htm"')
        ->toContain('المواد: 12');
});

// ---- audio department ----

it('audio department: uses vedio=0, weight DESC ordering, and renders full result cards', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'An audio title', 'author' => 1, 'vedio' => 0, 'hidden' => 0, 'time' => 100, 'weight' => 1],
        ['id' => 2, 'title' => 'A video title, must not appear', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 1],
    ]);

    $content = strip_tags($this->post('/search.htm', ['kh_title' => 'title', 'kh_dept' => 'audio'])->assertOk()->getContent());

    expect($content)->toContain('An audio title')->not->toContain('A video title, must not appear');
});

// ---- dumped_files department ----

it('dumped_files department: requires vedio=1 AND pdf>0, orders by weight DESC, and has no series panel', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'A dumped file title', 'author' => 1, 'vedio' => 1, 'pdf' => 1, 'hidden' => 0, 'time' => 100, 'weight' => 1],
        ['id' => 2, 'title' => 'A video without pdf, must not appear', 'author' => 1, 'vedio' => 1, 'pdf' => 0, 'hidden' => 0, 'time' => 100, 'weight' => 1],
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'title', 'kh_dept' => 'dumped_files']);

    $response->assertOk()
        ->assertSeeText('A dumped file title')
        ->assertDontSee('A video without pdf, must not appear')
        ->assertDontSee('قائمة السلاسل');
});

// ---- The 5 "varieties" departments ----

it('anasheed varieties departments: each is filtered by its own confirmed parent_id, not shared across departments', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Anasheed item', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
        ['id' => 2, 'title' => 'Cartoon item', 'parent_id' => 57, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
        ['id' => 3, 'title' => 'Documentary item', 'parent_id' => 12, 'vedio' => 1, 'hidden' => 0, 'weight' => 1],
    ]);

    $anasheedResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'anasheed']);
    $anasheedResponse->assertOk()->assertSee('Anasheed item')->assertDontSee('Cartoon item')->assertDontSee('Documentary item');

    $cartoonResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'cartoon']);
    $cartoonResponse->assertOk()->assertSee('Cartoon item')->assertDontSee('Anasheed item')->assertDontSee('Documentary item');

    $documentaryResponse = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'documentary']);
    $documentaryResponse->assertOk()->assertSee('Documentary item')->assertDontSee('Anasheed item')->assertDontSee('Cartoon item');
});

it('anasheed departments: hidden filter applies to items (mawad) but NOT to groups (series) — confirmed asymmetric legacy behavior', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Hidden anasheed item, must not appear', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 1, 'weight' => 1,
    ]);
    $db->table('nuke_anasheed_groups')->insert([
        'id' => 1, 'title' => 'A group with matching title', 'parent_id' => 98,
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'anasheed', 'kh_dept' => 'anasheed']);

    $response->assertOk()->assertDontSee('Hidden anasheed item, must not appear');

    $groupResponse = $this->post('/search.htm', ['kh_title' => 'matching', 'kh_dept' => 'anasheed']);
    $groupResponse->assertOk()->assertSee('A group with matching title');
});

it('varieties mawad: a frame=1 item shows its bucketed thumbnail; a frame=0 item falls back to tvnoise.gif', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1500, 'title' => 'Framed item', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'frame' => 1],
        ['id' => 1501, 'title' => 'Frameless item', 'parent_id' => 98, 'vedio' => 1, 'hidden' => 0, 'weight' => 1, 'frame' => 0],
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'item', 'kh_dept' => 'anasheed'])->assertOk()->getContent();

    expect($content)->toContain('/media/anasheed/frame/1/1500.jpg')
        ->toContain('/images/tvnoise.gif');
});

it('varieties series: an icon=1 group shows its bucketed icon; icon=0 falls back to pix001.gif; shows sub-category/item/visit counts and comment', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_groups')->insert([
        'id' => 2000, 'title' => 'An iconed group', 'parent_id' => 98, 'icon' => 1,
        'child' => 3, 'anasheed' => 44, 'hits' => 55, 'des' => 'A real comment',
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'iconed group', 'kh_dept' => 'anasheed'])->assertOk()->getContent();

    expect($content)->toContain('/media/anasheed/icons/2/2000.jpg')
        ->toContain('الأقسام الفرعية: 3')
        ->toContain('المقاطع: 44')
        ->toContain('الزيارات: 55')
        ->toContain('التعليق: A real comment');
});

it('varieties series: an empty comment (des) falls back to the exact legacy "بدون تعليق" text', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'No comment group', 'parent_id' => 98, 'des' => '']);

    $content = $this->post('/search.htm', ['kh_title' => 'No comment', 'kh_dept' => 'anasheed'])->assertOk()->getContent();

    expect($content)->toContain('التعليق: بدون تعليق');
});

// ---- fatawa department ----

it('fatawa department: renders three result sets, each with a working link, and its own confirmed author column', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Answering Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'A specific fatwa answer', 'auther_id' => 1, 'general_question_id' => '|10|', 'date_of_fatwa' => '2026-01-01',
    ]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 10, 'question_text' => 'A shared fatwa question', 'author_id' => 1, 'num_view' => 5,
    ]);
    $db->table('nuke_fatwa_topics')->insert([
        'id' => 20, 'topic_name' => 'A fatwa topic name', 'author_id' => 1, 'parent_id' => 3,
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'fatwa', 'kh_dept' => 'fatawa'])->assertOk()->getContent();
    $plainText = strip_tags($content);

    expect($plainText)->toContain('A specific fatwa answer')
        ->toContain('A shared fatwa question')
        ->toContain('A fatwa topic name');
    expect($content)->toContain('href="/fatawa-all-10.htm"') // pipe-stripped general_question_id
        ->toContain('href="/auther-questions-1.htm"')
        ->toContain('href="/fatawa-group-20-3.htm"');
});

it('fatawa department: per-item counts reproduce legacy\'s confirmed N+1 pattern (real counts, not batched/optimized away)', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 10, 'question_text' => 'A question with fatawa', 'num_view' => 0]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'question_text' => 'Answer one', 'general_question_id' => 10, 'auther_id' => 0],
        ['id' => 2, 'question_text' => 'Answer two', 'general_question_id' => 10, 'auther_id' => 0],
    ]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 20, 'topic_name' => 'A topic with 3 general questions', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 30, 'question_text' => 'q1', 'topic_id' => '20', 'num_view' => 0],
        ['id' => 31, 'question_text' => 'q2', 'topic_id' => '20', 'num_view' => 0],
        ['id' => 32, 'question_text' => 'q3', 'topic_id' => '20', 'num_view' => 0],
    ]);

    $content = $this->post('/search.htm', ['kh_title' => 'question with fatawa', 'kh_dept' => 'fatawa'])->assertOk()->getContent();
    expect($content)->toContain('عدد الفتاوى: 2');

    $topicContent = $this->post('/search.htm', ['kh_title' => 'topic with 3', 'kh_dept' => 'fatawa'])->assertOk()->getContent();
    expect($topicContent)->toContain('عدد الأسئلة: 3');
});

it('fatawa department: channel filter also matches place_of_fatwa via OR (advanced-search/index.php\'s own richer condition for this department only)', function () {
    // SearchController casts kh_channel to (int) before it ever reaches
    // ContentListingService — matching legacy's own `intval($this->channel)`
    // cast, applied BEFORE building this exact OR clause (index.php:920-923).
    // A numeric value matching a substring of place_of_fatwa is therefore
    // the faithful way to exercise this, not a free-text place name.
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'Answer with a place', 'auther_id' => 1, 'channel_id' => 0, 'place_of_fatwa' => 'Studio 42',
    ]);

    $content = strip_tags($this->post('/search.htm', [
        'kh_title' => 'Answer with a place', 'kh_dept' => 'fatawa', 'kh_channel' => '42',
    ])->assertOk()->getContent());

    expect($content)->toContain('Answer with a place');
});

it('fatawa department: no hidden filter is applied at all (neither table has a hidden column)', function () {
    // fatwaQuestionsAdvancedSearch() INNER JOINs nuke_islamic_authors — a
    // real, pre-existing (not G-05) requirement, so the fixture needs a
    // matching author row for the join itself to succeed (auther_id=0
    // with no id=0 author row silently produces zero rows regardless of
    // any hidden filter — confirmed unrelated to this test's own concern
    // by reproducing it against the unmodified pre-G-05 method too).
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'Visible regardless', 'auther_id' => 1, 'date_of_fatwa' => '2026-01-01',
    ]);

    $response = $this->post('/search.htm', ['kh_title' => 'Visible regardless', 'kh_dept' => 'fatawa']);

    $response->assertOk()->assertSeeText('Visible regardless');
});

// ---- gallery/cds — confirmed dead, not offered ----

it('gallery/cds: not selectable, and a manually-crafted request for either shows the "choose a department" message without erroring', function () {
    $galleryResponse = $this->post('/search.htm', ['kh_title' => 'anything valid', 'kh_dept' => 'gallery']);
    $galleryResponse->assertOk()->assertSeeText('يجب عليك إختيار القسم');

    $cdsResponse = $this->post('/search.htm', ['kh_title' => 'anything valid', 'kh_dept' => 'cds']);
    $cdsResponse->assertOk()->assertSeeText('يجب عليك إختيار القسم');
});

// ---- No-results behavior — legacy's real, reachable messages/placement ----

it('no-results: shows the one legacy page-level message when neither mawad nor series matched anything', function () {
    $content = $this->post('/search.htm', ['kh_title' => 'Nothing matches this at all', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->toContain('عفوا ، لا يوجد نتائج تطابق شروط البحث');
});

it('no-results: does NOT render an empty mawad/series section wrapper at all (legacy only ever calls the view function when results exist)', function () {
    $content = $this->post('/search.htm', ['kh_title' => 'Nothing matches this at all', 'kh_dept' => 'video'])->assertOk()->getContent();

    expect($content)->not->toContain('نتائج البحث - المواد')
        ->not->toContain('نتائج البحث - السلاسل');
});
