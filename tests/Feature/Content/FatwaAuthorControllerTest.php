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
        ->toContain('<td class="table_order">1</td>');
});

it('show: sidebar links use the real fatawa-all-{general_id}.htm#{id} shape (functions.php:688/701), not fatawa-download-{id}.htm', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh']);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 42, 'auther_id' => 5, 'question_text' => 'Downloaded item', 'general_question_id' => '|900|', 'num_download' => 500,
    ]);

    $content = $this->get('/auther-questions-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/fatawa-all-900.htm#42" class="add">Downloaded item</a>')
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
