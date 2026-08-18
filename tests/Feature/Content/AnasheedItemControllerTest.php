<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForAnasheedItem(): void
{
    InMemoryConnection::setup('main', [
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
        'ips' => MainSchema::ips(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAnasheedItem();
});

it('show: renders item details and increments hits', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'A Nasheed', 'description' => 'A description', 'hits' => 5,
    ]);

    $this->get('/var-item-1.htm')->assertOk()->assertSee('A Nasheed')->assertSee('A description');

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->find(1)->hits)->toBe(6);
});

it('show: hidden items remain viewable, matching legacy\'s confirmed lack of hidden filtering', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Hidden Item', 'hidden' => 1,
    ]);

    $this->get('/var-item-1.htm')->assertOk()->assertSee('Hidden Item');
});

it('show: IF-028 fix — comment flags render from images/flags/, not flags/', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'comments' => 1]);
    DB::connection('main')->table('nuke_anasheed_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Visitor', 'code' => 'eg', 'comment' => 'hello', 'view' => 1, 'mytime' => 100,
    ]);

    $this->get('/var-item-1.htm')->assertOk()->assertSee('images/flags/eg.png', false);
});

it('show: only renders comments with view=1', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'comments' => 2]);
    DB::connection('main')->table('nuke_anasheed_comments')->insert([
        ['id' => 1, 'khid' => 1, 'uname' => 'A', 'comment' => 'approved', 'view' => 1, 'mytime' => 100],
        ['id' => 2, 'khid' => 1, 'uname' => 'B', 'comment' => 'pending', 'view' => 0, 'mytime' => 100],
    ]);

    $response = $this->get('/var-item-1.htm');

    $response->assertOk()->assertSee('approved')->assertDontSee('pending');
});

it('show: renders mirrors only when the mirror flag is set, even if mirror rows exist', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'mirror' => 0]);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 1, 'khid' => 1, 'title' => 'HD']);

    $this->get('/var-item-1.htm')->assertOk()->assertDontSee('قائمة الجودات المختلفة');
});

it('show: G-13-09 — the "الأكثر تحميلا"/"احدث المواد" sidebar rows render a thumbnails.php-wrapped thumb, matching most_recent_html()\'s w=72,h=50', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item', 'group_id' => 5, 'frame' => 0, 'hits' => 1],
        ['id' => 100, 'title' => 'Other Framed Item', 'group_id' => 5, 'frame' => 1, 'hits' => 2],
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('thumbnails.php?h=50&amp;w=72&amp;src=media/anasheed/frame/0/100.jpg');
});

it('show: G-13 closure — the "most downloaded"/"most recent" sidebar is still present on the item page (item.php:93 DOES call most_downloaded_recent_sidebar($Group), unlike group.php)', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'group_id' => 5, 'hits' => 1,
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('الأكثر تحميلا')
        ->and($content)->toContain('احدث المواد');
});

it('download: redirects to the item\'s link (a full URL, not a streamed local file) and increments downcount', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'link' => 'http://example.com/a.mp3', 'downcount' => 2,
    ]);

    $this->get('/var-download-1.htm')->assertRedirect('https://example.com/a.mp3');

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->find(1)->downcount)->toBe(3);
});

it('downloadMirror: redirects to the mirror\'s link and increments the mirror\'s own hits', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => 1, 'khid' => 1, 'link' => 'http://example.com/hd.mp4', 'hits' => 0,
    ]);

    $this->get('/var-mirror-1-1.htm')->assertRedirect('https://example.com/hd.mp4');

    expect(DB::connection('main')->table('nuke_anasheed_mirror')->find(1)->hits)->toBe(1);
});

it('storeComment: creates an unmoderated comment and returns "1"', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/var-item-1/comments', ['user_nickname' => 'Test', 'user_comment' => 'Nice']);

    $response->assertOk()->assertSeeText('1');
    $comment = DB::connection('main')->table('nuke_anasheed_comments')->where('khid', 1)->first();
    expect($comment->view)->toBe(0);
});

it('storeComment: post-Wave-4 fix — resolves the country code via GeoIpLookup instead of leaving it blank', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 0, 'endip_num' => 4294967295, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    $this->post('/var-item-1/comments', ['user_nickname' => 'Test', 'user_comment' => 'Nice'])->assertOk();

    $comment = DB::connection('main')->table('nuke_anasheed_comments')->where('khid', 1)->first();
    expect($comment->code)->toBe('eg');
});

it('storeComment: post-Wave-4 fix — dispatches CommentPosted', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Domain\Content\Events\CommentPosted::class]);

    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/var-item-1/comments', ['user_nickname' => 'Test', 'user_comment' => 'Nice'])->assertOk();

    \Illuminate\Support\Facades\Event::assertDispatched(\App\Domain\Content\Events\CommentPosted::class);
});

// ---- G-11-02 (Phase 1 audit): send-friend-anasheed-{id}.htm ----

it('sendToFriend: valid submission sends AnasheedFriendMail and returns "1", matching anasheed_send_friend()\'s bare success code', function () {
    \Illuminate\Support\Facades\Mail::fake();
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 9, 'title' => 'A Group']);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'A Nasheed', 'group_id' => 9]);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => 'Ahmed', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('1');
    // hasFrom() isn't checked here — Mail::fake()'s captured Mailable is
    // the raw pre-build() instance, the same reason FatwaFriendMail's own
    // existing test doesn't assert its (also build()-set) from address
    // this way either. yourEmail is asserted directly instead — it's a
    // public constructor property, populated before build() runs.
    \Illuminate\Support\Facades\Mail::assertSent(\App\Domain\Content\Mail\AnasheedFriendMail::class, function ($mail) {
        return $mail->hasTo('sami@example.com')
            && $mail->yourEmail === 'ahmed@example.com'
            && $mail->friendName === 'Sami'
            && $mail->yourName === 'Ahmed'
            && $mail->anasheedItem->id === 1;
    });
});

it('sendToFriend: missing your_name returns "2" (legacy\'s single combined validation code) and sends no mail', function () {
    \Illuminate\Support\Facades\Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => '', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('2');
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('sendToFriend: an invalid email format returns "2", matching legacy\'s FILTER_VALIDATE_EMAIL check (no DNS lookup, unlike Fatawa)', function () {
    \Illuminate\Support\Facades\Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => 'Ahmed', 'your_email' => 'not-an-email',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('2');
    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('sendToFriend: a single-character name is accepted — legacy has no minimum-length check, unlike Fatawa\'s 2-character rule', function () {
    \Illuminate\Support\Facades\Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => 'A', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'S', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('1');
});

it('AnasheedFriendMail: build() sets From to the submitting user\'s own name/email, matching shams_mail_no_spam() exactly (not a fixed site address like FatwaFriendMail)', function () {
    $item = new \App\Domain\Content\Models\AnasheedItem(['id' => 1, 'title' => 'A Nasheed']);

    $built = (new \App\Domain\Content\Mail\AnasheedFriendMail($item, 'Sami', 'Ahmed', 'ahmed@example.com'))->build();

    expect($built->from[0]['address'])->toBe('ahmed@example.com')
        ->and($built->from[0]['name'])->toBe('Ahmed')
        ->and($built->subject)->toBe('A Nasheed - موقع الطريق الى الله');
});

it('sendToFriend: 404s for a nonexistent anasheed item', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $this->post('/send-friend-anasheed-999.htm', [
        'your_name' => 'Ahmed', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ])->assertNotFound();
});

// ---- G-12-01 (G-12 investigation): var-item-{id}-page-{page}.htm ----

it('show: paged route\'s page 2 shows the next 20 comments, NOT page 1\'s comments again — deliberately does not reproduce item.php\'s double-decrement bug', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'comments' => 21]);

    $comments = [];
    for ($i = 1; $i <= 21; $i++) {
        $comments[] = ['id' => $i, 'khid' => 1, 'uname' => 'U', 'comment' => "Comment {$i}", 'view' => 1, 'mytime' => 100];
    }
    DB::connection('main')->table('nuke_anasheed_comments')->insert($comments);

    // orderByDesc('id') — comment 21 is first, so page 1 (comments 21..2,
    // 20 of them) does not include comment 1; page 2 does. Legacy's own
    // double-decrement bug would instead re-show page 1's comments here.
    $page1 = $this->get('/var-item-1-page-1.htm')->assertOk()->getContent();
    $page2 = $this->get('/var-item-1-page-2.htm')->assertOk()->getContent();

    expect($page1)->not->toContain('Comment 1<')->and($page1)->toContain('Comment 21');
    expect($page2)->toContain('Comment 1<')->and($page2)->not->toContain('Comment 21');
});

it('show: paged route still increments hits and 404s for a nonexistent item, matching the base route', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'hits' => 5]);

    $this->get('/var-item-1-page-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->find(1)->hits)->toBe(6);
    $this->get('/var-item-999-page-1.htm')->assertNotFound();
});
