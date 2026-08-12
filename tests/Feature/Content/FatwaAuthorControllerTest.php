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
