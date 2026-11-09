<?php

use App\Domain\Admin\Actions\Advfiles\ApproveAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\RejectAdvfileRequestAction;
use App\Domain\Admin\Actions\Advfiles\UnpublishAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\UpdateAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\UpdateAdvfileCreditsAction;
use App\Domain\Admin\Support\PermissionLabels;
use App\Domain\Content\Models\KhotabItem;
use App\Support\Permission\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdvfileAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 5 — authorization. Requesting: any
 * authenticated admin (OLD: any admin). Managing: khotab.addadv (the real
 * admincp/khotab/menu.php key), enforced by route and re-checked in each
 * action.
 */
beforeEach(function () {
    H::setUp();
    $this->none = H::item(0)['item'];
    $this->pending = H::item(-1)['item'];
    $this->published = H::item(1)['item'];
    H::file($this->published, ['credit' => 5]);
});

/** @return list<array{0: string, 1: string, 2: string}> */
function advManageRoutes(): array
{
    return [
        ['get', 'admin.advfiles.queue', ''],
        ['get', 'admin.advfiles.credits', ''],
        ['post', 'admin.advfiles.credits.save', ''],
        ['get', 'admin.advfiles.approve.form', 'pending'],
        ['post', 'admin.advfiles.approve', 'pending'],
        ['post', 'admin.advfiles.reject', 'pending'],
        ['get', 'admin.advfiles.edit', 'published'],
        ['put', 'admin.advfiles.update', 'published'],
        ['delete', 'admin.advfiles.unpublish', 'published'],
    ];
}

/** @return list<array{0: string, 1: string, 2: string}> */
function advAnyAdminRoutes(): array
{
    return [
        ['get', 'admin.advfiles.lookup', ''],
        ['get', 'admin.advfiles.item', 'none'],
        ['post', 'admin.advfiles.request', 'none'],
    ];
}

function hitAdvRoute(array $route)
{
    [$method, $name, $param] = $route;
    $url = route($name, $param === '' ? [] : [test()->{$param}]);

    return test()->{$method}($url, ['credit' => [test()->published => 1], 'code' => 'x']);
}

it('covers all twelve registered advfiles routes', function () {
    $registered = collect(app('router')->getRoutes()->getRoutesByName())->keys()
        ->filter(fn ($n) => str_starts_with($n, 'admin.advfiles.'))->sort()->values()->all();

    expect(collect([...advManageRoutes(), ...advAnyAdminRoutes()])->pluck(1)->sort()->values()->all())->toBe($registered)
        ->and($registered)->toHaveCount(12);
});

it('refuses every route to a guest', function () {
    foreach ([...advManageRoutes(), ...advAnyAdminRoutes()] as $route) {
        expect(hitAdvRoute($route)->status())->toBeIn([302, 401]);
    }
    expect(H::state($this->none))->toBe(0)->and(H::state($this->pending))->toBe(-1);
});

it('lets any admin request, and forbids every management route without khotab.addadv', function () {
    $before = H::snapshot();
    $this->actingAs(H::admin(['khotab.addcat', 'khotab.repair', 'polls.managepolls']), 'admin');

    foreach (advManageRoutes() as $route) {
        hitAdvRoute($route)->assertForbidden();
    }
    expect(H::snapshot())->toBe($before);

    $this->get(route('admin.advfiles.item', $this->none))->assertOk()->assertDontSee(route('admin.advfiles.approve.form', $this->pending), false);
    $this->post(route('admin.advfiles.request', $this->none))->assertSessionHas('success');
    expect(H::state($this->none))->toBe(-1);
});

it('khotab.addadv opens every management route', function () {
    $this->actingAs(H::admin(['khotab.addadv']), 'admin');

    foreach ([...advAnyAdminRoutes(), ...advManageRoutes()] as $route) {
        expect(hitAdvRoute($route)->status())->not->toBe(403);
    }
    expect(H::state($this->published))->toBe(0);   // the last route removed it
});

it('lets a super-admin through without an explicit grant', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->post(route('admin.advfiles.approve', $this->pending), ['credit' => 1])->assertSessionHas('success');
    expect(H::state($this->pending))->toBe(1);
});

it('re-checks khotab.addadv inside every management action', function () {
    $outsider = H::admin(['khotab.addcat']);
    $pending = KhotabItem::query()->findOrFail($this->pending);
    $published = KhotabItem::query()->findOrFail($this->published);
    $payload = ['credit' => 1, 'code' => 'x', 'audio_url' => ''];
    $before = H::snapshot();

    expect(fn () => app(ApproveAdvfileAction::class)->execute($outsider, $pending, $payload))->toThrow(AuthorizationException::class);
    expect(fn () => app(RejectAdvfileRequestAction::class)->execute($outsider, $pending))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateAdvfileAction::class)->execute($outsider, $published, $payload))->toThrow(AuthorizationException::class);
    expect(fn () => app(UnpublishAdvfileAction::class)->execute($outsider, $published))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateAdvfileCreditsAction::class)->execute($outsider, [$this->published => 9]))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('shows the sidebar entry to khotab.addadv holders only', function () {
    // Exact href: the queue URL is a prefix of the lookup page's own URLs.
    $link = 'href="'.route('admin.advfiles.queue').'"';
    $this->actingAs(H::admin(['khotab.addadv']), 'admin');
    $this->get(route('admin.advfiles.lookup'))->assertOk()->assertSee($link, false);

    $this->actingAs(H::admin(['khotab.addcat'], 'adv-other'), 'admin');
    $this->get(route('admin.advfiles.lookup'))->assertOk()->assertDontSee($link, false);
});

it('defines khotab.addadv, labelled from the legacy menu, granted to no one', function () {
    expect(Permission::where('name', 'khotab.addadv')->exists())->toBeTrue()
        ->and(PermissionLabels::permissionLabel('khotab.addadv'))->toBe('إضافة ملفات دعائية')
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});
