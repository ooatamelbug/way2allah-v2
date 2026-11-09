<?php

/*
 * OLD Admin Migration, contextual surface C0 — the shared foundation:
 * route-derived authorization (must equal the real middleware for every
 * admin route), the same-site return_to validator, and the guarantee that
 * visitors get nothing.
 */

use App\Domain\Admin\Contextual\AdminRouteAccess;
use App\Domain\Admin\Contextual\ContextualAdmin;
use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Middleware\EnsureAdminHasPermission;
use App\Domain\Admin\Http\Middleware\EnsureAdminHasRole;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(fn () => H::setUp());

/** Runs only a route's admin.* middleware, as the router would, for one admin. */
function realMiddlewareAllows(Illuminate\Routing\Route $route, AdminUser $admin): bool
{
    Auth::guard('admin')->setUser($admin);
    $request = Request::create('/'.$route->uri());

    foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
        if (! is_string($middleware)) {
            continue;
        }
        [$class, $arguments] = array_pad(explode(':', $middleware, 2), 2, '');
        if (! in_array($class, [EnsureAdminHasRole::class, EnsureAdminHasPermission::class], true)) {
            continue;
        }
        try {
            app($class)->handle($request, fn () => response('ok'), ...array_values(array_filter(explode(',', $arguments), fn ($v) => $v !== '')));
        } catch (HttpException) {
            return false;
        }
    }

    return true;
}

it('decides exactly as the real admin middleware for every admin route and several admins', function () {
    $admins = [
        'plain' => H::admin([], 'ctx-plain'),
        'mirrors' => H::admin(['khotab.addmirror'], 'ctx-mirrors'),
        'cats' => H::admin(['khotab.addcat', 'anasheed.addcat'], 'ctx-cats'),
        'mixed' => H::admin(['polls.managepolls', 'w2acd.managecds', 'mobile.managedownloads', 'telawah.repair', 'khotab.repair'], 'ctx-mixed'),
        'boss' => H::superAdmin(),
    ];
    $access = app(AdminRouteAccess::class);
    $checked = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();
        $admin = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && preg_match('/^admin\.(role|permission)/', $m));
        if ($name === null || $admin === null) {
            continue;
        }
        foreach ($admins as $label => $user) {
            expect($access->allows($user, $name))->toBe(realMiddlewareAllows($route, $user), "{$name} for {$label}");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(400);
});

it('fails loudly for an unknown route or a route without admin authorization', function () {
    $access = app(AdminRouteAccess::class);

    expect(fn () => $access->requirementsFor('admin.no-such-route'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $access->requirementsFor('home'))->toThrow(InvalidArgumentException::class);
});

it('accepts only same-site public return targets', function (mixed $value, ?string $expected) {
    $request = Request::create('http://localhost/admincp/polls', 'POST');

    expect(ContextualReturn::safeTarget($value, $request))->toBe($expected);
})->with([
    'relative page' => ['/khotab-item-5.htm', '/khotab-item-5.htm'],
    'relative with query' => ['/mobile-search-x.htm?page=2', '/mobile-search-x.htm?page=2'],
    'fragment dropped' => ['/surveys.htm#top', '/surveys.htm'],
    'absolute same host' => ['http://localhost/cds-main.htm', '/cds-main.htm'],
    'absolute same host, case' => ['HTTP://LOCALHOST/cds-main.htm', '/cds-main.htm'],
    'protocol-relative' => ['//evil.example/x', null],
    'external absolute' => ['https://evil.example/x', null],
    'same host other port' => ['http://localhost:8080/x', null],
    'backslash trick' => ['/\\evil.example', null],
    'scheme-less host' => ['evil.example/x', null],
    'javascript' => ['javascript:alert(1)', null],
    'data uri' => ['data:text/html,x', null],
    'header injection' => ["/x\r\nLocation: https://evil.example", null],
    'whitespace' => ['/a b', null],
    'user info' => ['http://user@localhost/x', null],
    'admin path' => ['/admincp/polls', null],
    'admin path, case' => ['/AdminCP', null],
    'deploy path' => ['/deploy/install', null],
    'empty' => ['', null],
    'array' => [['/x'], null],
    'too long' => ['/'.str_repeat('a', 2050), null],
]);

it('renders nothing for a visitor and builds no control', function () {
    $html = Blade::render('@contextualAdmin<x-admin.contextual-controls :controls="$c" />@endcontextualAdmin', [
        'c' => [ContextualControl::link('x', 'fa-x', 'admin.polls.create')],
    ]);

    expect(trim($html))->toBe('')
        ->and(app(ContextualAdmin::class)->active())->toBeFalse()
        ->and(app(ContextualAdmin::class)->visible([ContextualControl::link('x', 'fa-x', 'admin.polls.create')]))->toBe([]);
});

it('renders a GET control as a link with return_to and a mutation as a CSRF form with method spoofing', function () {
    Auth::guard('admin')->setUser(H::superAdmin());
    $this->app->instance('request', Request::create('http://localhost/khotab-item-7.htm'));

    $html = Blade::render('<x-admin.contextual-controls :controls="$c" />', ['c' => [
        ContextualControl::link('تعديل', 'fa-pencil', 'admin.mirrors.khotab.edit', ['mirror' => 3]),
        ContextualControl::action('حذف', 'fa-trash', 'admin.mirrors.khotab.destroy', ['mirror' => 3], 'DELETE', [], 'متأكد؟'),
    ]]);

    expect($html)->toContain('href="'.route('admin.mirrors.khotab.edit', 3).'?return_to=%2Fkhotab-item-7.htm"')
        ->toContain('action="'.route('admin.mirrors.khotab.destroy', 3).'"')
        ->toContain('name="_token"')
        ->toContain('name="_method" value="DELETE"')
        ->toContain('name="return_to" value="/khotab-item-7.htm"')
        ->toContain('return confirm(')
        ->not->toContain('method="get"');
});

it('omits return_to for plain navigation controls', function () {
    Auth::guard('admin')->setUser(H::superAdmin());
    $this->app->instance('request', Request::create('http://localhost/recite-item-7.htm'));

    $html = Blade::render('<x-admin.contextual-controls :controls="$c" />', ['c' => [
        ContextualControl::link('حالة', 'fa-heartbeat', 'admin.link-quality.telawah.item', ['telawahItem' => 7], returns: false),
    ]]);

    expect($html)->toContain('href="'.route('admin.link-quality.telawah.item', 7).'"')->not->toContain('return_to');
});

it('keeps contextual pages out of shared caches, and leaves visitor responses untouched', function () {
    H::insert('nuke_telawah_telawah', ['id' => 7, 'title' => 'تلاوة', 'link' => 'http://x/a.mp3', 'group_id' => 0]);

    $visitor = $this->get('/recite-item-7.htm')->assertOk();
    expect($visitor->headers->get('Cache-Control'))->not->toContain('no-store');

    $admin = $this->withSession(H::session(H::admin(['telawah.repair'])))->get('/recite-item-7.htm')->assertOk();
    expect($admin->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('never points a contextual control at OLD', function () {
    $files = array_merge(
        glob(app_path('Domain/Admin/Contextual').'/*.php'),
        glob(app_path('Domain/Admin/Contextual/Controls').'/*.php'),
        glob(resource_path('views/components/admin').'/contextual-*.blade.php'),
        glob(resource_path('views/admin/contextual').'/*.blade.php'),
    );

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        expect($source)->not->toContain('old.way2allah.com')
            ->not->toContain('admin.php?op=')
            ->not->toContain('inlineAdminUrl')
            ->not->toContain('->permissions')
            ->not->toContain('radminsuper');
    }
});
