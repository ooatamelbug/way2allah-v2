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
