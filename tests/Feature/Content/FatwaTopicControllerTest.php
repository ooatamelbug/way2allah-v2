<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForFatwaTopicController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaTopicController();
});

it('index: lists only top-level categories with q_count > 0', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root With Questions', 'main_cat' => 0, 'q_count' => 5],
        ['id' => 2, 'title' => 'Root Without Questions', 'main_cat' => 0, 'q_count' => 0],
        // main_cat=1 (not a root) with q_count>0 — excluded from the main
        // topic list (this test's own point) but, per G-07-02, legitimately
        // DOES appear in the unrelated sidebar widgets below (legacy's own
        // tasnifat_latestadd()/tasnifat_active() have no main_cat filter) —
        // so the assertion is scoped to the main list's own portlet, not
        // page-wide, to avoid a false conflict with that separate fix.
        ['id' => 3, 'title' => 'Not A Root', 'main_cat' => 1, 'q_count' => 5],
    ]);

    $content = $this->get('/fatawa.htm')->assertOk()->getContent();

    preg_match('/<div class="portlet-body">(.*?)<\/div>/s', $content, $matches);
    $mainList = $matches[1] ?? '';

    expect($mainList)->toContain('Root With Questions')
        ->not->toContain('Root Without Questions')
        ->not->toContain('Not A Root');
});

// ---- G-07-02: fatawa.htm's 2 sidebar widgets must NOT be main_cat=0-only
// (Phase 1 audit finding — legacy's tasnifat_latestadd()/tasnifat_active()
// query the whole nuke_w2a_cat table, any nesting level) ----

it('index: "latest added categories" sidebar includes non-top-level categories, ordered by id DESC, not restricted to main_cat=0', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Top Level', 'main_cat' => 0, 'q_count' => 1],
        // Higher id, but a SUB-category (main_cat != 0) — legacy's own
        // tasnifat_latestadd() has no main_cat filter, so this must still
        // appear, and appear BEFORE the top-level row (ORDER BY id DESC).
        ['id' => 2, 'title' => 'Sub Category Newer', 'main_cat' => 1, 'q_count' => 1],
    ]);

    $content = $this->get('/fatawa.htm')->assertOk()->getContent();

    // "Top Level" (main_cat=0, q_count>0) also legitimately appears in the
    // main topic list above the sidebar — scoped to just this sidebar box
    // so that unrelated earlier occurrence can't affect the ordering check.
    preg_match('/احدث التصنيفات المضافة.*?<ul class="news">(.*?)<\/ul>/s', $content, $matches);
    $sidebar = $matches[1] ?? '';

    expect($sidebar)->toContain('Sub Category Newer')->toContain('Top Level');
    expect(strpos($sidebar, 'Sub Category Newer'))->toBeLessThan(strpos($sidebar, 'Top Level'));
});

it('index: "most active categories" sidebar includes non-top-level categories, ordered by q_count DESC, not restricted to main_cat=0', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Top Level Low Activity', 'main_cat' => 0, 'q_count' => 2],
        ['id' => 2, 'title' => 'Sub Category High Activity', 'main_cat' => 1, 'q_count' => 50],
    ]);

    $content = $this->get('/fatawa.htm')->assertOk()->getContent();

    preg_match('/التصنيفات الأكثر نشاطاً.*?<ul class="news">(.*?)<\/ul>/s', $content, $matches);
    $sidebar = $matches[1] ?? '';

    expect($sidebar)->toContain('Sub Category High Activity')->toContain('Top Level Low Activity');
    expect(strpos($sidebar, 'Sub Category High Activity'))->toBeLessThan(strpos($sidebar, 'Top Level Low Activity'));
});

it('show: lists sub-categories (q_count > 0 only) and topics under one category', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'q_count' => 5],
        ['id' => 2, 'title' => 'Child With Questions', 'main_cat' => 1, 'q_count' => 2],
        ['id' => 3, 'title' => 'Child Without Questions', 'main_cat' => 1, 'q_count' => 0],
    ]);
    $db->table('nuke_fatwa_topics')->insert([
        ['id' => 10, 'topic_name' => 'Topic A', 'parent_id' => 1],
        ['id' => 11, 'topic_name' => 'Topic B (other category)', 'parent_id' => 2],
    ]);

    $response = $this->get('/fatawa-topics-1-1.htm');

    $response->assertOk()
        ->assertSee('Child With Questions')
        ->assertDontSee('Child Without Questions')
        ->assertSee('Topic A')
        ->assertDontSee('Topic B (other category)');
});

it('show: 404s for a nonexistent category', function () {
    $this->get('/fatawa-topics-999-1.htm')->assertNotFound();
});

// ---- Full Design Parity Pass (fatawa-topics-{category}-{page}.htm) ----

it('show: document title is "الفتاوى المرئية | {category title} - {sitename}", single suffix', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'My Category', 'main_cat' => 0, 'q_count' => 5]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 200);
    expect($titleTag)->toContain('<title>الفتاوى المرئية | My Category - '.config('app.name'))
        ->and(substr_count($titleTag, (string) config('app.name')))->toBe(1);
});

it('show: renders page_bar() chrome — empty <h1>, home link, الفتاوى المرئية link, both with unconditional angle-right icons', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'My Category', 'main_cat' => 0, 'q_count' => 5]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h1 style=""></h1>')
        ->toContain('<div class="page-bar">')
        ->toContain('<i class="fa fa-home"></i>')
        ->toContain('<a href="/">الرئيسية</a>')
        ->toContain('<a href="/fatawa.htm">الفتاوى المرئية </a>');
});

it('show: page_bar() ancestor chain is root-first, current-category-last, no trailing angle-right icon on the last entry', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0, 'q_count' => 0],
        ['id' => 2, 'title' => 'Middle', 'main_cat' => 1, 'q_count' => 0],
        ['id' => 3, 'title' => 'Leaf', 'main_cat' => 2, 'q_count' => 5],
    ]);

    $content = $this->get('/fatawa-topics-3-1.htm')->assertOk()->getContent();

    $breadcrumbStart = strpos($content, 'page-breadcrumb');
    $breadcrumbEnd = strpos($content, '</ul>', $breadcrumbStart);
    $breadcrumb = substr($content, $breadcrumbStart, $breadcrumbEnd - $breadcrumbStart);

    expect(strpos($breadcrumb, 'Root'))->toBeLessThan(strpos($breadcrumb, 'Middle'))
        ->and(strpos($breadcrumb, 'Middle'))->toBeLessThan(strpos($breadcrumb, 'Leaf'));
    expect($breadcrumb)->toContain('<a href="/fatawa-topics-1-1.htm">Root </a>')
        ->toContain('<a href="/fatawa-topics-2-1.htm">Middle </a>')
        ->toContain('<a href="/fatawa-topics-3-1.htm">Leaf </a>');
    // Leaf is last — no angle-right icon after its closing </a>, unlike Root/Middle.
    $leafPos = strpos($breadcrumb, 'Leaf </a>');
    expect(substr($breadcrumb, $leafPos, 40))->not->toContain('fa-angle-right');
});

it('show: subcategory portlet is entirely absent when there are no sub-categories (under_this_tasnif()\'s own empty gate)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'No Children', 'main_cat' => 0, 'q_count' => 5]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('التصنيفات المدرجة تحت هذا التصنيف');
});

it('show: subcategory portlet uses the real window.png icon, visible thead, and numbered rows when non-empty', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'q_count' => 0],
        ['id' => 2, 'title' => 'Child', 'main_cat' => 1, 'q_count' => 3],
    ]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->toContain('التصنيفات المدرجة تحت هذا التصنيف')
        ->toContain('<img src="/assets/img/window.png">')
        ->toContain('<th> م </th>')
        ->toContain('<th> اسم التصنيف </th>')
        ->toContain('<td>1</td>')
        ->toContain('<a href="/fatawa-topics-2-1.htm">Child</a>');
});

it('show: sub-categories are ordered level ASC, id ASC (under_this_tasnif()\'s ORDER BY level DESC, id DESC then krsort())', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'q_count' => 0, 'level' => 0],
        ['id' => 3, 'title' => 'Higher Level Child', 'main_cat' => 1, 'q_count' => 1, 'level' => 2],
        ['id' => 2, 'title' => 'Lower Level Child', 'main_cat' => 1, 'q_count' => 1, 'level' => 1],
    ]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect(strpos($content, 'Lower Level Child'))->toBeLessThan(strpos($content, 'Higher Level Child'));
});

it('show: topics portlet always renders, even with zero topics (no gate in source, unlike the subcategory portlet)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Empty Topics', 'main_cat' => 0, 'q_count' => 5]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->toContain('الموضوعات المضافة في التصنيف')
        ->toContain('<img src="/assets/img/quran-book (1).png">');
});

it('show: each topic row uses the real portlet/h5/question-count/date DOM, with the real Arabic date format (space before م)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 5]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'A Topic', 'parent_id' => 1, 'db_insertion_date' => '2023-08-07']);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 100, 'question_text' => 'Q1', 'topic_id' => '|10|'],
        ['id' => 101, 'question_text' => 'Q2', 'topic_id' => '|10|20|'],
    ]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h5>')
        ->toContain('<a href="/fatawa-group-10-1.htm">A Topic</a>')
        ->toContain('<i class="fa fa-play-circle-o"></i>')
        ->toContain('عدد الأسئلة:')
        ->toContain('2') // both Q1 (exact) and Q2 (multi-membership) count via LIKE
        ->toContain('<i class="fa fa-calendar-o"></i>')
        ->toContain(\App\Domain\Content\Support\ArabicDateConverter::convert('2023-08-07'))
        ->not->toContain('٢٠٢٣م'); // no-space regression guard
});

it('show: sidebar portlets use the real fatawa-all-{general_question_id}.htm#{id} link shape, class="add", not fatawa-download-{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 5]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'T', 'parent_id' => 1]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 500, 'question_text' => 'Downloaded Q', 'general_question_id' => '|900|', 'topic_id' => 10, 'num_download' => 99, 'db_insertion_date' => 1000],
    ]);

    $content = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<i class="fa fa-download"></i>الأكثر تحميلا')
        ->toContain('<i class="fa fa-plus"></i>جديد المواد')
        ->toContain('<ul class="news">')
        ->toContain('<a href="/fatawa-all-900.htm#500" class="add">Downloaded Q</a>')
        ->not->toContain('/fatawa-download-500.htm');
});

it('fatawa-category-{id}.htm still resolves to page 1 and inherits the full presentation fix, byte-identical to fatawa-topics-{id}-1.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'q_count' => 5],
        ['id' => 2, 'title' => 'Child', 'main_cat' => 1, 'q_count' => 3],
    ]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);

    $viaCategory = $this->get('/fatawa-category-1.htm')->assertOk()->getContent();
    $viaTopicsPageOne = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    // Strip per-request CSRF tokens before comparing, same approach as the
    // route-equivalence test above.
    $strip = fn (string $html) => preg_replace('/name="_token" value="[^"]*"|content="[^"]*" name="csrf-token"|name="csrf-token" content="[^"]*"/', '', $html);

    expect($strip($viaCategory))->toBe($strip($viaTopicsPageOne))
        ->toContain('<h1 style=""></h1>')
        ->toContain('التصنيفات المدرجة تحت هذا التصنيف')
        ->toContain('Topic');
});

it('questions: exact-matches the pipe-wrapped topic_id, not a LIKE multi-membership match, via the topic-first-category-second route order', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 100, 'question_text' => 'Exact single-topic match', 'topic_id' => '|10|'],
        ['id' => 101, 'question_text' => 'Multi-topic string, not exact', 'topic_id' => '|10|20|'],
        ['id' => 102, 'question_text' => 'Different topic entirely', 'topic_id' => '|20|'],
    ]);

    // Topic id (10) first, category id (1) second — matches .htaccess:301-302
    // (t_id=$1&cat_id=$2), the opposite of increment 1's shipped order.
    $response = $this->get('/fatawa-group-10-1.htm');

    $response->assertOk()
        ->assertSee('Exact single-topic match')
        ->assertDontSee('Multi-topic string, not exact')
        ->assertDontSee('Different topic entirely');
});

it('questions: the explicit-page 3-parameter route form also works', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'Paged result', 'topic_id' => '|10|']);

    $this->get('/fatawa-group-10-1-1.htm')->assertOk()->assertSee('Paged result');
});

it('questions: 404s for a nonexistent topic', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);

    $this->get('/fatawa-group-999-1.htm')->assertNotFound();
});

// ---- Full Design Parity Pass (fatawa-group-{topic}-{category}.htm) ----

it('questions: document title is "الفتاوى المرئية | {category} | موضوع {topic} - {sitename}", single suffix', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'My Category', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'My Topic', 'parent_id' => 1]);

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 200);
    expect($titleTag)->toContain('<title>الفتاوى المرئية | My Category | موضوع My Topic - '.config('app.name'))
        ->and(substr_count($titleTag, (string) config('app.name')))->toBe(1);
});

it('questions: renders page_bar($cat_id, $id) chrome — empty <h1>, home/الفتاوى links, category ancestor chain, AND a trailing topic breadcrumb entry (a branch show() never exercises)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0, 'q_count' => 0],
        ['id' => 2, 'title' => 'Leaf', 'main_cat' => 1, 'q_count' => 1],
    ]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'The Topic', 'parent_id' => 2]);

    $content = $this->get('/fatawa-group-10-2.htm')->assertOk()->getContent();

    expect($content)->toContain('<h1 style=""></h1>')
        ->toContain('<a href="/">الرئيسية</a>')
        ->toContain('<a href="/fatawa.htm">الفتاوى المرئية </a>')
        ->toContain('<a href="/fatawa-topics-1-1.htm">Root </a>')
        ->toContain('<a href="/fatawa-topics-2-1.htm">Leaf </a>')
        ->toContain('<a href="/fatawa-group-10-2.htm"> موضوع The Topic </a>');

    // The ancestor loop's own LAST item ("Leaf") gets NO trailing
    // angle-right icon — the topic <li> that follows is a separate block
    // with its own icon, not counted into the ancestor loop's own
    // last-item check (page_bar()'s `$i < count($page_par)` only covers
    // $page_par itself).
    $leafEnd = strpos($content, 'Leaf </a>') + strlen('Leaf </a>');
    $leafClosingLi = strpos($content, '</li>', $leafEnd);
    expect(substr($content, $leafEnd, $leafClosingLi - $leafEnd))->not->toContain('fa-angle-right');

    // The topic entry's icon comes BEFORE its link, unlike every other
    // breadcrumb <li> (icon after the <a>) — reproduced exactly.
    $topicLiStart = strpos($content, '<a href="/fatawa-group-10-2.htm">');
    $topicLi = substr($content, $topicLiStart - 60, 60);
    expect($topicLi)->toContain('<i class="fa fa-angle-right"></i>');
});

it('questions: main list portlet is entirely absent when there are zero questions (get_all_questions()\'s own count>0 gate)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Empty Topic', 'parent_id' => 1]);

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('الأسئلة المضافة في الموضوع');
});

it('questions: each row uses the real portlet/h5/answer-count/view-count DOM — عدد الفتاوى + المشاهدات, not tobics.php\'s topic-count + date fields', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'A Question', 'topic_id' => '|10|', 'num_view' => 42]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 500, 'question_text' => 'Answer 1', 'general_question_id' => '|100|', 'topic_id' => 10, 'num_download' => 0, 'db_insertion_date' => 0],
        ['id' => 501, 'question_text' => 'Answer 2', 'general_question_id' => '|100|', 'topic_id' => 10, 'num_download' => 0, 'db_insertion_date' => 0],
    ]);

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h5>')
        ->toContain('<a href="/fatawa-all-100.htm">A Question</a>')
        ->toContain('<i class="fa fa-play-circle-o"></i>')
        ->toContain('عدد الفتاوى:')
        ->toContain('2') // answer count, via fatwaAnswerCountForGeneralQuestion()
        ->toContain('<i class="fa fa-eye"></i>')
        ->toContain('المشاهدات:')
        ->toContain('42') // num_view
        ->not->toContain('fa-calendar-o'); // no date field on this page's rows
});

it('questions: main-list link has no #fragment (fatawa-all-{id}.htm), unlike the sidebar\'s fatawa-all-{id}.htm#{answer_id} shape', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'Q', 'topic_id' => '|10|', 'num_view' => 0]);

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/fatawa-all-100.htm"')
        ->not->toContain('href="/fatawa-all-100.htm#');
});

it('questions: pagination renders both above and below the table (subtobics.php calls pagination() twice)', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert(
        collect(range(1, 30))->map(fn ($i) => ['id' => $i, 'question_text' => "Q{$i}", 'topic_id' => '|10|', 'num_view' => 0])->all()
    );

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    expect(substr_count($content, 'role="navigation"'))->toBe(2);
});

it('questions: topic description renders as a bare portlet (no caption/icon) only when non-empty', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert([
        ['id' => 10, 'topic_name' => 'With Description', 'parent_id' => 1, 'description' => 'Some real description text.'],
        ['id' => 11, 'topic_name' => 'Without Description', 'parent_id' => 1, 'description' => null],
    ]);

    $withDescription = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();
    $withoutDescription = $this->get('/fatawa-group-11-1.htm')->assertOk()->getContent();

    expect($withDescription)->toContain('Some real description text.');
    // Both topics have zero questions (main-list portlet absent for both),
    // so the only structural difference is the description portlet itself
    // — one extra "portlet box blue" wrapper when a description exists.
    expect(substr_count($withDescription, 'portlet box blue'))
        ->toBe(substr_count($withoutDescription, 'portlet box blue') + 1);
});

it('questions: sidebars are category-scoped (mostdownload($cat_id)/recentlyadd($cat_id)), same real link shape as topics-show.blade.php, not fatawa-download-{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_questions')->insert(
        ['id' => 500, 'question_text' => 'Downloaded Q', 'general_question_id' => '|900|', 'topic_id' => 10, 'num_download' => 99, 'db_insertion_date' => 1000]
    );

    $content = $this->get('/fatawa-group-10-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<i class="fa fa-download"></i>الأكثر تحميلا')
        ->toContain('<i class="fa fa-plus"></i>جديد المواد')
        ->toContain('<a href="/fatawa-all-900.htm#500" class="add">Downloaded Q</a>')
        ->not->toContain('/fatawa-download-500.htm');
});

// ---- fatawa-category-{id}.htm: owner-approved reuse of tobics.php's
// already-ported show() action (fatawa/category.php itself is confirmed
// unrecoverable — see routes/content.php's own docblock) ----

it('category: fatawa-category-{id}.htm renders identically to fatawa-topics-{id}-1.htm, same controller action, page defaulted to 1', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Parent', 'main_cat' => 0, 'q_count' => 5],
        ['id' => 2, 'title' => 'Child With Questions', 'main_cat' => 1, 'q_count' => 2],
    ]);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic A', 'parent_id' => 1]);

    $viaCategory = $this->get('/fatawa-category-1.htm')->assertOk()->getContent();
    $viaTopicsPageOne = $this->get('/fatawa-topics-1-1.htm')->assertOk()->getContent();

    expect($viaCategory)->toBe($viaTopicsPageOne)
        ->toContain('Child With Questions')
        ->toContain('Topic A');
});

it('category: 404s for a nonexistent category, same as fatawa-topics-', function () {
    $this->get('/fatawa-category-999.htm')->assertNotFound();
});

it('category: no page-suffixed alias is registered for this URL family', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert(['id' => 1, 'title' => 'Cat', 'main_cat' => 0, 'q_count' => 1]);

    $this->get('/fatawa-category-1-page-2.htm')->assertNotFound();
    $this->get('/fatawa-category-1-2.htm')->assertNotFound();
});

it('fatawa-authors.htm reuses KhotabAuthorController::index() with the fatwa branch', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Has Fatwa', 'prename' => 'Dr.', 'fatwa' => 3, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Fatwa', 'prename' => 'Dr.', 'fatwa' => 0, 'hidden' => 0],
    ]);

    $response = $this->get('/fatawa-authors.htm');

    $response->assertOk()->assertSee('Has Fatwa')->assertDontSee('No Fatwa');
});
