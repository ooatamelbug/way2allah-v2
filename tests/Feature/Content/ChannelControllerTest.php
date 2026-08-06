<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForChannelController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForChannelController();
});

it('index: lists ALL channels, no eligibility filter at all (unlike live-stream)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Active', 'active' => 0, 'khotab' => 5],
        ['id' => 2, 'title' => 'Inactive too', 'active' => 1, 'khotab' => 10],
    ]);

    $response = $this->get('/channels.htm');

    $response->assertOk()->assertSee('Active')->assertSee('Inactive too');
});

it('index: orders by khotab desc', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Fewer', 'khotab' => 2],
        ['id' => 2, 'title' => 'More', 'khotab' => 20],
    ]);

    $content = $this->get('/channels.htm')->getContent();

    expect(strpos($content, 'More'))->toBeLessThan(strpos($content, 'Fewer'));
});

it('show: renders groups/series/items scoped to the channel and a populated most-downloaded/newest sidebar', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan', 'khotab' => 1]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Item One', 'vedio' => 1, 'hits' => 10, 'time' => 100, 'hidden' => 0],
    ]);

    $response = $this->get('/channel-5.htm');

    $response->assertOk()
        ->assertSee('Item One')
        ->assertSeeInOrder(['الأكثر تحميلا', 'Item One', 'جديد المواد', 'Item One']);
});

it('show: 404s for a nonexistent channel', function () {
    $this->get('/channel-999.htm')->assertNotFound();
});

it('showAuthor: filters groups/series/items by author, and leaves the most-downloaded/newest boxes empty (IF-012)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Chan']);
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Q', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'title' => 'By author 9', 'vedio' => 1, 'hits' => 999, 'time' => 1, 'hidden' => 0],
        ['id' => 2, 'channel_id' => 5, 'author' => 42, 'ser_id' => 0, 'group_id' => 0, 'title' => 'By other author', 'vedio' => 1, 'hits' => 1, 'time' => 1, 'hidden' => 0],
    ]);

    $response = $this->get('/channel-5-9.htm');

    $response->assertOk()
        ->assertSee('By author 9')
        ->assertDontSee('By other author');

    // The item with the highest hits (999) belongs to a DIFFERENT author and
    // must never appear — if the sidebar were accidentally populated here
    // (like channel.php's page, but unlike author.php's), it would leak in.
    $response->assertDontSee('999');
});

it('showAuthor: 404s for a nonexistent channel', function () {
    $this->get('/channel-999-1.htm')->assertNotFound();
});
