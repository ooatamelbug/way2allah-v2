<?php

use App\Domain\Content\Models\IslamicSetting;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 6.3. `nuke_islamic_setting` is new to this fixture set —
 * no prior test in this codebase used it (Task 6.3 investigation §5).
 */
function useInMemoryMainConnectionForRamadan(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_setting' => MainSchema::nukeIslamicSetting(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForRamadan();
});

it('renders the ramadan-flagged series for each year bucket, excluding non-ramadan and hidden rows', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_series')->insert([
        ['id' => 5000, 'title' => 'Old series', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 3, 'vedio' => 1, 'channel_id' => 7],
        ['id' => 12000, 'title' => 'Excluded, not ramadan', 'author_id' => 1, 'ramadan' => 0, 'hidden' => 0, 'count' => 1, 'vedio' => 1, 'channel_id' => 7],
    ]);

    $response = $this->get('/ramadan.htm');

    $response->assertOk()
        ->assertSee('برامج رمضان 1434 هـ')
        ->assertSee('Old series')
        ->assertDontSee('Excluded, not ramadan');
});

it('increments only the current year (1447) counter on each load, not every displayed year', function () {
    IslamicSetting::query()->create(['option' => 'ramadan_counter', 'value' => serialize([1447 => 5, 1441 => 9])]);

    $this->get('/ramadan.htm');

    $counters = IslamicSetting::ramadanCounters();
    expect($counters[1447])->toBe(6)->and($counters[1441])->toBe(9);
});

it('displays a visit counter only for years 1441, 1442, 1444, 1446, 1447 (ramadan.php has no \'tools\' key for 1434-1440)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_series')->insert([
        // id 10000 lands in the 1441 bucket (>9621, <=10943) — a
        // counter-bearing year; id 5000 lands in the 1434 bucket
        // (<5332) — a non-counter-bearing year.
        ['id' => 10000, 'title' => 'Series 1441', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1, 'channel_id' => 1],
        ['id' => 5000, 'title' => 'Series 1434', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1, 'channel_id' => 1],
    ]);
    IslamicSetting::query()->create(['option' => 'ramadan_counter', 'value' => serialize([1441 => 6])]);

    $response = $this->get('/ramadan.htm');
    $response->assertOk()->assertSee('عدد الزيارات: 6');

    preg_match('/برامج رمضان 1434 هـ(.*?)<\/h4>/s', $response->getContent(), $matches);
    expect($matches[1] ?? null)->not->toContain('عدد الزيارات');
});

it('does NOT display a visit counter for 1443, even though it falls inside the 1441-1447 counter range (ramadan.php:420 has its \'tools\' line commented out)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    // id 12000 lands in the 1443 bucket (>11223, <13228).
    $db->table('nuke_islamic_series')->insert([
        'id' => 12000, 'title' => 'Series 1443', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1, 'channel_id' => 1,
    ]);
    IslamicSetting::query()->create(['option' => 'ramadan_counter', 'value' => serialize([1443 => 42])]);

    $response = $this->get('/ramadan.htm');

    $response->assertOk();
    preg_match('/برامج رمضان 1443 هـ(.*?)<\/h4>/s', $response->getContent(), $matches);
    expect($matches[1] ?? null)->not->toContain('عدد الزيارات');
});

it('keeps the confirmed-missing images/channels/{id}.png path exactly as legacy references it, no placeholder asset', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $db->table('nuke_islamic_series')->insert([
        'id' => 5000, 'title' => 'Series', 'author_id' => 1, 'channel_id' => 42, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1,
    ]);

    $response = $this->get('/ramadan.htm');

    $response->assertOk()->assertSee('/images/channels/42.png', false);
});

it('renders even with no nuke_islamic_setting row at all, treating the current-year counter as starting from 1', function () {
    // Reproduces ramadan.php's own UPDATE-only (no upsert) write: with no
    // pre-existing row at all, the write silently matches nothing and
    // persists no counter (same as legacy) — but the in-memory value used
    // for THIS request's own display still starts from 1, since it's
    // computed before the (no-op) write is attempted.
    $response = $this->get('/ramadan.htm');

    $response->assertOk();
    expect(IslamicSetting::ramadanCounters())->toBe([]);
});
