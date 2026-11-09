<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CategoryAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 3 — Anasheed item add / remove. Same list
 * semantics as Khotab; differs exactly where OLD did: `cat_id` column,
 * `anasheed_count`, no repair cascade.
 */
beforeEach(function () {
    H::setUp();
    H::categories(3, 5, 9, 17, 99);
    $this->actingAs(H::superAdmin(), 'admin');
});

function addNasheedCat(int $item, int $cat)
{
    return test()->post(route('admin.categories.anasheed.item.add', $item), ['category_id' => $cat]);
}

function removeNasheedCat(int $item, int $cat)
{
    return test()->post(route('admin.categories.anasheed.item.remove', $item), ['category_id' => $cat]);
}

it('parses both forms and writes canonical cat_id', function (string $raw) {
    $item = H::anasheedItem(['cat_id' => $raw]);

    addNasheedCat($item, 9)->assertRedirect(route('admin.categories.anasheed.item', $item));

    expect(H::cat('nuke_anasheed_anasheed', $item))->toBe('|3|17|5|9|');
})->with(['3|17|5|', '|3|17|5|']);

it('removes a first-position category from a no-leading-pipe row', function () {
    $item = H::anasheedItem(['cat_id' => '3|17|5|']);

    removeNasheedCat($item, 3);

    expect(H::cat('nuke_anasheed_anasheed', $item))->toBe('|17|5|');
});

it('moves anasheed_count only, floored at zero', function () {
    $item = H::anasheedItem(['cat_id' => '']);

    addNasheedCat($item, 9);
    expect(H::anasheedCount(9))->toBe(11)->and(H::videoCount(9))->toBe(10);

    DB::connection('main')->table('nuke_w2a_cat')->where('id', 9)->update(['anasheed_count' => 0]);
    removeNasheedCat($item, 9);
    expect(H::anasheedCount(9))->toBe(0)->and(H::cat('nuke_anasheed_anasheed', $item))->toBe('');
});

it('treats duplicate add and absent remove as no-ops that write nothing', function () {
    $item = H::anasheedItem(['cat_id' => '3|17|5|']);
    $before = H::snapshot();

    $writes = H::writesDuring(function () use ($item) {
        addNasheedCat($item, 17)->assertSessionHas('error');
        removeNasheedCat($item, 99)->assertSessionHas('error');
    });

    expect($writes)->toBe([])
        ->and(H::snapshot())->toBe($before)
        ->and(H::cat('nuke_anasheed_anasheed', $item))->toBe('3|17|5|');
});

it('runs no cascade and touches no other row', function () {
    $series = H::series(['cat' => '5|']);
    $khotab = H::khotabItem(['cat' => '5|', 'ser_id' => $series]);
    $other = H::anasheedItem(['cat_id' => '5|']);
    $item = H::anasheedItem(['cat_id' => '5|']);

    $writes = H::writesDuring(fn () => addNasheedCat($item, 9));

    $tables = collect($writes)->filter(fn ($w) => stripos($w['sql'], 'update') === 0)
        ->map(fn ($w) => preg_match('/update "([^"]+)"/', $w['sql'], $m) ? $m[1] : '?')->values()->all();
    expect($tables)->toBe(['nuke_anasheed_anasheed', 'nuke_w2a_cat'])
        ->and(H::cat('nuke_anasheed_anasheed', $other))->toBe('5|')
        ->and(H::cat('nuke_islamic_khotab', $khotab))->toBe('5|')
        ->and(H::cat('nuke_islamic_series', $series))->toBe('5|');
});

it('audits under module anasheed', function () {
    $item = H::anasheedItem(['cat_id' => '3|']);

    addNasheedCat($item, 9);

    $row = H::auditRows()[0];
    expect($row->action)->toBe('add_cat_item')->and($row->module)->toBe('anasheed')
        ->and($row->entity_type)->toBe('item')->and((int) $row->entity_id)->toBe($item)
        ->and(json_decode($row->metadata, true))->toBe(['category_id' => 9, 'cat_before' => '3|', 'cat_after' => '|3|9|']);
});

it('renders the item page and looks items up', function () {
    $item = H::anasheedItem(['title' => 'نشيد الصباح', 'cat_id' => '|5|']);

    $this->get(route('admin.categories.anasheed.item', $item))->assertOk()->assertSee('نشيد الصباح')->assertSee('تصنيف 5');
    $this->get(route('admin.categories.anasheed.index', ['item' => $item]))->assertRedirect(route('admin.categories.anasheed.item', $item));
    $this->get(route('admin.categories.anasheed.index'))->assertOk();
});
