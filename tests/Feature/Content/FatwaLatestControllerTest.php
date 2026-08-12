<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
});

it('lists the latest 50 answers, newest id first, with the answering author, and an empty sidebar (more.php\'s confirmed undefined-$id bug)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'question_text' => 'Older', 'general_question_id' => '|10|'],
        ['id' => 2, 'auther_id' => 1, 'question_text' => 'Newer', 'general_question_id' => '|20|'],
    ]);

    $response = $this->get('/more-fatawa.htm');

    $response->assertOk()->assertSeeInOrder(['Newer', 'Older']);
});
