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
        ['id' => 3, 'title' => 'Not A Root', 'main_cat' => 1, 'q_count' => 5],
    ]);

    $response = $this->get('/fatawa.htm');

    $response->assertOk()
        ->assertSee('Root With Questions')
        ->assertDontSee('Root Without Questions')
        ->assertDontSee('Not A Root');
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
