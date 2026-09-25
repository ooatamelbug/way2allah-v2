<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForFatwaAuthorController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaAuthorController();
});

it('show: lists general questions this author has answered, deduplicated, with legacy\'s own (unimplemented) auther-all-fatawa link target', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic X', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 100, 'question_text' => 'Answered by this author', 'topic_id' => '|10|'],
        ['id' => 200, 'question_text' => 'Not answered by this author', 'topic_id' => '|10|'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 5, 'general_question_id' => '|100|'],
        ['id' => 2, 'auther_id' => 99, 'general_question_id' => '|200|'],
    ]);

    $response = $this->get('/auther-questions-5.htm');

    $response->assertOk()
        ->assertSee('Answered by this author')
        ->assertDontSee('Not answered by this author')
        ->assertSee('/auther-all-fatawa-5-100.htm', false);
});

it('show: 404s for a nonexistent author', function () {
    $this->get('/auther-questions-999.htm')->assertNotFound();
});

it('show: most-downloaded sidebar is confirmed sitewide/unscoped, NOT filtered to this author (legacy\'s own commented-out filter, reproduced not fixed)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'auther_id' => 999, 'question_text' => 'By a totally different author', 'num_download' => 500,
    ]);

    // Even though this question was answered by a DIFFERENT author (999,
    // not 5), it must still appear on author 5's "most downloaded"
    // sidebar — this is the confirmed legacy bug/quirk, not scoped.
    $response = $this->get('/auther-questions-5.htm');

    $response->assertOk()->assertSee('By a totally different author');
});

// ---- Author Questions Visual Parity Pass (decision-log #50) ----

it('show: restores the real legacy breadcrumb, portlet wrapper, and table_order numbered column', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 17, 'name' => 'الحويني', 'prename' => 'الشيخ']);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'A question']);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 17, 'general_question_id' => '|100|']);

    $content = $this->get('/auther-questions-17.htm')->assertOk()->getContent();

    expect($content)
        // page_bar_auther()'s real, distinct breadcrumb shape — icon
        // BEFORE the link, "قائمة الدعاة" first item, not the shared
        // <x-page-chrome> component's "الرئيسية"/icon-after shape.
        ->toContain('<a href="/fatawa-authors.htm">قائمة الدعاة</a>')
        ->toContain('<a href="/auther-questions-17.htm">الشيخ الحويني </a>')
        ->toContain('portlet box blue')
        ->toContain('<i class="fa fa-question"></i>الأسئلة التى أفتى بها الشيخ')
        ->toContain('<link rel="stylesheet" href="/fatawa/css/new-style.css">')
        // get_all_auther_questions()'s real $i=$offset+1 row numbering.
        ->toContain('class="table_order w2a-card-order">1</span>');
});

it('show: sidebar links use the real fatawa-all-{general_id}.htm#{id} shape (functions.php:688/701), not fatawa-download-{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 42, 'auther_id' => 5, 'question_text' => 'Downloaded item', 'general_question_id' => '|900|', 'num_download' => 500,
    ]);

    $content = $this->get('/auther-questions-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/fatawa-all-900.htm#42" class="add"><span class="w2a-news-title">Downloaded item</span></a>')
        ->not->toContain('/fatawa-download-42.htm');
});

it('show: pagination uses the real pretty-URL contract (/auther-questions-{author}-{page}.htm), not ?page=', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh']);
    $questionIds = [];
    for ($i = 1; $i <= 30; $i++) {
        $db->table('nuke_fatwa_general_questions')->insert(['id' => 100 + $i, 'question_text' => "Question {$i}"]);
        $db->table('nuke_fatwa_questions')->insert(['id' => $i, 'auther_id' => 5, 'general_question_id' => '|'.(100 + $i).'|']);
        $questionIds[] = 100 + $i;
    }

    $page1 = $this->get('/auther-questions-5.htm');
    $page1->assertOk()
        ->assertSee('class="w2a-pagination"', false)
        ->assertSee('/auther-questions-5-2.htm', false)
        ->assertDontSee('?page=', false);

    $page2 = $this->get('/auther-questions-5-2.htm');
    $page2->assertOk()->assertSee('/auther-questions-5.htm', false);
});

// ---- khotab-fatwa-{author}.htm compatibility redirect (decision-log
// #48/#49) — BUSINESS_REPAIR_LOW_RISK, NOT legacy parity. Investigation
// trail: initially SOURCE_UNRECOVERABLE (no generator found) -> a real
// dynamic generator was found (khotab/authors.php:80, op=fatwa branch) ->
// confirmed legacy never implemented a fatwa branch in khotab/author.php
// (a real, live, unfixed legacy authoring bug) -> a reuse audit found the
// exact same "this author's fatwas" content already served correctly at
// /auther-questions-{author}.htm -> owner approved redirecting the broken
// legacy-generated URL there. No second rendering path, no duplicated
// query logic — this only tests the redirect itself; show()'s own
// behavior is covered by the tests above, unchanged. ----

it('khotab-fatwa-{author}.htm redirects to the canonical /auther-questions-{author}.htm, resolved via the named route (not a hardcoded string)', function () {
    $this->get('/khotab-fatwa-17.htm')
        ->assertRedirect(route('fatawa.author.show', ['author' => 17]))
        ->assertRedirect('/auther-questions-17.htm');
});

it('khotab-fatwa-{author}.htm redirect works for a second real author id (79)', function () {
    $this->get('/khotab-fatwa-79.htm')
        ->assertRedirect(route('fatawa.author.show', ['author' => 79]))
        ->assertRedirect('/auther-questions-79.htm');
});

it('khotab-fatwa-{author}.htm uses a 302 (temporary) redirect', function () {
    $this->get('/khotab-fatwa-17.htm')->assertStatus(302);
});

it('khotab-fatwa-{author}.htm does not match a non-numeric author segment', function () {
    $this->get('/khotab-fatwa-abc.htm')->assertNotFound();
});

it('khotab-fatwa-{author}.htm redirects even for a nonexistent author id — the 404 correctly happens on the canonical page, not here (no duplicated existence check)', function () {
    $this->get('/khotab-fatwa-999999.htm')
        ->assertRedirect('/auther-questions-999999.htm');

    $this->get('/auther-questions-999999.htm')->assertNotFound();
});

/**
 * Fatwa Authors Batch 1 — the zero-result short-circuit.
 *
 * These pin BEHAVIOUR PARITY for an author with no mappings: same 200, same
 * empty presentation, same valid URL — plus the one intended difference,
 * which is a lower query count.
 *
 * NOT a fix for the observed /auther-questions-242.htm latency: production
 * measured that author's first-stage query at 0.0025s and its zero-result
 * path at fewer queries than a non-empty author's. That issue is still OPEN.
 */
it('show: an author with zero mappings renders 200 with the empty page, and the URL stays valid (no 404, no redirect)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 242, 'name' => 'Zero Mappings', 'prename' => 'Sh.', 'fatwa' => 289]);

    $response = $this->get('/auther-questions-242.htm');

    $response->assertOk();
    $response->assertHeaderMissing('Location');

    $content = $response->getContent();

    // Same chrome as a non-empty page; simply no question rows.
    expect($content)
        ->toContain('الأسئلة التى أفتى بها الشيخ')
        ->toContain('Zero Mappings')
        ->not->toContain('w2a-fatwa-question-card');
});

it('show: the zero-result path issues NO paginator COUNT query (the 0 = 1 round trip is gone)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 242, 'name' => 'Zero Mappings', 'prename' => 'Sh.']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->get('/auther-questions-242.htm')->assertOk();

    $againstGeneralQuestions = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'nuke_fatwa_general_questions')
    ));

    expect($againstGeneralQuestions)->toBe([]);
});

it('show: a non-empty author still queries nuke_fatwa_general_questions (the short-circuit is scoped to the empty case)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Has Questions', 'prename' => 'Sh.']);
    $db->table('nuke_fatwa_general_questions')->insert(['id' => 100, 'question_text' => 'Q100', 'topic_id' => '0']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'auther_id' => 5, 'general_question_id' => '|100|', 'question_text' => 'A',
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->get('/auther-questions-5.htm')->assertOk();

    expect(array_filter($queries, fn (string $sql) => str_contains($sql, 'nuke_fatwa_general_questions')))
        ->not->toBe([]);
});

it('show: pagination/count consistency — 30 distinct questions paginate at 25 with a total of 30 on both pages', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Paged', 'prename' => 'Sh.']);

    for ($i = 1; $i <= 30; $i++) {
        $db->table('nuke_fatwa_general_questions')->insert([
            'id' => 200 + $i, 'question_text' => sprintf('Question %02d', $i), 'topic_id' => '0',
        ]);
        $db->table('nuke_fatwa_questions')->insert([
            'id' => $i, 'auther_id' => 5, 'general_question_id' => '|' . (200 + $i) . '|', 'question_text' => 'A' . $i,
        ]);
    }

    $listing = app(App\Domain\Content\Services\ContentListingService::class);

    expect($listing->fatwaGeneralQuestionsByAuthor(5, 1)->total())->toBe(30)
        ->and($listing->fatwaGeneralQuestionsByAuthor(5, 1)->count())->toBe(25)
        ->and($listing->fatwaGeneralQuestionsByAuthor(5, 2)->total())->toBe(30)
        ->and($listing->fatwaGeneralQuestionsByAuthor(5, 2)->count())->toBe(5);

    // 30 > 25, so the pagination nav renders (its own `$count > $perpage` gate).
    expect($this->get('/auther-questions-5.htm')->assertOk()->getContent())
        ->toContain('w2a-pagination');
});

it('show: an author with exactly 25 questions renders no pagination nav, and one with zero renders none either', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 5, 'name' => 'Exactly25', 'prename' => 'Sh.'],
        ['id' => 6, 'name' => 'Empty', 'prename' => 'Sh.'],
    ]);

    for ($i = 1; $i <= 25; $i++) {
        $db->table('nuke_fatwa_general_questions')->insert([
            'id' => 300 + $i, 'question_text' => sprintf('Q %02d', $i), 'topic_id' => '0',
        ]);
        $db->table('nuke_fatwa_questions')->insert([
            'id' => $i, 'auther_id' => 5, 'general_question_id' => '|' . (300 + $i) . '|', 'question_text' => 'A' . $i,
        ]);
    }

    expect($this->get('/auther-questions-5.htm')->assertOk()->getContent())->not->toContain('w2a-pagination');
    expect($this->get('/auther-questions-6.htm')->assertOk()->getContent())->not->toContain('w2a-pagination');
});

it('show: duplicate mappings are deduplicated in the listing total (132-rows/126-questions semantics)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Dups', 'prename' => 'Sh.']);
    $db->table('nuke_fatwa_general_questions')->insert([
        ['id' => 100, 'question_text' => 'Q100', 'topic_id' => '0'],
        ['id' => 101, 'question_text' => 'Q101', 'topic_id' => '0'],
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 5, 'general_question_id' => '|100|', 'question_text' => 'A'],
        ['id' => 2, 'auther_id' => 5, 'general_question_id' => '|100|', 'question_text' => 'B'],
        ['id' => 3, 'auther_id' => 5, 'general_question_id' => '|101|', 'question_text' => 'C'],
    ]);

    expect(app(App\Domain\Content\Services\ContentListingService::class)
        ->fatwaGeneralQuestionsByAuthor(5, 1)->total())->toBe(2);
});
