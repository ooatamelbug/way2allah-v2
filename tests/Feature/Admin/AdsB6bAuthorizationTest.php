<?php

/*
 * F01 / B6b — authorization, isolation from B6a, HTTP methods / CSRF, and no public side effects.
 */

use App\Domain\Admin\Actions\Ads\DeleteBadAdReportAction;
use App\Domain\Admin\Actions\Ads\UpdateBannerAdsAction;
use App\Domain\Admin\Support\AdminDashboardModules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BannerAdsHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->item = H::item('بنر', 1);
    $this->other = H::item('بنر آخر', 1);
    H::member($this->item, 968);
    $this->report = H::report('x@example.org', 'https://example.org/');
});

function b6bBanners(): array
{
    return [['get', 'admin.banners_ads.index', []], ['put', 'admin.banners_ads.update', []]];
}

function b6bReports(): array
{
    return [['get', 'admin.report_ads.index', []], ['delete', 'admin.report_ads.destroy', ['report']]];
}

function b6bHit(array $route)
{
    [$method, $name, $params] = $route;

    return test()->{$method}(route($name, array_map(fn ($p) => test()->{$p}, $params)), ['keep' => [], 'add' => [test()->other], 'set_version' => H::version()]);
}

function b6bState(): array
{
    return [H::set(), H::reports(), H::auditRows()];
}

it('registers exactly four B6b routes under /admincp, each behind its own key', function () {
    $routes = collect(app('router')->getRoutes()->getRoutesByName())
        ->filter(fn ($r, $n) => str_starts_with($n, 'admin.banners_ads.') || str_starts_with($n, 'admin.report_ads.'));

    expect($routes->keys()->sort()->values()->all())->toBe(['admin.banners_ads.index', 'admin.banners_ads.update', 'admin.report_ads.destroy', 'admin.report_ads.index']);
    foreach ($routes as $name => $route) {
        $key = str_starts_with($name, 'admin.banners_ads.') ? 'banners_ads.managebanners' : 'report_ads.managereports';
        expect($route->uri())->toStartWith('admincp/')
            ->and($route->gatherMiddleware())->toContain('web')->toContain('admin.role')->toContain('admin.permission:'.$key)
            ->not->toContain('admin.permission:ads.manageads');
    }
});

it('a visitor cannot reach any B6b page or action', function () {
    $before = b6bState();
    foreach ([...b6bBanners(), ...b6bReports()] as $route) {
        expect(b6bHit($route)->status())->toBeIn([302, 401]);
    }
    expect(b6bState())->toBe($before);
});

it('ads.manageads (B6a) authorizes neither B6b module', function () {
    $this->actingAs(H::admin(['ads.manageads']), 'admin');
    $before = b6bState();

    foreach ([...b6bBanners(), ...b6bReports()] as $route) {
        b6bHit($route)->assertForbidden();
    }

    expect(b6bState())->toBe($before);
});

it('each B6b key opens only its own module, and neither opens B6a', function () {
    $this->actingAs(H::admin(['banners_ads.managebanners'], 'banners'), 'admin');
    foreach (b6bReports() as $route) {
        b6bHit($route)->assertForbidden();
    }
    $this->get(route('admin.banners_ads.index'))->assertOk();
    $this->get(route('admin.ads.index'))->assertForbidden();

    $this->actingAs(H::admin(['report_ads.managereports'], 'reports'), 'admin');
    foreach (b6bBanners() as $route) {
        b6bHit($route)->assertForbidden();
    }
    $this->get(route('admin.report_ads.index'))->assertOk();
    $this->get(route('admin.ads.index'))->assertForbidden();
});

it('a super-admin passes both modules without explicit grants', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.banners_ads.index'))->assertOk();
    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->item], 'add' => [$this->other], 'set_version' => H::version()])->assertSessionHas('success');
    $this->get(route('admin.report_ads.index'))->assertOk();
    $this->delete(route('admin.report_ads.destroy', $this->report))->assertSessionHas('success');
});

it('the actions re-check authorization themselves', function () {
    $outsider = H::admin(['ads.manageads']);
    $before = b6bState();

    expect(fn () => app(UpdateBannerAdsAction::class)->execute($outsider, [], [$this->other], H::version()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteBadAdReportAction::class)->execute($outsider, $this->report))->toThrow(AuthorizationException::class);

    expect(b6bState())->toBe($before);
});

it('the menu shows each B6b entry only to its key holders (and super-admins)', function () {
    $routes = fn ($admin) => collect(app(AdminDashboardModules::class)->visibleFor($admin))
        ->flatMap(fn ($m) => $m['children'] ?? [$m])->pluck('route')->all();

    expect($routes(H::admin(['ads.manageads'], 'ads')))->not->toContain('admin.banners_ads.index')->not->toContain('admin.report_ads.index')
        ->and($routes(H::admin(['banners_ads.managebanners'], 'bn')))->toContain('admin.banners_ads.index')->not->toContain('admin.report_ads.index')
        ->and($routes(H::admin(['report_ads.managereports'], 'rp')))->toContain('admin.report_ads.index')->not->toContain('admin.banners_ads.index')
        ->and($routes(H::superAdmin()))->toContain('admin.banners_ads.index')->toContain('admin.report_ads.index');
});

it('state changes need the right verb; GET never changes anything; forms carry CSRF tokens', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $before = b6bState();

    $this->post('/admincp/banners-ads', ['add' => [$this->other], 'set_version' => H::version()])->assertStatus(405);
    $this->get('/admincp/banners-ads?add[]='.$this->other.'&keep[]=')->assertOk();
    $this->get('/admincp/report-ads/'.$this->report)->assertStatus(405);

    expect(b6bState())->toBe($before);
    expect($this->get(route('admin.banners_ads.index'))->getContent())->toContain('name="_token"')->toContain('name="_method" value="PUT"')
        ->and($this->get(route('admin.report_ads.index'))->getContent())->toContain('name="_token"')->toContain('name="_method" value="DELETE"');
});

it('CSRF is enforced on both B6b state changes (token missing → rejected; valid token → passes)', function (string $verb, string $route) {
    expect(app('router')->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class);

    // Laravel skips CSRF while running tests; exercise the web group's middleware directly with that bypass off.
    $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
    $url = $route === 'report' ? route('admin.report_ads.destroy', $this->report) : route('admin.banners_ads.update');
    $session = app('session.store');
    $session->start();

    $forged = Request::create($url, 'POST', ['_method' => $verb, 'add' => [$this->other]]);
    $forged->headers->set('Sec-Fetch-Site', 'cross-site');
    $forged->setLaravelSession($session);
    expect(fn () => $middleware->handle($forged, fn () => response('passed')))->toThrow(TokenMismatchException::class);

    $genuine = Request::create($url, 'POST', ['_method' => $verb, '_token' => $session->token()]);
    $genuine->headers->set('Sec-Fetch-Site', 'cross-site');
    $genuine->setLaravelSession($session);
    expect($middleware->handle($genuine, fn () => response('passed'))->getContent())->toBe('passed');
})->with(['DELETE report' => ['DELETE', 'report'], 'PUT banner set' => ['PUT', 'banners']]);

it('B6b leaves B6a\'s nuke_ads untouched', function () {
    DB::connection('main')->table('nuke_ads')->insert(['name' => 'B6a ad', 'type' => 1, 'position' => 4, 'show' => 1]);
    $before = DB::connection('main')->table('nuke_ads')->get()->map(fn ($r) => (array) $r)->all();
    $this->actingAs(H::superAdmin(), 'admin');

    $this->put(route('admin.banners_ads.update'), ['keep' => [], 'add' => [$this->other], 'set_version' => H::version()])->assertSessionHas('success');
    $this->delete(route('admin.report_ads.destroy', $this->report))->assertSessionHas('success');

    expect(DB::connection('main')->table('nuke_ads')->get()->map(fn ($r) => (array) $r)->all())->toBe($before);
});

it('no public code reads, writes or renders the B6b tables, and no report-submission endpoint exists', function () {
    foreach (app('router')->getRoutes() as $route) {
        $uri = $route->uri();
        if (str_contains($uri, 'banners-ads') || str_contains($uri, 'report-ads')) {
            expect($uri)->toStartWith('admincp/');
        }
        expect($uri)->not->toContain('report_for_bad_ads')->not->toContain('bad-ad')->not->toContain('report-ad/');
    }

    $offenders = [];
    foreach (['app', 'resources/views'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            // B6c-1: the switch-gated, read-only strip foundation (app/Domain/Content/Ads/) may read nuke_banners_ads.
            if (! str_ends_with($path, '.php') || str_starts_with($path, 'app/Domain/Admin/') || str_starts_with($path, 'app/Domain/Content/Ads/')
                || str_starts_with($path, 'resources/views/admin/banners-ads/') || str_starts_with($path, 'resources/views/admin/report-ads/')) {
                continue;
            }
            if (preg_match('/nuke_banners_ads|report_for_bad_ads|admin\.banners_ads\.|admin\.report_ads\./', file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $path;
            }
        }
    }
    expect($offenders)->toBe([]);
});
