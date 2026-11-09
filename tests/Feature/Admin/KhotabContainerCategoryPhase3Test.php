<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CategoryAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 3 — "اضف للكل" / "امسح من الكل" on a Khotab
 * series or group (OLD `cat_{add_to,del_from}_sub{series,group}`). Scopes
 * per plan §17: series → every item in it; group → loose items only
 * (`ser_id = 0`). Group remove-all ends in a repair, not a direct remove.
 */
beforeEach(function () {
    H::setUp();
    H::categories(3, 5, 9, 17);
    $this->actingAs(H::superAdmin(), 'admin');
});

function bulk(string $type, int $id, string $op, int $cat)
{
    return test()->post(route("admin.categories.khotab.{$type}.{$op}", $id), ['category_id' => $cat]);
}

it('series add-all: adds to every item lacking it (hidden included), then to the series', function () {
    $series = H::series(['cat' => '3|']);
    $a = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    $b = H::khotabItem(['cat' => '|9|3|', 'ser_id' => $series]);         // already holds 9
    $c = H::khotabItem(['cat' => null, 'ser_id' => $series, 'hidden' => 1]);
    $outside = H::khotabItem(['cat' => '3|']);

    bulk('series', $series, 'add-all', 9)->assertRedirect(route('admin.categories.khotab.series', $series));

    expect(H::cat('nuke_islamic_khotab', $a))->toBe('|3|9|')
        ->and(H::cat('nuke_islamic_khotab', $b))->toBe('|9|3|')        // untouched
        ->and(H::cat('nuke_islamic_khotab', $c))->toBe('|9|')
        ->and(H::cat('nuke_islamic_khotab', $outside))->toBe('3|')
        ->and(H::cat('nuke_islamic_series', $series))->toBe('|3|9|')
        ->and(H::videoCount(9))->toBe(10 + 2 + 1);                      // two items + the series
});

it('series remove-all: removes from every holder, then from the series, floored', function () {
    $series = H::series(['cat' => '9|3|']);
    $a = H::khotabItem(['cat' => '9|3|', 'ser_id' => $series]);
    $b = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);           // does not hold 9
    DB::connection('main')->table('nuke_w2a_cat')->where('id', 9)->update(['video_count' => 1]);

    bulk('series', $series, 'remove-all', 9);

    expect(H::cat('nuke_islamic_khotab', $a))->toBe('|3|')
        ->and(H::cat('nuke_islamic_khotab', $b))->toBe('3|')
        ->and(H::cat('nuke_islamic_series', $series))->toBe('|3|')
        ->and(H::videoCount(9))->toBe(0);                               // 1 - 1 - 1, floored
});

it('group add-all: only loose items (ser_id = 0), then the group', function () {
    $group = H::group(['cat' => '3|']);
    $series = H::series(['group_id' => $group]);
    $loose = H::khotabItem(['cat' => '3|', 'group_id' => $group, 'ser_id' => 0]);
    $inSeries = H::khotabItem(['cat' => '3|', 'group_id' => $group, 'ser_id' => $series]);
    $otherGroup = H::khotabItem(['cat' => '3|', 'group_id' => $group + 1]);

    bulk('group', $group, 'add-all', 9);

    expect(H::cat('nuke_islamic_khotab', $loose))->toBe('|3|9|')
        ->and(H::cat('nuke_islamic_khotab', $inSeries))->toBe('3|')
        ->and(H::cat('nuke_islamic_khotab', $otherGroup))->toBe('3|')
        ->and(H::cat('nuke_islamic_groups', $group))->toBe('|3|9|')
        ->and(H::videoCount(9))->toBe(12);
});

it('group remove-all: loose holders only, then REPAIRS the group (OLD asymmetry) without a group counter step', function () {
    $group = H::group(['cat' => '|3|9|']);
    $series = H::series(['group_id' => $group]);
    $loose = H::khotabItem(['cat' => '|3|9|', 'group_id' => $group, 'ser_id' => 0]);
    $inSeries = H::khotabItem(['cat' => '|9|', 'group_id' => $group, 'ser_id' => $series]);

    bulk('group', $group, 'remove-all', 9);

    expect(H::cat('nuke_islamic_khotab', $loose))->toBe('|3|')
        ->and(H::cat('nuke_islamic_khotab', $inSeries))->toBe('|9|')
        // repaired = union of ALL group items, so 9 stays via the series item
        ->and(H::cat('nuke_islamic_groups', $group))->toBe('|3|9|')
        ->and(H::videoCount(9))->toBe(9);                               // one loose item only

    $actions = collect(H::auditRows())->pluck('action')->all();
    expect($actions)->toBe(['del_cat_item']);                           // set unchanged → no repair row
});

it('group remove-all drops the category from the group once no item holds it', function () {
    $group = H::group(['cat' => '|3|9|']);
    H::khotabItem(['cat' => '|3|9|', 'group_id' => $group]);

    bulk('group', $group, 'remove-all', 9);

    expect(H::cat('nuke_islamic_groups', $group))->toBe('|3|')
        ->and(collect(H::auditRows())->pluck('action')->all())->toBe(['del_cat_item', 'repair_cat_group']);
});

it('is idempotent: a repeated add-all writes and audits nothing', function () {
    $series = H::series(['cat' => '3|']);
    H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    bulk('series', $series, 'add-all', 9);
    $before = H::snapshot();
    $audits = count(H::auditRows());

    $writes = H::writesDuring(fn () => bulk('series', $series, 'add-all', 9));

    expect($writes)->toBe([])->and(H::snapshot())->toBe($before)->and(H::auditRows())->toHaveCount($audits);
});

it('audits every changed item and the container, with structured metadata', function () {
    $series = H::series(['cat' => '3|']);
    $a = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    $b = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);

    bulk('series', $series, 'add-all', 9);

    $rows = H::auditRows();
    expect(collect($rows)->map(fn ($r) => [$r->action, $r->entity_type, (int) $r->entity_id])->all())->toBe([
        ['add_cat_item', 'item', $a],
        ['add_cat_item', 'item', $b],
        ['add_cat_series', 'series', $series],
    ]);
    expect(json_decode($rows[2]->metadata, true))->toBe([
        'category_id' => 9, 'cat_before' => '3|', 'cat_after' => '|3|9|', 'items_changed' => [$a, $b],
    ]);
});

it('normalizes no row it does not change — Proof 6/7 for the bulk path', function () {
    $series = H::series(['cat' => '3|']);
    $holder = H::khotabItem(['cat' => '9|3|', 'ser_id' => $series]);   // legacy, already holds 9
    $target = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    foreach (range(1, 10) as $_) {
        H::khotabItem(['cat' => '3|9|']);                              // unrelated legacy rows
    }
    $before = H::snapshot();

    bulk('series', $series, 'add-all', 9);

    $after = H::snapshot();
    $changed = collect($after['khotab'])->filter(fn ($v, $k) => $before['khotab'][$k] !== $v)->keys()->all();
    expect($changed)->toBe([$target])
        ->and($after['khotab'][$holder])->toBe('9|3|')
        ->and($after['series'][$series])->toBe('|3|9|');
});

it('rolls the whole bulk operation back if any step fails', function () {
    $series = H::series(['cat' => '3|']);
    $a = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    $b = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    // Fail on the LAST step (the series' own write), after both items, the
    // counter and two audit rows have already been written.
    DB::connection('main')->unprepared("CREATE TRIGGER fail_series BEFORE UPDATE ON nuke_islamic_series BEGIN SELECT RAISE(ABORT, 'boom'); END");
    $before = H::snapshot();

    $this->withoutExceptionHandling();
    expect(fn () => bulk('series', $series, 'add-all', 9))->toThrow(Exception::class, 'boom');

    expect(H::snapshot())->toBe($before)
        ->and(H::cat('nuke_islamic_khotab', $a))->toBe('3|')
        ->and(H::cat('nuke_islamic_khotab', $b))->toBe('3|')
        ->and(H::auditRows())->toBe([]);
});

it('renders the container page with per-category bulk buttons and the scope note', function () {
    $group = H::group(['title' => 'مجموعة الفقه', 'cat' => '3|']);

    $this->get(route('admin.categories.khotab.group', $group))->assertOk()
        ->assertSee('مجموعة الفقه')->assertSee('تصنيف 3')
        ->assertSee('ليست ضمن سلسلة')
        ->assertSee(route('admin.categories.khotab.group.add-all', $group), false)
        ->assertSee(route('admin.categories.khotab.group.remove-all', $group), false);
});

it('returns 404 for a missing series or group', function () {
    bulk('series', 4040, 'add-all', 9)->assertNotFound();
    bulk('group', 4040, 'remove-all', 9)->assertNotFound();
});
