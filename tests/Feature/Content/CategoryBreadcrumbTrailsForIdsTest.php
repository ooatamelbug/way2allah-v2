<?php

use App\Domain\Content\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * O-3 — proves `Category::breadcrumbTrailsForIds()` is an exact,
 * occurrence-for-occurrence replacement for the per-id
 * `Category::find()` + `breadcrumbTrail()` loop it replaced in
 * `CategorySeriesController`, and that its query count scales with tree
 * DEPTH rather than with the number of ids.
 *
 * Several tests compare against `oldSeriesTrails()` below — a verbatim copy
 * of the pre-change algorithm, kept here as a parity oracle so the
 * assertions test equivalence rather than a restatement of the new code.
 */
function useInMemoryMainConnectionForBreadcrumbTrails(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
    ]);
}

/**
 * The exact pre-O-3 algorithm from CategorySeriesController:73-77.
 * Used only as a parity oracle. Never run it against cyclic data — it
 * inherits `breadcrumbTrail()`'s unbounded `while` loop.
 *
 * @return Collection<int, Collection<int, Category>>
 */
function oldSeriesTrails(string $cat): Collection
{
    return collect(explode('|', $cat))
        ->filter(fn ($id) => $id !== '')
        ->map(fn ($id) => Category::find((int) $id))
        ->filter()
        ->map(fn (Category $seriesCategory) => $seriesCategory->breadcrumbTrail())
        ->values();
}

/**
 * Collapses trails to plain id arrays so parity can be asserted on
 * structure and order rather than on model identity.
 *
 * @return list<list<int>>
 */
function trailIds(Collection $trails): array
{
    return $trails
        ->map(fn ($trail) => $trail->map(fn (Category $c) => (int) $c->id)->all())
        ->values()
        ->all();
}

function seedCategory(int $id, int $parent, string $title = 'Cat'): void
{
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        'id' => $id, 'title' => $title.' '.$id, 'main_cat' => $parent,
    ]);
}

/** root(1) <- mid(2) <- leafA(3), leafB(4); plus an unrelated root(9). */
function seedStandardTree(): void
{
    seedCategory(1, 0, 'Root');
    seedCategory(2, 1, 'Mid');
    seedCategory(3, 2, 'LeafA');
    seedCategory(4, 2, 'LeafB');
    seedCategory(9, 0, 'OtherRoot');
}

beforeEach(function () {
    useInMemoryMainConnectionForBreadcrumbTrails();
});

// ---------------------------------------------------------------- parity

it('matches the old per-id algorithm exactly for a multi-id list', function () {
    seedStandardTree();

    $cat = '3|4|9';

    expect(trailIds(Category::breadcrumbTrailsForIds(
        collect(explode('|', $cat))->filter(fn ($id) => $id !== '')
    )))->toBe(trailIds(oldSeriesTrails($cat)));
});

it('keeps duplicate ids as duplicate trails', function () {
    seedStandardTree();

    $cat = '3|3|4';

    $new = trailIds(Category::breadcrumbTrailsForIds(
        collect(explode('|', $cat))->filter(fn ($id) => $id !== '')
    ));

    expect($new)->toBe(trailIds(oldSeriesTrails($cat)))
        ->and($new)->toHaveCount(3)
        ->and($new[0])->toBe([1, 2, 3])
        ->and($new[1])->toBe([1, 2, 3])
        ->and($new[2])->toBe([1, 2, 4]);
});

it('preserves the pipe-string order even when database order differs', function () {
    // Inserted ascending; requested descending. A whereIn would return them
    // in database order, so this fails if the output is not re-ordered.
    seedStandardTree();

    $new = trailIds(Category::breadcrumbTrailsForIds(collect(['9', '4', '3'])));

    expect($new)->toBe([[9], [1, 2, 4], [1, 2, 3]])
        ->and($new)->toBe(trailIds(oldSeriesTrails('9|4|3')));
});

it('silently drops ids with no category row', function () {
    seedStandardTree();

    $cat = '3|77777|9';

    $new = trailIds(Category::breadcrumbTrailsForIds(
        collect(explode('|', $cat))->filter(fn ($id) => $id !== '')
    ));

    expect($new)->toBe(trailIds(oldSeriesTrails($cat)))
        ->and($new)->toHaveCount(2)
        ->and($new)->toBe([[1, 2, 3], [9]]);
});

it('behaves identically for empty and malformed pipe strings', function () {
    seedStandardTree();

    foreach (['', '|', '||', '|3', '3|', '|3|'] as $cat) {
        $ids = collect(explode('|', $cat))->filter(fn ($id) => $id !== '');

        expect(trailIds(Category::breadcrumbTrailsForIds($ids)))
            ->toBe(trailIds(oldSeriesTrails($cat)), "cat='{$cat}'");
    }
});

it('returns an empty collection for an empty id list', function () {
    seedStandardTree();

    expect(Category::breadcrumbTrailsForIds([])->all())->toBe([])
        ->and(Category::breadcrumbTrailsForIds(collect())->all())->toBe([]);
});

// ---------------------------------------------------------------- depth

it('builds ancestors-first trails including the leaf at depths 0, 1 and 3', function () {
    seedCategory(1, 0, 'Root');     // depth 0
    seedCategory(2, 1, 'L1');       // depth 1
    seedCategory(3, 2, 'L2');
    seedCategory(4, 3, 'L3');       // depth 3

    $new = trailIds(Category::breadcrumbTrailsForIds(collect(['1', '2', '4'])));

    expect($new)->toBe([
        [1],              // root only — leaf included, no ancestors
        [1, 2],           // ancestors-first
        [1, 2, 3, 4],     // full chain, leaf last
    ])->and($new)->toBe(trailIds(oldSeriesTrails('1|2|4')));
});

it('ends a trail where a parent row is missing, as the old walk did', function () {
    // 5's parent 6 does not exist — breadcrumbTrail() breaks there.
    seedCategory(5, 6, 'Orphan');

    $new = trailIds(Category::breadcrumbTrailsForIds(collect(['5'])));

    expect($new)->toBe([[5]])
        ->and($new)->toBe(trailIds(oldSeriesTrails('5')));
});

// ------------------------------------------------------------ query count

it('scales query count with tree depth, not with the number of ids', function () {
    // root(1000) <- mid(1001) <- 50 leaves. Depth is identical for every
    // leaf, so the resolver should cost the same for 5 ids and for 50.
    seedCategory(1000, 0, 'Root');
    seedCategory(1001, 1000, 'Mid');

    for ($id = 1; $id <= 50; $id++) {
        seedCategory($id, 1001, 'Leaf');
    }

    $measure = function (array $ids): int {
        $count = 0;
        $listener = function () use (&$count) {
            $count++;
        };

        DB::connection('main')->listen($listener);
        Category::breadcrumbTrailsForIds(collect($ids));
        // Pest boots a fresh app per test, so the listener dies with it; the
        // count is captured before any other assertion runs a query.

        return $count;
    };

    $five = $measure(range(1, 5));
    $fifty = $measure(range(1, 50));

    // 3 levels: leaves, mid, root.
    expect($five)->toBe(3)
        ->and($fifty)->toBe(3)
        ->and($fifty)->toBe($five);

    expect($fifty)->toBe($five);
});

it('measures the old per-id algorithm scaling linearly, for contrast', function () {
    seedCategory(1000, 0, 'Root');
    seedCategory(1001, 1000, 'Mid');

    for ($id = 1; $id <= 50; $id++) {
        seedCategory($id, 1001, 'Leaf');
    }

    $measureOld = function (string $cat): int {
        $count = 0;
        DB::connection('main')->listen(function () use (&$count) {
            $count++;
        });
        oldSeriesTrails($cat);

        return $count;
    };

    // 3 queries per id: find(leaf), find(mid), find(root).
    $oldFive = $measureOld(implode('|', range(1, 5)));
    $oldFifty = $measureOld(implode('|', range(1, 50)));

    expect($oldFive)->toBe(15)
        ->and($oldFifty)->toBe(150)
        // Linear in N — the defect O-3 removes.
        ->and($oldFifty)->toBe($oldFive * 10);
});

it('produces the same trails as the old algorithm at N=50', function () {
    seedCategory(1000, 0, 'Root');
    seedCategory(1001, 1000, 'Mid');

    for ($id = 1; $id <= 50; $id++) {
        seedCategory($id, 1001, 'Leaf');
    }

    $cat = implode('|', range(1, 50));

    expect(trailIds(Category::breadcrumbTrailsForIds(
        collect(explode('|', $cat))->filter(fn ($id) => $id !== '')
    )))->toBe(trailIds(oldSeriesTrails($cat)));
});

// --------------------------------------------------------- cycle protection

it('terminates on a main_cat cycle instead of looping forever', function () {
    // 20 -> 21 -> 20. The old breadcrumbTrail() would spin here, so the
    // oracle is deliberately NOT run against this fixture.
    seedCategory(20, 21, 'CycleA');
    seedCategory(21, 20, 'CycleB');

    $new = trailIds(Category::breadcrumbTrailsForIds(collect(['20'])));

    expect($new)->toHaveCount(1)
        // Walk stops the moment an id repeats, so the trail is finite and
        // contains each node at most once.
        ->and(count($new[0]))->toBeLessThanOrEqual(2)
        ->and(array_unique($new[0]))->toHaveCount(count($new[0]));
});

it('terminates on a self-referencing category', function () {
    seedCategory(30, 30, 'SelfParent');

    expect(trailIds(Category::breadcrumbTrailsForIds(collect(['30']))))->toBe([[30]]);
});

// ------------------------------------------- existing method left untouched

it('leaves Category::breadcrumbTrail() behaving exactly as before', function () {
    seedStandardTree();

    $leaf = Category::find(3);

    expect($leaf->breadcrumbTrail()->map(fn (Category $c) => (int) $c->id)->all())
        ->toBe([1, 2, 3]);

    expect(Category::find(1)->breadcrumbTrail()->map(fn (Category $c) => (int) $c->id)->all())
        ->toBe([1]);

    // Missing parent still breaks the walk rather than erroring.
    seedCategory(5, 6, 'Orphan');
    expect(Category::find(5)->breadcrumbTrail()->map(fn (Category $c) => (int) $c->id)->all())
        ->toBe([5]);
});
