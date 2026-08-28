<?php

use App\Domain\Content\Mail\FatwaFriendMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForFatwaQuestionController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaQuestionController();
});

it('show: renders one specific scholar answer and does NOT increment any view counter (single.php has none)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 50, 'topic_id' => 10, 'general_question_id' => '|100|',
        'question_text' => 'One specific answer', 'auther_id' => 1,
        'answer_text' => 'The answer text', 'num_view' => 0,
    ]);

    $response = $this->get('/fatawa-50.htm');

    $response->assertOk()->assertSee('One specific answer')->assertSee('The answer text');

    // num_view must remain exactly 0 — confirmed, single.php never
    // increments any counter.
    expect(DB::connection('main')->table('nuke_fatwa_questions')->find(50)->num_view)->toBe(0);
});

it('show: 404s for a nonexistent question', function () {
    $this->get('/fatawa-999.htm')->assertNotFound();
});

it('showAll: lists every scholar answer for the general question and atomically increments num_view', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'First Shaikh', 'prename' => 'Dr.'],
        ['id' => 2, 'name' => 'Second Shaikh', 'prename' => 'Sheikh'],
    ]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 100, 'question_text' => 'Shared question', 'topic_id' => '|10|', 'num_view' => 5,
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'general_question_id' => '|100|', 'auther_id' => 1, 'answer_text' => 'Answer one'],
        ['id' => 2, 'general_question_id' => '|100|', 'auther_id' => 2, 'answer_text' => 'Answer two'],
        ['id' => 3, 'general_question_id' => '|200|', 'auther_id' => 1, 'answer_text' => 'Unrelated question'],
    ]);

    $response = $this->get('/fatawa-all-100.htm');

    $response->assertOk()
        ->assertSee('Answer one')
        ->assertSee('Answer two')
        ->assertSee('First Shaikh')
        ->assertSee('Second Shaikh')
        ->assertDontSee('Unrelated question');

    expect(DB::connection('main')->table('nuke_fatwa_general_questions')->find(100)->num_view)->toBe(6);
});

it('showAll: 404s for a nonexistent general question', function () {
    $this->get('/fatawa-all-999.htm')->assertNotFound();
});

// ---- auther-all-fatawa-{author}-{generalQuestion}.htm — BUSINESS_REPAIR
// (decision-log #51). Reuses showAll()'s exact rendering path, filtered by
// author — not a separate design, not recovered legacy behavior. ----

it('showAllForAuthor: renders only the requested author\'s answer, excluding other scholars\' answers to the same general question (the critical multi-author proof)', function () {
    // Deliberately fictional author names (not real production scholar
    // names like الحنبلي/السرساوى) — the shared layout's sitewide
    // w2a_autocomplete/authors.txt include contains real production author
    // names regardless of test DB fixtures, which would make an
    // assertDontSee() on a REAL name a false-negative-proof (it can appear
    // in that unrelated sitewide data even when correctly excluded from
    // this page's own $answers). Fictional names avoid that collision.
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 79, 'name' => 'TestScholarSeventyNine', 'prename' => 'Sheikh'],
        ['id' => 225, 'name' => 'TestScholarTwoTwentyFive', 'prename' => 'Sheikh'],
    ]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 10007, 'question_text' => 'Photography (multi-scholar example)', 'topic_id' => '|10|', 'num_view' => 0,
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'general_question_id' => '|10007|', 'auther_id' => 79, 'answer_text' => 'Answer by author 79'],
        ['id' => 2, 'general_question_id' => '|10007|', 'auther_id' => 225, 'answer_text' => 'Answer by author 225'],
    ]);

    // The author-scoped route: only author 79's own answer, and no other
    // scholar's answer to the SAME general question.
    $scoped = $this->get('/auther-all-fatawa-79-10007.htm');
    $scoped->assertOk()
        ->assertSee('Answer by author 79')
        ->assertSee('TestScholarSeventyNine')
        ->assertDontSee('Answer by author 225')
        ->assertDontSee('TestScholarTwoTwentyFive');

    // The pre-existing unscoped route must remain completely unaffected —
    // both answers, both authors, still visible.
    $unscoped = $this->get('/fatawa-all-10007.htm');
    $unscoped->assertOk()
        ->assertSee('Answer by author 79')
        ->assertSee('Answer by author 225')
        ->assertSee('TestScholarSeventyNine')
        ->assertSee('TestScholarTwoTwentyFive');
});

it('showAllForAuthor: 17/1924 and 17/3710 — the exact pairs from the original investigation — both resolve, both correctly scoped', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 17, 'name' => 'الحويني', 'prename' => 'الشيخ']);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 1924, 'question_text' => 'Question 1924', 'topic_id' => '|10|'],
        ['id' => 3710, 'question_text' => 'Question 3710', 'topic_id' => '|10|'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'general_question_id' => '|1924|', 'auther_id' => 17, 'answer_text' => 'Answer 1924'],
        ['id' => 2, 'general_question_id' => '|3710|', 'auther_id' => 17, 'answer_text' => 'Answer 3710'],
    ]);

    $this->get('/auther-all-fatawa-17-1924.htm')->assertOk()->assertSee('Answer 1924');
    $this->get('/auther-all-fatawa-17-3710.htm')->assertOk()->assertSee('Answer 3710');
});

it('showAllForAuthor: 404s for a nonexistent author', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'Q', 'topic_id' => '|10|']);

    $this->get('/auther-all-fatawa-999-100.htm')->assertNotFound();
});

it('showAllForAuthor: 404s for a nonexistent general question', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 17, 'name' => 'الحويني']);

    $this->get('/auther-all-fatawa-17-999999.htm')->assertNotFound();
});

it('showAllForAuthor: valid author + valid general question with no relationship renders an empty answers area, not a 404 or invented fallback content', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 17, 'name' => 'الحويني'],
        ['id' => 79, 'name' => 'الحنبلي'],
    ]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 500, 'question_text' => 'Someone else\'s question', 'topic_id' => '|10|']);
    // Only author 79 answered this general question — author 17 never did.
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'general_question_id' => '|500|', 'auther_id' => 79, 'answer_text' => 'Answer by 79',
    ]);

    $response = $this->get('/auther-all-fatawa-17-500.htm');

    $response->assertOk()->assertDontSee('Answer by 79');
});

it('auther-questions-{author}.htm still generates the historical auther-all-fatawa link, unaffected by this repair', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 17, 'name' => 'الحويني', 'prename' => 'الشيخ']);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 1924, 'question_text' => 'Question 1924']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 17, 'general_question_id' => '|1924|']);

    $this->get('/auther-questions-17.htm')
        ->assertOk()
        ->assertSee('/auther-all-fatawa-17-1924.htm', false);
});

// ---- fatawa-all-{id}.htm owner-approved answer2.php reconstruction ----
// DISPATCH_ORIGIN_UNKNOWN (modules.php missing — see the reconstruction
// report); answer2.php is the OWNER-APPROVED presentation reference, not
// a proven historic handler. These assert the DOM contract that
// distinguishes answer2.php from answer.php, not just text presence.
// beforeEach() above already sets up nuke_w2a_cat/nuke_fatwa_topics/
// nuke_fatwa_general_questions/nuke_fatwa_questions/nuke_islamic_authors/
// nuke_sat_channels — no separate schema setup needed here.

function seedFatwaAllParityFixture(): void
{
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root Category', 'main_cat' => 0],
        ['id' => 2, 'title' => 'Sub Category', 'main_cat' => 1],
    ]);
    $db->table('nuke_fatwa_topics')->insert([
        'id' => 10, 'topic_name' => 'Zakat Topic', 'parent_id' => 2,
    ]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 100, 'question_text' => 'Shared question', 'description' => 'Extra general notes',
        'topic_id' => '|10|', 'num_view' => 5,
    ]);
    $db->table('nuke_sat_channels')->insert(['id' => 7, 'title' => 'Iqraa Channel']);
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'First Shaikh', 'prename' => 'Dr.'],
        ['id' => 2, 'name' => 'Second Shaikh', 'prename' => 'Sheikh'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        [
            'id' => 1, 'general_question_id' => '|100|', 'topic_id' => 10, 'auther_id' => 1, 'channel_id' => 7,
            'question_text' => 'Individual question one', 'answer_text' => 'Answer one',
            'date_of_fatwa' => '2020-05-10', 'db_insertion_date' => 1690000000,
            'media_size' => 12, 'num_download' => 3,
        ],
        [
            'id' => 2, 'general_question_id' => '|100|', 'topic_id' => 10, 'auther_id' => 2, 'channel_id' => 0,
            'question_text' => 'Individual question two', 'answer_text' => '.',
            'date_of_fatwa' => '0000-00-00', 'db_insertion_date' => 0,
            'media_size' => 0, 'num_download' => 0,
        ],
    ]);
}

it('showAll: document title is "سؤال | {question}", answer2.php\'s own header() title', function () {
    seedFatwaAllParityFixture();

    $response = $this->get('/fatawa-all-100.htm');

    $response->assertOk()->assertSee('<title>سؤال | Shared question', false);
});

it('showAll: renders page_bar()\'s own empty <h1 style=""> chrome and breadcrumb, not <x-page-chrome>', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('<h1 style=""></h1>');
    expect($content)->toContain('<div class="page-bar">');
    expect($content)->toContain('<a href="/fatawa.htm">الفتاوى المرئية </a>');
    // Category ancestor chain, root-first: Root Category then Sub Category.
    expect(strpos($content, 'Root Category'))->toBeLessThan(strpos($content, 'Sub Category'));
    expect($content)->toContain('موضوع Zakat Topic');
    // Self-link breadcrumb item — page_bar()'s own confirmed self-reference.
    expect($content)->toContain('<a href="/fatawa-all-100.htm">Shared question </a>');
});

it('showAll: category breadcrumb links include the required {page} segment (Repair Batch 1, decision-log #52) — the old 1-segment URL is no longer generated', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)
        ->toContain('/fatawa-topics-1-1.htm')
        ->toContain('/fatawa-topics-2-1.htm')
        ->not->toContain('href="/fatawa-topics-1.htm"')
        ->not->toContain('href="/fatawa-topics-2.htm"');

    // The corrected URL must actually resolve, not just look plausible.
    $this->get('/fatawa-topics-1-1.htm')->assertOk();
    $this->get('/fatawa-topics-2-1.htm')->assertOk();
});

it('showAll: reproduces answer2.php\'s two-column table row (not answer.php\'s stacked colspan row)', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('<th width="25%" class="w20" style="border-top:0;">السؤال </th>');
    expect($content)->not->toContain('colspan="2"');
});

it('showAll: uses answer2.php\'s own answer-p class (not answer.php\'s answer-pXX answer)', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('class="answer-p"');
    expect($content)->not->toContain('answer-pXX');
});

it('showAll: the icon/action row appears AFTER the details table, answer2.php\'s own ordering (not answer.php\'s before-table ordering)', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    $tablePos = strpos($content, '</table>');
    $iconRowPos = strpos($content, 'jumbotron-icon');

    expect($tablePos)->not->toBeFalse();
    expect($iconRowPos)->toBeGreaterThan($tablePos);
});

it('showAll: skips the answer row entirely when answer_text is empty or the literal "." placeholder', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('Answer one');
    expect(substr_count($content, 'class="answer-p"'))->toBe(1);
});

it('showAll: renders the real channel title as "مكان إصدار الفتوي" when a channel exists, falling back to "بدون قناه" when it does not', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('<a href="/fatawa-channel-7.htm">Iqraa Channel</a>');
    expect($content)->toContain('<a href="/fatawa-channel-0.htm"> بدون قناه </a>');
});

it('showAll: date_of_fatwa "0000-00-00" renders as "غير معلوم", a real date renders via ArabicDateConverter', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('غير معلوم');
    expect($content)->toContain('مايو'); // 2020-05-10 -> May
});

it('showAll: "عدد الزيارات" renders the shared general question\'s num_view on every row, not each answer\'s own uncounted column', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    // num_view was seeded at 5. recordView() dispatches an atomic
    // query-builder increment (RecordsView listener) that updates the DB
    // row directly without refreshing $generalQuestionModel's in-memory
    // attribute — the same "stale by one" display legacy itself has
    // (answer2.php's $allquestions is fetched once, before its own
    // UPDATE runs, and never re-fetched before rendering). Both answer
    // rows show the same shared pre-increment value: real parity, not a
    // bug — confirmed separately against the DB's actual post-increment
    // value in the "view-count behavior" test below.
    expect(substr_count($content, '5 زيارة'))->toBe(2);
});

it('showAll: "مشاهدة المادة" is wired to /media-player addressed by the answer\'s real id, not a page-ordinal counter', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain("w2a_play(1, 'fatawa')");
    expect($content)->toContain("w2a_play(2, 'fatawa')");
});

it('showAll: "حفظ المادة" links to the already-existing download route for each answer', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('href="/fatawa-download-1.htm"');
    expect($content)->toContain('href="/fatawa-download-2.htm"');
});

it('showAll: the send-friend modal posts to the real, already-tested sendToFriend route for each answer', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('fatawa-friend-sendemail-1.htm" method="post"');
    expect($content)->toContain('fatawa-friend-sendemail-2.htm" method="post"');
});

it('showAll: renders the general question\'s own description block when present', function () {
    seedFatwaAllParityFixture();

    $this->get('/fatawa-all-100.htm')->assertSee('Extra general notes');
});

it('showAll: omits the description portlet entirely when the general question has none', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 200, 'question_text' => 'No description here', 'num_view' => 0]);
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 5, 'general_question_id' => '|200|', 'auther_id' => 1, 'answer_text' => 'An answer',
    ]);

    $this->get('/fatawa-all-200.htm')->assertDontSee('<p></p>', false);
});

it('showAll: renders the category-scoped "الأكثر تحميلا"/"جديد المواد" sidebar when a category resolves', function () {
    seedFatwaAllParityFixture();
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 9, 'topic_id' => 10, 'general_question_id' => '|900|',
        'question_text' => 'Most downloaded sidebar item', 'num_download' => 99,
    ]);

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->toContain('الأكثر تحميلا');
    expect($content)->toContain('جديد المواد');
    expect($content)->toContain('Most downloaded sidebar item');
    expect($content)->toContain('href="/fatawa-all-900.htm#9"');
});

it('showAll: omits the sidebar entirely when the general question\'s topic never resolves to a category', function () {
    $db = DB::connection('main');
    // topic_id resolves to 0 -> no topic row -> categoryId stays 0.
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 300, 'question_text' => 'No topic', 'num_view' => 0]);
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 6, 'general_question_id' => '|300|', 'auther_id' => 1, 'answer_text' => 'An answer',
    ]);

    $content = $this->get('/fatawa-all-300.htm')->getContent();

    expect($content)->not->toContain('الأكثر تحميلا');
    expect($content)->not->toContain('aria-label="الشريط الجانبي"');
});

it('showAll: admin controls (adminAnswerControls/adminAnswerMoreControls) are never rendered — ADMIN_ONLY, no migrated admin panel exists', function () {
    seedFatwaAllParityFixture();

    $content = $this->get('/fatawa-all-100.htm')->getContent();

    expect($content)->not->toContain('admin_control_box');
    expect($content)->not->toContain('inlineAdminUrl');
});

it('showAll: view-count behavior is unaffected by the presentation reconstruction — still atomic, still exactly +1', function () {
    seedFatwaAllParityFixture();

    $this->get('/fatawa-all-100.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_fatwa_general_questions')->find(100)->num_view)->toBe(6);
});

it('download: atomically increments num_download and redirects to media_link', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 7, 'media_link' => 'https://way2allah.com/files/answer7.mp4', 'num_download' => 2,
    ]);

    $response = $this->get('/fatawa-download-7.htm');

    $response->assertRedirect('https://way2allah.com/files/answer7.mp4');
    expect(DB::connection('main')->table('nuke_fatwa_questions')->find(7)->num_download)->toBe(3);
});

it('download: 404s for a nonexistent question', function () {
    $this->get('/fatawa-download-999.htm')->assertNotFound();
});

// ---- G-07-01: fix_archive_links() reproduction (Phase 1 audit finding) ----

it('download: an archive.org CDN-node media_link is rewritten to the canonical download URL, matching fix_archive_links() exactly', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 8, 'media_link' => 'https://ia802302.us.archive.org/2/items/fatawaa-98/24.mp4', 'num_download' => 0,
    ]);

    // Real olddb shape (ids 13636/13638/13791) confirmed during the Phase 1
    // audit — the only 3 rows (of 13,353) where fix_archive_links()
    // produces a genuinely different URL than the input.
    $this->get('/fatawa-download-8.htm')
        ->assertRedirect('http://www.archive.org/download/fatawaa-98/24.mp4');
});

it('download: a media_link already in the canonical archive.org/download form passes through unchanged', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 9, 'media_link' => 'http://www.archive.org/download/fatawa_way2allah-1/001.avi', 'num_download' => 0,
    ]);

    $this->get('/fatawa-download-9.htm')
        ->assertRedirect('http://www.archive.org/download/fatawa_way2allah-1/001.avi');
});

it('download: a non-archive.org media_link is never touched by the transformation', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 10, 'media_link' => 'https://way2allah.com/files/answer10.mp4', 'num_download' => 0,
    ]);

    $this->get('/fatawa-download-10.htm')
        ->assertRedirect('https://way2allah.com/files/answer10.mp4');
});

it('sendToFriend: validates required fields with legacy\'s own 2-character-minimum name rule', function () {
    Mail::fake();

    $db = DB::connection('main');
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'question_text' => 'Q']);

    $response = $this->post('/fatawa-friend-sendemail-1.htm', [
        'your_name' => 'A',
        'your_email' => 'not-an-email',
        'friend_name' => '',
        'friend_email' => 'friend@example.com',
    ]);

    $response->assertSessionHasErrors(['your_name', 'your_email', 'friend_name']);
    Mail::assertNothingSent();
});

it('sendToFriend: sends FatwaFriendMail (not raw mail()) with the confirmed subject/content shape, on valid input', function () {
    Mail::fake();

    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'question_text' => 'A real fatwa question', 'auther_id' => 1,
    ]);

    $response = $this->post('/fatawa-friend-sendemail-1.htm', [
        'your_name' => 'Ahmed',
        'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami',
        'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk();

    Mail::assertSent(FatwaFriendMail::class, function (FatwaFriendMail $mail) {
        return $mail->hasTo('sami@example.com')
            && $mail->friendName === 'Sami'
            && $mail->yourName === 'Ahmed'
            && $mail->fatwaQuestion->id === 1;
    });
});

it('sendToFriend: 404s for a nonexistent question', function () {
    Mail::fake();

    $this->post('/fatawa-friend-sendemail-999.htm', [
        'your_name' => 'Ahmed', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ])->assertNotFound();
});
