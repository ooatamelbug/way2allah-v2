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
