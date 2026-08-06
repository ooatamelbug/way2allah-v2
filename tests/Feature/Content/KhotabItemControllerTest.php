<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabItemController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'ips' => MainSchema::ips(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabItemController();
});

function insertKhotabAuthor(int $id = 1): void
{
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => $id, 'name' => 'Author Name', 'prename' => 'Sheikh',
    ]);
}

it('show: renders the item detail page with title, description, and author', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item One', 'description' => 'A description',
        'vedio' => 1, 'hidden' => 0, 'hits' => 5, 'downcount' => 2,
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Item One')->assertSee('A description');
});

it('show: 404s for a hidden item', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Hidden Item', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab-item-1.htm')->assertNotFound();
});

it('show: 404s when the item belongs to a hidden series', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'Series', 'vedio' => 1, 'hidden' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'ser_id' => 10, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $this->get('/khotab-item-1.htm')->assertNotFound();
});

it('show: 404s when the item belongs to a hidden group', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 1, 'title' => 'Group', 'vedio' => 1, 'hidden' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'group_id' => 20, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $this->get('/khotab-item-1.htm')->assertNotFound();
});

it('show: increments hits (recordView) on load', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 10,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->hits)->toBe(11);
});

it('show: fixes IF-014 — a VIDEO item\'s "Most Downloaded"/"Newest" sidebar shows video items, not audio', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'The Video Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 1],
        ['id' => 2, 'author' => 1, 'title' => 'Other Video', 'vedio' => 1, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Some Audio Item', 'vedio' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Other Video')->assertDontSee('Some Audio Item');
});

it('show: on an AUDIO item, the sidebar shows audio items, not video', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'The Audio Item', 'vedio' => 0, 'hidden' => 0, 'hits' => 1],
        ['id' => 2, 'author' => 1, 'title' => 'Other Audio', 'vedio' => 0, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Some Video Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('Other Audio')->assertDontSee('Some Video Item');
});

it('show: renders mirrors', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'HD quality', 'link' => '/tmp/x.mp4', 'linksize' => 100, 'hits' => 3,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('HD quality');
});

it('show: only renders comments with view=1, gated by the stored comments counter', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 2,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        ['id' => 1, 'khid' => 1, 'uname' => 'Visible User', 'comment' => 'approved comment', 'view' => 1, 'mytime' => 100],
        ['id' => 2, 'khid' => 1, 'uname' => 'Pending User', 'comment' => 'unapproved comment', 'view' => 0, 'mytime' => 100],
    ]);

    $response = $this->get('/khotab-item-1.htm');

    $response->assertOk()->assertSee('approved comment')->assertDontSee('unapproved comment');
});

it('show: does not query comments at all when the stored counter is 0, even if view=1 rows exist', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Someone', 'comment' => 'a stray approved comment', 'view' => 1, 'mytime' => 100,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertDontSee('a stray approved comment');
});

it('show: IF-019 fix — comment flags render from images/flags/, not flags/', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'comments' => 1,
    ]);
    DB::connection('main')->table('nuke_islamic_comments')->insert([
        'id' => 1, 'khid' => 1, 'uname' => 'Visitor', 'code' => 'eg', 'comment' => 'hello', 'view' => 1, 'mytime' => 100,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('images/flags/eg.png', false);
});

it('show: IF-020 fix — the PDF link resolves to a real route', function () {
    insertKhotabAuthor();
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'pdf' => 1,
    ]);

    $this->get('/khotab-item-1.htm')->assertOk()->assertSee('/khotab-item-pdf-1.htm', false);

    $this->get('/khotab-item-pdf-1.htm')->assertRedirect('/media/pdf/0/1.pdf');
});

it('download: increments downcount and streams the linked file', function () {
    $file = tempnam(sys_get_temp_dir(), 'khotab');
    file_put_contents($file, 'file-bytes');

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Item', 'link' => $file, 'linksize' => 10, 'downcount' => 4,
    ]);

    $response = $this->get('/khotab-download-1.htm');

    $response->assertOk();
    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->downcount)->toBe(5);

    unlink($file);
});

it('downloadMirror: increments the mirror\'s own hits, not the item\'s downcount', function () {
    $file = tempnam(sys_get_temp_dir(), 'khotabmirror');
    file_put_contents($file, 'mirror-bytes');

    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item', 'downcount' => 0]);
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 1, 'khid' => 1, 'link' => $file, 'hits' => 7]);

    $this->get('/khotab-mirror-1-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_mirror')->find(1)->hits)->toBe(8);
    expect(DB::connection('main')->table('nuke_islamic_khotab')->find(1)->downcount)->toBe(0);

    unlink($file);
});

it('storeComment: creates an unmoderated (view=0) comment and returns "1"', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $response = $this->post('/khotab-item-1/comments', [
        'user_nickname' => 'Test User',
        'user_comment' => 'Great lesson',
    ]);

    $response->assertOk()->assertSeeText('1');

    $comment = DB::connection('main')->table('nuke_islamic_comments')->where('khid', 1)->first();
    expect($comment->uname)->toBe('Test User');
    expect($comment->view)->toBe(0);
});

it('storeComment: post-Wave-4 fix — resolves the country code via GeoIpLookup instead of leaving it blank', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 0, 'endip_num' => 4294967295, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User', 'user_comment' => 'Great lesson'])
        ->assertOk();

    $comment = DB::connection('main')->table('nuke_islamic_comments')->where('khid', 1)->first();
    expect($comment->code)->toBe('eg');
});

it('storeComment: post-Wave-4 fix — dispatches CommentPosted', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Domain\Content\Events\CommentPosted::class]);

    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User', 'user_comment' => 'Great lesson'])
        ->assertOk();

    \Illuminate\Support\Facades\Event::assertDispatched(\App\Domain\Content\Events\CommentPosted::class);
});

it('storeComment: returns "2" when the nickname is missing', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_comment' => 'Great lesson'])
        ->assertSeeText('2');
});

it('storeComment: returns "3" when the comment body is missing', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Item']);

    $this->post('/khotab-item-1/comments', ['user_nickname' => 'Test User'])
        ->assertSeeText('3');
});
