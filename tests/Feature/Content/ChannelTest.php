<?php

use App\Domain\Content\Models\Channel;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Uses an in-memory SQLite override of the 'main' connection, matching the
 * confirmed nuke_sat_channels column list (00-database-schema.md) — per
 * Roadmap task 1.1.
 */
function useInMemoryMainConnectionForChannels(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_sat_sats' => MainSchema::nukeSatSats(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForChannels();
});

it('maps to nuke_sat_channels with no timestamps and the confirmed primary key', function () {
    $channel = Channel::create([
        'title' => 'Way2Allah TV',
        'sat_id' => 7,
        'beam' => 3,
        'active' => 1,
    ]);

    expect($channel->getTable())->toBe('nuke_sat_channels')
        ->and($channel->getConnectionName())->toBe('main')
        ->and($channel->timestamps)->toBeFalse()
        ->and($channel->exists)->toBeTrue()
        ->and($channel->id)->toBeInt();
});

it('casts beam to an integer, matching its confirmed tinyint type', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'X', 'beam' => '5']);

    $channel = Channel::find(1);

    expect($channel->beam)->toBeInt()->toBe(5);
});

it('retrieves all confirmed columns from a seeded row without error', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        'id' => 42,
        'title' => 'Sample Channel',
        'notes' => 'some notes',
        'programs' => 'program list',
        'time' => 1700000000,
        'freq' => '11000',
        'srate' => '27500',
        'fec' => '3/4',
        'polar' => 'H',
        'enc' => 'FTA',
        'beam' => 2,
        'sat_id' => 1,
        'active' => 1,
        'khotab' => 1,
        'anasheed' => 0,
        'streamcode' => 'abc123',
        'ch_visits' => 150,
    ]);

    $channel = Channel::find(42);

    expect($channel->title)->toBe('Sample Channel')
        ->and($channel->sat_id)->toBe(1)
        ->and($channel->ch_visits)->toBe(150);
});

/**
 * Wave 3 (live-stream.md §5, direct read of live-stream/functions.php).
 * `active = 0` means eligible/live — the opposite of what the column name
 * suggests. Proven explicitly so a future reader can't "fix" this scope
 * by flipping the comparison, mistaking it for a bug.
 */
it('scopeEligibleForLiveStream: active=0 is eligible, active=1 is not — the inverted-sounding legacy semantics preserved exactly', function () {
    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'Eligible', 'active' => 0, 'streamcode' => '<iframe></iframe>'],
        ['id' => 2, 'title' => 'Not eligible (active=1)', 'active' => 1, 'streamcode' => '<iframe></iframe>'],
        ['id' => 3, 'title' => 'Not eligible (no streamcode)', 'active' => 0, 'streamcode' => ''],
        ['id' => 4, 'title' => 'Not eligible (null streamcode)', 'active' => 0, 'streamcode' => null],
    ]);

    $eligible = Channel::eligibleForLiveStream()->get();

    expect($eligible)->toHaveCount(1)->and($eligible->first()->id)->toBe(1);
});

it('beamForDisplay: falls back to 1 only when beam is empty, matching live-stream/functions.php:79 exactly', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        ['id' => 1, 'title' => 'X', 'beam' => 0],
        ['id' => 2, 'title' => 'Y', 'beam' => 5],
    ]);

    expect(Channel::find(1)->beamForDisplay())->toBe(1)
        ->and(Channel::find(2)->beamForDisplay())->toBe(5);
});

it('satellite: resolves the belongsTo relationship via sat_id', function () {
    DB::connection('main')->table('nuke_sat_sats')->insert(['id' => 7, 'title' => 'Nilesat', 'pos' => '7W']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'X', 'sat_id' => 7]);

    expect(Channel::find(1)->satellite->title)->toBe('Nilesat');
});

it('recordView(): increments ch_visits (not hits) and never writes lastvisit, via the generalized RecordsView listener', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 1, 'title' => 'X', 'ch_visits' => 10]);

    Channel::find(1)->recordView();

    $row = DB::connection('main')->table('nuke_sat_channels')->find(1);

    expect((int) $row->ch_visits)->toBe(11);
    expect(property_exists($row, 'lastvisit'))->toBeFalse(); // column doesn't exist on this table at all
});
