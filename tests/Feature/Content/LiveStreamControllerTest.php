<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForLiveStream(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_sat_sats' => MainSchema::nukeSatSats(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForLiveStream();
});

it('index: lists only eligible channels (active=0, non-empty streamcode)', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Eligible', 'active' => 0, 'streamcode' => 'x', 'ch_visits' => 0],
        ['id' => 2, 'title' => 'Not eligible', 'active' => 1, 'streamcode' => 'x', 'ch_visits' => 0],
    ]);

    $response = $this->get('/live-stream.htm');

    $response->assertOk()->assertSee('Eligible')->assertDontSee('Not eligible');
});

it('show: renders streamcode as raw unescaped HTML, not entity-encoded', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        'id' => 5, 'title' => 'X', 'active' => 0, 'streamcode' => '<iframe src="https://player.example/5"></iframe>', 'ch_visits' => 0,
    ]);

    $response = $this->get('/live-channel-5.htm');

    $response->assertOk()->assertSee('<iframe src="https://player.example/5"></iframe>', false);
});

it('show: increments ch_visits by exactly 1 per view', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'X', 'active' => 0, 'streamcode' => 'x', 'ch_visits' => 10]);

    $this->get('/live-channel-5.htm');

    expect((int) DB::connection('main')->table('nuke_sat_channels')->find(5)->ch_visits)->toBe(11);
});

it('show: displays ch_visits + 1 (pre-increment compensation), matching live_channel_details() exactly', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'X', 'active' => 0, 'streamcode' => 'x', 'ch_visits' => 10]);

    // The rendered page must show 11 (10 + 1 display compensation), even
    // though the actual stored value at render time was still 10.
    $this->get('/live-channel-5.htm')->assertSee('11');
});

it('show: IF-009 — a channel with an empty streamcode is still directly viewable (no streamcode check on this route, unlike the directory)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'X', 'active' => 0, 'streamcode' => '', 'ch_visits' => 0]);

    $this->get('/live-channel-5.htm')->assertOk();
});

it('show: 404s for a nonexistent or inactive channel', function () {
    $this->get('/live-channel-999.htm')->assertNotFound();

    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 6, 'title' => 'X', 'active' => 1, 'streamcode' => 'x', 'ch_visits' => 0]);
    $this->get('/live-channel-6.htm')->assertNotFound();
});

it('featured: renders the hardcoded channel 51 and does NOT increment ch_visits (IF-010)', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 51, 'title' => 'Featured', 'active' => 0, 'streamcode' => '<div>x</div>', 'ch_visits' => 7]);

    $response = $this->get('/live-stream/featured');

    $response->assertOk()->assertSee('<div>x</div>', false);
    expect((int) DB::connection('main')->table('nuke_sat_channels')->find(51)->ch_visits)->toBe(7); // unchanged
});

it('featured: 404s if channel 51 does not exist', function () {
    $this->get('/live-stream/featured')->assertNotFound();
});

it('redirects the raw legacy live-stream/live.php path to the new featured route', function () {
    $this->get('/live-stream/live.php')->assertRedirect('/live-stream/featured');
});
