<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CategoryAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 3 — Khotab item add / remove through the real
 * routes. Contract: plan §17 (OLD `cat_add_to_item` / `cat_del_from_item`);
 * storage format: plan §20 (owner decision — canonical `|a|b|`, both forms
 * parsed, only mutated rows written).
 */
beforeEach(function () {
    H::setUp();
    H::categories(3, 5, 9, 17, 99);
    $this->actingAs(H::superAdmin(), 'admin');
});

function addKhotabCat(int $item, int $cat)
{
    return test()->post(route('admin.categories.khotab.item.add', $item), ['category_id' => $cat]);
}

function removeKhotabCat(int $item, int $cat)
{
    return test()->post(route('admin.categories.khotab.item.remove', $item), ['category_id' => $cat]);
}

// ---- Proofs 1 + 2: both stored forms parse; a mutation writes canonical ----

it('adds to a no-leading-pipe row and writes the canonical form (owner example 1)', function () {
    $item = H::khotabItem(['cat' => '3|17|5|']);

    addKhotabCat($item, 9)->assertRedirect(route('admin.categories.khotab.item', $item))->assertSessionHas('success');

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|3|17|5|9|')
        ->and(H::videoCount(9))->toBe(11);
});

it('adds to a canonical row and keeps it canonical', function () {
    $item = H::khotabItem(['cat' => '|3|17|5|']);

    addKhotabCat($item, 9);

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|3|17|5|9|');
});

it('adds to an empty or null row', function (?string $raw) {
    $item = H::khotabItem(['cat' => $raw]);

    addKhotabCat($item, 9);

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|9|');
})->with([null, '', '|']);

// ---- Proof 3: first-position removal from a no-leading-pipe row ----

it('removes the first-position category from a no-leading-pipe row (owner example 2)', function () {
    // The case OLD's own `str_replace('|3|', '|', $cat)` cannot match: the
    // leading pipe it needs is missing, so OLD would leave 3 in place.
    $item = H::khotabItem(['cat' => '3|17|5|']);

    removeKhotabCat($item, 3)->assertSessionHas('success');

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|17|5|')
        ->and(H::videoCount(3))->toBe(9);
});

it('removes a middle and a last category from either form', function (string $raw, int $cat, string $expected) {
    $item = H::khotabItem(['cat' => $raw]);

    removeKhotabCat($item, $cat);

    expect(H::cat('nuke_islamic_khotab', $item))->toBe($expected);
})->with([
    ['3|17|5|', 17, '|3|5|'],
    ['3|17|5|', 5, '|3|17|'],
    ['|3|17|5|', 3, '|17|5|'],
    ['|3|17|5|', 5, '|3|17|'],
    ['3|', 3, ''],
]);

it('does not confuse 17 with 171 on remove', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 171, 'title' => 'x', 'main_cat' => 0]);
    $item = H::khotabItem(['cat' => '171|17|']);

    removeKhotabCat($item, 17);

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|171|');
});

// ---- Proofs 4 + 5: duplicate add / absent remove are true no-ops ----

it('treats adding an existing category as a no-op — no write, no counter, no audit, no normalization (owner example 3)', function () {
    $item = H::khotabItem(['cat' => '3|17|5|']);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => addKhotabCat($item, 17)->assertSessionHas('error'));

    expect($writes)->toBe([])
        ->and(H::cat('nuke_islamic_khotab', $item))->toBe('3|17|5|')
        ->and(H::snapshot())->toBe($before)
        ->and(H::auditRows())->toBe([]);
});

it('treats removing an absent category as a no-op — no write, no counter, no audit, no normalization (owner example 4)', function () {
    $item = H::khotabItem(['cat' => '3|17|5|']);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => removeKhotabCat($item, 99)->assertSessionHas('error'));

    expect($writes)->toBe([])
        ->and(H::cat('nuke_islamic_khotab', $item))->toBe('3|17|5|')
        ->and(H::snapshot())->toBe($before)
        ->and(H::auditRows())->toBe([]);
});

// ---- counters ----

it('floors video_count at zero on remove, as OLD did', function () {
    DB::connection('main')->table('nuke_w2a_cat')->where('id', 3)->update(['video_count' => 0]);
    $item = H::khotabItem(['cat' => '|3|']);

    removeKhotabCat($item, 3);

    expect(H::videoCount(3))->toBe(0)->and(H::cat('nuke_islamic_khotab', $item))->toBe('');
});

it('moves only video_count, never anasheed_count', function () {
    $item = H::khotabItem(['cat' => '']);

    addKhotabCat($item, 9);

    expect(H::videoCount(9))->toBe(11)->and(H::anasheedCount(9))->toBe(10);
});

// ---- cascade: OLD cat_repair_group + cat_repair_series ----

it('repairs the item group and series to the union of their items', function () {
    $series = H::series(['cat' => '17|']);
    $group = H::group(['cat' => '17|']);
    $item = H::khotabItem(['cat' => '17|', 'ser_id' => $series, 'group_id' => $group]);
    H::khotabItem(['cat' => '5|', 'ser_id' => $series, 'group_id' => $group]);

    addKhotabCat($item, 9);

    // Parents changed content, so they are rewritten — canonically.
    expect(H::cat('nuke_islamic_series', $series))->toBe('|17|9|5|')
        ->and(H::cat('nuke_islamic_groups', $group))->toBe('|17|9|5|');
});

it('keeps a category on the series while another item still holds it', function () {
    $series = H::series(['cat' => '|3|17|']);
    $item = H::khotabItem(['cat' => '|3|17|', 'ser_id' => $series]);
    H::khotabItem(['cat' => '|3|', 'ser_id' => $series]);

    removeKhotabCat($item, 3);
    expect(H::cat('nuke_islamic_series', $series))->toBe('|3|17|');   // same set: not rewritten

    removeKhotabCat($item, 17);
    expect(H::cat('nuke_islamic_series', $series))->toBe('|3|');
});

it('does not touch counters during repair', function () {
    $series = H::series(['cat' => '']);
    $item = H::khotabItem(['cat' => '', 'ser_id' => $series]);

    addKhotabCat($item, 9);

    expect(H::videoCount(9))->toBe(11);   // +1 for the item only, not for the series
});

it('skips the cascade for an item with no group or series', function () {
    $item = H::khotabItem(['cat' => '', 'ser_id' => 0, 'group_id' => 0]);

    $writes = H::writesDuring(fn () => addKhotabCat($item, 9));

    expect(collect($writes)->filter(fn ($w) => str_contains($w['sql'], 'nuke_islamic_series') || str_contains($w['sql'], 'nuke_islamic_groups')))->toBeEmpty();
});

it('tolerates a missing parent series, as OLD did', function () {
    $item = H::khotabItem(['cat' => '', 'ser_id' => 4040]);

    addKhotabCat($item, 9)->assertSessionHas('success');

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|9|');
});

// ---- Proof 6: no unrelated row is normalized ----

it('leaves every unrelated row byte-identical, including legacy-form siblings and a same-set parent', function () {
    $series = H::series(['cat' => '17|3|']);                                  // legacy form, already holds 3 and 17
    $item = H::khotabItem(['cat' => '17|', 'ser_id' => $series]);
    $sibling = H::khotabItem(['cat' => '3|17|', 'ser_id' => $series]);     // legacy form
    $otherSeries = H::series(['cat' => '5|']);
    $stranger = H::khotabItem(['cat' => '5|', 'ser_id' => $otherSeries]);
    $group = H::group(['cat' => '5|']);
    $nasheed = H::anasheedItem(['cat_id' => '3|5|']);

    addKhotabCat($item, 3);

    expect(H::cat('nuke_islamic_khotab', $item))->toBe('|17|3|')        // the mutated row: canonical
        ->and(H::cat('nuke_islamic_series', $series))->toBe('17|3|')    // same set: untouched, still legacy
        ->and(H::cat('nuke_islamic_khotab', $sibling))->toBe('3|17|')
        ->and(H::cat('nuke_islamic_khotab', $stranger))->toBe('5|')
        ->and(H::cat('nuke_islamic_series', $otherSeries))->toBe('5|')
        ->and(H::cat('nuke_islamic_groups', $group))->toBe('5|')
        ->and(H::cat('nuke_anasheed_anasheed', $nasheed))->toBe('3|5|');
});

// ---- Proof 7: no bulk normalization ----

it('writes exactly the rows the operation changes — every UPDATE is keyed to one id', function () {
    foreach (range(1, 20) as $i) {
        H::khotabItem(['cat' => "3|{$i}|"]);   // twenty legacy-form rows
    }
    $item = H::khotabItem(['cat' => '3|17|5|']);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => addKhotabCat($item, 9));

    $updates = collect($writes)->filter(fn ($w) => stripos($w['sql'], 'update') === 0)->values()->all();
    expect($updates)->toHaveCount(2);   // the item's cat, and category 9's counter
    expect($updates[0]['sql'])->toContain('"nuke_islamic_khotab"')->toContain('where "id" = ?')
        ->and($updates[0]['bindings'][array_key_last($updates[0]['bindings'])])->toBe($item);
    expect($updates[1]['sql'])->toContain('"nuke_w2a_cat"')->toContain('where "id" = ?');

    $after = H::snapshot();
    $expected = $before;
    $expected['khotab'][$item] = '|3|17|5|9|';
    $expected['counters'][9] = [11, 10];
    expect($after)->toBe($expected);
    expect(collect($after['khotab'])->except($item)->filter(fn ($cat) => str_starts_with((string) $cat, '|')))->toBeEmpty();
});

it('never writes the junction tables — the DB triggers own them', function () {
    $item = H::khotabItem(['cat' => '']);

    $writes = H::writesDuring(fn () => addKhotabCat($item, 9));

    expect(collect($writes)->filter(fn ($w) => str_contains($w['sql'], 'category_index')))->toBeEmpty();
});

// ---- input ----

it('refuses an unknown category and changes nothing', function () {
    $item = H::khotabItem(['cat' => '3|']);
    $before = H::snapshot();

    addKhotabCat($item, 4242)->assertSessionHasErrors('category_id');
    $this->post(route('admin.categories.khotab.item.add', $item), [])->assertSessionHasErrors('category_id');

    expect(H::snapshot())->toBe($before);
});

it('returns 404 for a missing item', function () {
    addKhotabCat(4040, 9)->assertNotFound();
});

it('renders the item page with titles, an unknown id marked, and the add select', function () {
    $item = H::khotabItem(['title' => 'درس الفقه', 'cat' => '3|777|']);

    $this->get(route('admin.categories.khotab.item', $item))
        ->assertOk()
        ->assertSee('درس الفقه')
        ->assertSee('تصنيف 3')
        ->assertSee('تصنيف غير معروف')
        ->assertSee('3|777|')
        ->assertSee(route('admin.categories.khotab.item.add', $item), false);
});

it('looks up an item, series or group from the index', function () {
    $item = H::khotabItem();
    $series = H::series();
    $group = H::group();

    $this->get(route('admin.categories.khotab.index', ['item' => $item]))->assertRedirect(route('admin.categories.khotab.item', $item));
    $this->get(route('admin.categories.khotab.index', ['series' => $series]))->assertRedirect(route('admin.categories.khotab.series', $series));
    $this->get(route('admin.categories.khotab.index', ['group' => $group]))->assertRedirect(route('admin.categories.khotab.group', $group));
    $this->from(route('admin.categories.khotab.index'))->get(route('admin.categories.khotab.index', ['item' => 4040]))->assertSessionHas('error');
    $this->get(route('admin.categories.khotab.index'))->assertOk();
});
