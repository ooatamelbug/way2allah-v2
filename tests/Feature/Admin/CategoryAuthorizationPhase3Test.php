<?php

use App\Domain\Admin\Actions\Categories\AnasheedItemCategoryAction;
use App\Domain\Admin\Actions\Categories\KhotabContainerCategoryAction;
use App\Domain\Admin\Actions\Categories\KhotabItemCategoryAction;
use App\Domain\Admin\Support\PermissionLabels;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\KhotabItem;
use App\Support\Permission\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CategoryAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 3 — authorization. Laravel/Spatie is the sole
 * authority: `khotab.addcat` gates every Khotab route (add and remove),
 * `anasheed.addcat` every Anasheed route, checked by route middleware AND
 * re-checked by each action through AdminRecordAuthorizer.
 */
beforeEach(function () {
    H::setUp();
    H::categories(9);
    $this->khotab = H::khotabItem(['cat' => '3|']);
    $this->nasheed = H::anasheedItem(['cat_id' => '3|']);
    $this->series = H::series(['cat' => '3|']);
    $this->group = H::group(['cat' => '3|']);
});

/** @return array<string, list<array{0: string, 1: string, 2: string}>> */
function categoryRoutes(): array
{
    return [
        'khotab' => [
            ['get', 'admin.categories.khotab.index', ''],
            ['get', 'admin.categories.khotab.item', 'khotab'],
            ['post', 'admin.categories.khotab.item.add', 'khotab'],
            ['post', 'admin.categories.khotab.item.remove', 'khotab'],
            ['get', 'admin.categories.khotab.series', 'series'],
            ['post', 'admin.categories.khotab.series.add-all', 'series'],
            ['post', 'admin.categories.khotab.series.remove-all', 'series'],
            ['get', 'admin.categories.khotab.group', 'group'],
            ['post', 'admin.categories.khotab.group.add-all', 'group'],
            ['post', 'admin.categories.khotab.group.remove-all', 'group'],
        ],
        'anasheed' => [
            ['get', 'admin.categories.anasheed.index', ''],
            ['get', 'admin.categories.anasheed.item', 'nasheed'],
            ['post', 'admin.categories.anasheed.item.add', 'nasheed'],
            ['post', 'admin.categories.anasheed.item.remove', 'nasheed'],
        ],
    ];
}

function hitCategoryRoute(array $route)
{
    [$method, $name, $param] = $route;
    $url = route($name, $param === '' ? [] : [test()->{$param}]);

    return $method === 'get' ? test()->get($url) : test()->post($url, ['category_id' => 9]);
}

it('covers all fourteen registered category routes', function () {
    $registered = collect(app('router')->getRoutes()->getRoutesByName())->keys()
        ->filter(fn ($n) => str_starts_with($n, 'admin.categories.'))->sort()->values()->all();
    $tested = collect(categoryRoutes())->flatten(1)->pluck(1)->sort()->values()->all();

    expect($tested)->toBe($registered)->and($registered)->toHaveCount(14);
});

it('forbids every category route to an admin without the permission', function () {
    $before = H::snapshot();
    $this->actingAs(H::admin(['khotab.addmirror', 'khotab.repair', 'anasheed.addmirror']), 'admin');

    foreach (collect(categoryRoutes())->flatten(1) as $route) {
        hitCategoryRoute($route)->assertForbidden();
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('khotab.addcat opens Khotab routes only', function () {
    $this->actingAs(H::admin(['khotab.addcat']), 'admin');

    foreach (categoryRoutes()['khotab'] as $route) {
        expect(hitCategoryRoute($route)->status())->not->toBe(403);
    }
    foreach (categoryRoutes()['anasheed'] as $route) {
        hitCategoryRoute($route)->assertForbidden();
    }
    expect(H::cat('nuke_anasheed_anasheed', $this->nasheed))->toBe('3|');
});

it('anasheed.addcat opens Anasheed routes only', function () {
    $this->actingAs(H::admin(['anasheed.addcat']), 'admin');

    foreach (categoryRoutes()['anasheed'] as $route) {
        expect(hitCategoryRoute($route)->status())->not->toBe(403);
    }
    foreach (categoryRoutes()['khotab'] as $route) {
        hitCategoryRoute($route)->assertForbidden();
    }
    expect(H::cat('nuke_islamic_khotab', $this->khotab))->toBe('3|');
});

it('lets a super-admin through without an explicit grant', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->post(route('admin.categories.khotab.item.add', $this->khotab), ['category_id' => 9])->assertSessionHas('success');
    $this->post(route('admin.categories.anasheed.item.add', $this->nasheed), ['category_id' => 9])->assertSessionHas('success');
});

it('re-checks the permission inside every action, independent of the route', function () {
    $outsider = H::admin(['khotab.addmirror']);
    $category = Category::query()->findOrFail(9);
    $before = H::snapshot();

    expect(fn () => app(KhotabItemCategoryAction::class)->add($outsider, KhotabItem::query()->findOrFail($this->khotab), $category))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AnasheedItemCategoryAction::class)->remove($outsider, AnasheedItem::query()->findOrFail($this->nasheed), $category))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(KhotabContainerCategoryAction::class)->addToAll($outsider, 'series', $this->series, $category))
        ->toThrow(AuthorizationException::class);

    // anasheed.addcat does not authorize a Khotab action, and vice versa.
    $anasheedOnly = H::admin(['anasheed.addcat'], 'cat-anasheed');
    expect(fn () => app(KhotabContainerCategoryAction::class)->removeFromAll($anasheedOnly, 'group', $this->group, $category))
        ->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('shows each sidebar entry only to holders of its permission', function () {
    $this->actingAs(H::admin(['khotab.addcat']), 'admin');
    $page = $this->get(route('admin.categories.khotab.index'))->assertOk();
    $page->assertSee(route('admin.categories.khotab.index'), false)
        ->assertDontSee(route('admin.categories.anasheed.index'), false);
});

it('mints exactly two category permissions, with labels, and grants them to no one', function () {
    $names = Permission::where('name', 'like', '%.addcat')->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['anasheed.addcat', 'khotab.addcat'])
        ->and(PermissionLabels::permissionLabel('khotab.addcat'))->toBe('اضافة تصنيف')
        ->and(PermissionLabels::permissionLabel('anasheed.addcat'))->toBe('اضافة تصنيف')
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});
