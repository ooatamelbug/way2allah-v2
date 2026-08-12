<?php

use App\Domain\Content\Models\IslamicSetting;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 6.3. Covers `IslamicSetting`'s reproduction of
 * `pages/ramadan.php`'s confirmed SELECT/UPDATE predicate asymmetry —
 * preserved exactly per explicit authorization, not normalized.
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_setting' => MainSchema::nukeIslamicSetting(),
    ]);
});

it('ramadanCounters: reads via option=ramadan_counter OR Id=4, matching ramadan.php\'s own SELECT', function () {
    DB::connection('main')->table('nuke_islamic_setting')->insert([
        'Id' => 4, 'option' => 'something_else', 'value' => serialize([1447 => 10]),
    ]);

    expect(IslamicSetting::ramadanCounters())->toBe([1447 => 10]);
});

it('ramadanCounters: returns an empty array when no row matches either predicate branch', function () {
    expect(IslamicSetting::ramadanCounters())->toBe([]);
});

it('ramadanCounters: normalizes a legacy object-shaped serialized value (ramadan1442.php\'s syntax) to an array on read', function () {
    $object = (object) [1442 => 7];
    DB::connection('main')->table('nuke_islamic_setting')->insert([
        'option' => 'ramadan_counter', 'value' => serialize($object),
    ]);

    expect(IslamicSetting::ramadanCounters())->toBe([1442 => 7]);
});

it('incrementRamadanCounter: writes via option=ramadan_counter only — confirmed asymmetry with the SELECT predicate, reproduced exactly', function () {
    // Row matches the SELECT's `Id=4` branch, NOT the `option='ramadan_counter'`
    // branch the UPDATE actually targets — the exact legacy scenario where
    // the read finds a row the write cannot subsequently update.
    DB::connection('main')->table('nuke_islamic_setting')->insert([
        'Id' => 4, 'option' => 'something_else', 'value' => serialize([1447 => 1]), 'edit_time' => 1,
    ]);

    IslamicSetting::incrementRamadanCounter(1447);

    $row = DB::connection('main')->table('nuke_islamic_setting')->where('Id', 4)->first();
    // The row is untouched — the UPDATE's WHERE option='ramadan_counter'
    // never matched it, exactly reproducing ramadan.php's own predicate gap.
    expect(unserialize($row->value))->toBe([1447 => 1]);
});

it('incrementRamadanCounter: writes correctly when the row does match the option predicate, and returns the full updated array', function () {
    DB::connection('main')->table('nuke_islamic_setting')->insert([
        'option' => 'ramadan_counter', 'value' => serialize([1447 => 4, 1441 => 2]),
    ]);

    $counters = IslamicSetting::incrementRamadanCounter(1447);

    expect($counters)->toBe([1447 => 5, 1441 => 2]);
    expect(IslamicSetting::ramadanCounters())->toBe([1447 => 5, 1441 => 2]);
});

it('incrementRamadanCounter: starts a new year at 1 when absent from the stored counters', function () {
    DB::connection('main')->table('nuke_islamic_setting')->insert([
        'option' => 'ramadan_counter', 'value' => serialize([1441 => 2]),
    ]);

    $counters = IslamicSetting::incrementRamadanCounter(1447);

    expect($counters[1447])->toBe(1);
});
