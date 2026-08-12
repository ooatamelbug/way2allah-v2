<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForFatwaChannelController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
        'nuke_fatwa_topics' => MainSchema::nukeFatwaTopics(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForFatwaChannelController();
});

it('index: lists only channels with at least one fatwa question, plus the always-present "no channel" entry', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Has Questions'],
        ['id' => 2, 'title' => 'No Questions'],
    ]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'channel_id' => 1]);

    $response = $this->get('/fatawa-channels.htm');

    $response->assertOk()
        ->assertSee('بدون قناة')
        ->assertSee('Has Questions')
        ->assertDontSee('No Questions');
});

it('show: lists general questions for the channel, resolved via the multi-step legacy query shape, with topic and author attached', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_islamic_authors')->insert(['id' => 5, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_topics')->insert(['id' => 10, 'topic_name' => 'Topic X', 'parent_id' => 1]);
    $db->table('nuke_fatwa_general_questions')->insert([
        'id' => 100, 'question_text' => 'Channel-scoped question', 'topic_id' => '|10|',
    ]);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'channel_id' => 1, 'general_question_id' => '|100|', 'auther_id' => 5],
        ['id' => 2, 'channel_id' => 99, 'general_question_id' => '|200|', 'auther_id' => 5],
    ]);

    $response = $this->get('/fatawa-channel-1.htm');

    $response->assertOk()->assertSee('Channel-scoped question')->assertSee('Topic X')->assertSee('Shaikh');
});

it('show: 404s for a nonexistent channel', function () {
    $this->get('/fatawa-channel-999.htm')->assertNotFound();
});

it('show: most-downloaded sidebar is genuinely channel-scoped (WHERE channel_id is NOT commented out, unlike the author page)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'Chan']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'channel_id' => 1, 'question_text' => 'In this channel', 'num_download' => 100],
        ['id' => 2, 'channel_id' => 2, 'question_text' => 'Different channel entirely', 'num_download' => 999],
    ]);

    $response = $this->get('/fatawa-channel-1.htm');

    $response->assertOk()->assertSee('In this channel')->assertDontSee('Different channel entirely');
});
