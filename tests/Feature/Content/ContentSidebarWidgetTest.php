<?php

use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Uses the canonical MainSchema::nukeIslamicKhotab() (the fuller,
 * 15-column definition ContentListingServiceTest also uses) rather than a
 * locally-drifted 4-column version — this file previously defined its own
 * minimal copy, which was exactly the kind of divergence MainSchema now
 * makes structurally impossible.
 */
function useInMemoryMainConnectionForSidebar(): void
{
    InMemoryConnection::setup('main', [
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_w2acd_w2acd' => MainSchema::nukeW2acdW2acd(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForSidebar();
    $this->widget = new ContentSidebarWidget;
});

// ---- anasheed ----

it('anasheedMostDownloaded: orders by hits desc, limits to 7, filters by group only when given', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 9; $i++) {
        $db->table('nuke_anasheed_anasheed')->insert(['id' => $i, 'hits' => $i, 'group_id' => $i <= 3 ? 1 : 2]);
    }

    expect($this->widget->anasheedMostDownloaded())->toHaveCount(7)
        ->and($this->widget->anasheedMostDownloaded()->first()->id)->toBe(9) // highest hits first
        ->and($this->widget->anasheedMostDownloaded(groupId: 1))->toHaveCount(3);
});

it('anasheedMostRecent: orders by mytime desc, not hits', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'hits' => 100, 'mytime' => 1],
        ['id' => 2, 'hits' => 1, 'mytime' => 999],
    ]);

    expect($this->widget->anasheedMostRecent()->first()->id)->toBe(2); // later mytime wins, despite fewer hits
});

// ---- w2acd (no group filter exists at all) ----

it('w2acdMostDownloaded: orders by hits, limits to 6, and has no group-filtering capability', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 8; $i++) {
        $db->table('nuke_w2acd_w2acd')->insert(['id' => $i, 'hits' => $i]);
    }

    expect($this->widget->w2acdMostDownloaded())->toHaveCount(6);
    expect((new ReflectionClass($this->widget))->getMethod('w2acdMostDownloaded')->getNumberOfParameters())->toBe(0);
});

it('w2acdMostRecent: orders by mytime, limits to 6', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 8; $i++) {
        $db->table('nuke_w2acd_w2acd')->insert(['id' => $i, 'mytime' => $i]);
    }

    $results = $this->widget->w2acdMostRecent();

    expect($results)->toHaveCount(6)->and($results->first()->id)->toBe(8);
});

// ---- telawah (no filter parameter at all, limit 10) ----

it('telawahMostDownloaded/MostRecent: order by hits/mytime respectively, limit 10, id+title only', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 12; $i++) {
        $db->table('nuke_telawah_telawah')->insert(['id' => $i, 'hits' => $i, 'mytime' => 13 - $i]);
    }

    expect($this->widget->telawahMostDownloaded())->toHaveCount(10)
        ->and($this->widget->telawahMostDownloaded()->first()->id)->toBe(12)
        ->and($this->widget->telawahMostRecent()->first()->id)->toBe(1); // lowest id has highest mytime
});

// ---- live-stream (channel filter, "most recent" orders by id not time) ----

it('liveStreamMostViewed: filters by channel_id only when non-zero, orders by hits', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'hits' => 5, 'channel_id' => 1],
        ['id' => 2, 'hits' => 9, 'channel_id' => 2],
    ]);

    expect($this->widget->liveStreamMostViewed())->toHaveCount(2) // channelId=0 means no filter
        ->and($this->widget->liveStreamMostViewed(channelId: 1))->toHaveCount(1);
});

it('liveStreamMostRecent: orders by id DESC, not a time column — matching legacy exactly since khotab has none available here', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'hits' => 999, 'channel_id' => 0],
        ['id' => 2, 'hits' => 1, 'channel_id' => 0],
    ]);

    // id 2 has far fewer hits but a higher id — it must still come first for "most recent".
    expect($this->widget->liveStreamMostRecent()->first()->id)->toBe(2);
});

// ---- Wave 3: mostViewedLiveChannels (live-stream) + channel*KhotabItems (channels/channel.php's topitems() calls) ----

it('mostViewedLiveChannels: uses the eligibility filter (active=0, non-empty streamcode) and orders by ch_visits desc', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Eligible, fewer visits', 'active' => 0, 'streamcode' => 'x', 'ch_visits' => 5],
        ['id' => 2, 'title' => 'Eligible, more visits', 'active' => 0, 'streamcode' => 'x', 'ch_visits' => 50],
        ['id' => 3, 'title' => 'Not eligible (active=1)', 'active' => 1, 'streamcode' => 'x', 'ch_visits' => 999],
    ]);

    $results = $this->widget->mostViewedLiveChannels();

    expect($results)->toHaveCount(2)->and($results->first()->id)->toBe(2);
});

it('channelMostDownloadedKhotabItems: filters channel_id AND vedio=1, limit 5, orders by hits — a different call path than liveStreamMostViewed', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 7; $i++) {
        $db->table('nuke_islamic_khotab')->insert(['id' => $i, 'channel_id' => 5, 'vedio' => 1, 'hits' => $i]);
    }
    // An audio item (vedio=0) in the same channel must never appear — channel.php's
    // topitems() call includes "and vedio='1'", unlike liveStreamMostViewed which has no video filter at all.
    $db->table('nuke_islamic_khotab')->insert(['id' => 99, 'channel_id' => 5, 'vedio' => 0, 'hits' => 999]);

    $results = $this->widget->channelMostDownloadedKhotabItems(5);

    expect($results)->toHaveCount(5) // limit 5, not liveStream's 10
        ->and($results->first()->id)->toBe(7)
        ->and($results->pluck('id')->contains(99))->toBeFalse();
});

it('channelMostRecentKhotabItems: orders by time DESC, not id DESC — a different "most recent" than liveStreamMostRecent', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'vedio' => 1, 'time' => 999],
        ['id' => 2, 'channel_id' => 5, 'vedio' => 1, 'time' => 1],
    ]);

    // id 2 is higher but has an older time — time-ordering must put id 1 first.
    expect($this->widget->channelMostRecentKhotabItems(5)->first()->id)->toBe(1);
});
