<?php

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Mail\AnasheedFriendMail;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

// ---- var-item-{id}.htm parity: css/custom.css, w2a_play.js/anasheed_scripts.js, watch/comment/send-friend controls, modals, player container ----

it('show: loads css/custom.css, w2a_play.js, and anasheed_scripts.js (item.php:5,63-64)', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'A Nasheed']);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/css/custom.css')
        ->and($content)->toContain('/scripts/w2a_play.js')
        ->and($content)->toContain('/scripts/anasheed_scripts.js');
});

it('show: renders the real watch/comment/send-friend controls and both modals, all previously missing', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'A Nasheed']);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain("onclick=\"w2a_play(1,'anasheed')\"")
        ->and($content)->toContain('مشاهدة المادة')
        ->and($content)->toContain('id="commentsModal"')
        ->and($content)->toContain('id="sendFriendModal"')
        ->and($content)->toContain('send-friend-btn')
        ->and($content)->toContain('class="w2a-player-panel"')
        ->and(substr_count($content, 'id="anasheed_id"'))->toBe(2) // one per modal, matches legacy's own duplicate id
        ->and($content)->toContain('id="the_main_player"')
        ->and($content)->toContain('id="w2a_main_player"');
});

it('show: mirror rows render the real numbered/play-button/extension-icon/size/downloads structure, not a bare title+count line', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'A Nasheed', 'mirror' => 1]);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => 10, 'khid' => 1, 'title' => 'HD quality', 'link' => 'https://cdn.example.com/a.mp4', 'hits' => 42, 'linksize' => 9000000, 'vedio' => 1,
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain("onclick=\"w2a_play(10,'anasheed_mirror')\"")
        ->and($content)->toContain('<span class="w2a-quality-num">1</span>')
        ->and($content)->toContain('class="w2a-quality-badge-fmt">MP4</span>')
        ->and($content)->toContain('8.58 ميجا بايت')
        ->and($content)->toContain('class="fa fa-play-circle"');
});

it('show: IF-028 fix — comment flags render from images/flags/, not flags/', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'comments' => 1]);
    DB::connection('main')->table('nuke_anasheed_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Visitor', 'code' => 'eg', 'comment' => 'hello', 'view' => 1, 'mytime' => 100,
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('images/flags/eg.png')
        ->toContain('class="w2a-comments-wrap"')
        ->toContain('class="w2a-comment-card"')
        ->toContain('class="w2a-comment-body"');
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

it('show: the redesigned sidebar rows replace thumbnails with lightweight media icons', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item', 'group_id' => 5, 'frame' => 0, 'hits' => 1],
        ['id' => 100, 'title' => 'Other Framed Item', 'group_id' => 5, 'frame' => 1, 'hits' => 2],
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-chat-sidebar-icon"')
        ->not->toContain('thumbnails.php?h=50&amp;w=72');
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
    Event::fake([CommentPosted::class]);

    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/var-item-1/comments', ['user_nickname' => 'Test', 'user_comment' => 'Nice'])->assertOk();

    Event::assertDispatched(CommentPosted::class);
});

// ---- G-11-02 (Phase 1 audit): send-friend-anasheed-{id}.htm ----

it('sendToFriend: valid submission sends AnasheedFriendMail and returns "1", matching anasheed_send_friend()\'s bare success code', function () {
    Mail::fake();
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
    Mail::assertSent(AnasheedFriendMail::class, function ($mail) {
        return $mail->hasTo('sami@example.com')
            && $mail->yourEmail === 'ahmed@example.com'
            && $mail->friendName === 'Sami'
            && $mail->yourName === 'Ahmed'
            && $mail->anasheedItem->id === 1;
    });
});

it('sendToFriend: missing your_name returns "2" (legacy\'s single combined validation code) and sends no mail', function () {
    Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => '', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('2');
    Mail::assertNothingSent();
});

it('sendToFriend: an invalid email format returns "2", matching legacy\'s FILTER_VALIDATE_EMAIL check (no DNS lookup, unlike Fatawa)', function () {
    Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => 'Ahmed', 'your_email' => 'not-an-email',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('2');
    Mail::assertNothingSent();
});

it('sendToFriend: a single-character name is accepted — legacy has no minimum-length check, unlike Fatawa\'s 2-character rule', function () {
    Mail::fake();
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/send-friend-anasheed-1.htm', [
        'your_name' => 'A', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'S', 'friend_email' => 'sami@example.com',
    ]);

    $response->assertOk()->assertSeeText('1');
});

it('AnasheedFriendMail: build() sets From to the submitting user\'s own name/email, matching shams_mail_no_spam() exactly (not a fixed site address like FatwaFriendMail)', function () {
    $item = new AnasheedItem(['id' => 1, 'title' => 'A Nasheed']);

    $built = (new AnasheedFriendMail($item, 'Sami', 'Ahmed', 'ahmed@example.com'))->build();

    expect($built->from[0]['address'])->toBe('ahmed@example.com')
        ->and($built->from[0]['name'])->toBe('Ahmed')
        ->and($built->subject)->toBe('A Nasheed - موقع الطريق الى الله');
});

it('sendToFriend: 404s for a nonexistent anasheed item', function () {
    Mail::fake();

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

// ---- var-item-17350.htm targeted gap closure: title/heading, breadcrumb ancestor chain, sidebar markup, date formatting ----

it('show: the <title> and <h3> heading both use " - {group} - {item}", and the <title> carries the sitename TWICE (item.php\'s own confirmed double-suffix, not a fetch artifact)', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 1, 'title' => 'A Group', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'An Item', 'group_id' => 1]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<title> - A Group - An Item - '.config('app.name').' - '.config('app.name').'</title>')
        ->and($content)->toContain('<h3 class="page-title"> - A Group - An Item</h3>');
});

it('show: breadcrumb walks the FULL ancestor chain (parent_id) and does not invent a "منوعات" label', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Grandparent', 'parent_id' => 0],
        ['id' => 2, 'title' => 'Parent', 'parent_id' => 1],
        ['id' => 3, 'title' => 'Immediate Group', 'parent_id' => 2],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'An Item', 'group_id' => 3]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    // "منوعات" legitimately appears elsewhere (the sitewide nav dropdown,
    // layouts/partials/navigation.blade.php) — scoped to the breadcrumb
    // list specifically, not the whole page.
    preg_match('/<ul class="page-breadcrumb">.*?<\/ul>/s', $content, $matches);
    $breadcrumb = $matches[0] ?? '';

    expect($breadcrumb)->not->toContain('منوعات')
        ->and(strpos($breadcrumb, 'Grandparent'))->toBeLessThan(strpos($breadcrumb, 'Parent'))
        ->and(strpos($breadcrumb, 'Parent'))->toBeLessThan(strpos($breadcrumb, 'Immediate Group'))
        ->and($breadcrumb)->toContain('href="/var-group-1.htm"')
        ->and($breadcrumb)->toContain('href="/var-group-2.htm"')
        ->and($breadcrumb)->toContain('href="/var-group-3.htm"');
});

it('show: no group at all (group_id points nowhere) — page title/h3 degrade to just " - {item}", no crash', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Orphan Item', 'group_id' => 999]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title"> - Orphan Item</h3>');
});

it('show: detail table date row uses CoolShortDate() formatting, not a raw Y-m-d string', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'mytime' => mktime(0, 0, 0, 6, 23, 2026),
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('الثلاثاء 23 يونيو 2026 مـ')
        ->and($content)->toContain('class="w2a-item-details-card"');
});

it('show: sidebar boxes use compact media cards with the correct downloads/date metadata', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Main Item', 'group_id' => 5, 'hits' => 0, 'downcount' => 0, 'mytime' => null],
        ['id' => 2, 'title' => 'Sidebar Item', 'group_id' => 5, 'hits' => 10, 'downcount' => 42, 'mytime' => mktime(0, 0, 0, 6, 23, 2026)],
    ]);

    $content = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-chat-sidebar-list"')
        ->and($content)->toContain('class="w2a-chat-sidebar-item"')
        ->and($content)->toContain('<i class="fa fa-download" aria-hidden="true"></i> 42 مرة')
        ->and($content)->toContain('<i class="fa fa-calendar" aria-hidden="true"></i> الثلاثاء 23 يونيو 2026 مـ')
        ->and($content)->toContain('href="/var-item-2.htm"');
});
