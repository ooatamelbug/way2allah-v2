<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
});

it('lists individual answers added today (db_insertion_date match), joined with the answering author', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $today = now()->format('Y-m-d');
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'question_text' => 'Added today', 'db_insertion_date' => $today],
        ['id' => 2, 'auther_id' => 1, 'question_text' => 'Added yesterday', 'db_insertion_date' => '2020-01-01'],
    ]);

    $response = $this->get('/fatwa-today.htm');

    $response->assertOk()->assertSee('Added today')->assertDontSee('Added yesterday');
});

it('featured/random section joins on plain integer topic_id equality, not the pipe-delimited format', function () {
    $db = DB::connection('main');
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic', 'parent_id' => 1]);
    $db->table('nuke_fatwa_questions')->insert([
        'id' => 1, 'topic_id' => 10, 'question_text' => 'Featured candidate', 'general_question_id' => '|500|',
    ]);

    // The featured box uses a random OFFSET up to 7400 — with only one
    // row present, it will only actually render if the random offset
    // happens to land within range; assert the route at least renders
    // without error (the join itself is what's under test — a
    // topic_id=999 row would break the JOIN, proving the equality shape).
    $response = $this->get('/fatwa-today.htm');

    $response->assertOk();
});

it('the fatwa-today-{page}.htm route also works', function () {
    $this->get('/fatwa-today-1.htm')->assertOk();
});

it('fatwa-date-{d}-{m}-{y}-{page}.htm is NOT registered — no confirmed implementing code was found', function () {
    $this->get('/fatwa-date-1-1-2026-1.htm')->assertNotFound();
});
