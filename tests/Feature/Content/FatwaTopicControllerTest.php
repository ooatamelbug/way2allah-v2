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

it('fatawa-authors.htm reuses KhotabAuthorController::index() with the fatwa branch', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Has Fatwa', 'prename' => 'Dr.', 'fatwa' => 3, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Fatwa', 'prename' => 'Dr.', 'fatwa' => 0, 'hidden' => 0],
    ]);

    $response = $this->get('/fatawa-authors.htm');

    $response->assertOk()->assertSee('Has Fatwa')->assertDontSee('No Fatwa');
});
