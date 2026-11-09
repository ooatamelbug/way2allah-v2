<?php

/*
 * R2 architecture fix — `App\Support\Http\AdminViewer` is how Content (and
 * download telemetry) asks "is an administrator viewing?" without depending
 * on the Admin domain. It must be the very ContextualAdmin instance of the
 * request: a Content controller's markRendered() has to reach the state that
 * KeepContextualPagesPrivate reads, or a page showing hidden content to an
 * admin would lose `no-store, private`.
 */

use App\Domain\Admin\Contextual\ContextualAdmin;
use App\Domain\Admin\Contextual\KeepContextualPagesPrivate;
use App\Support\Http\AdminViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(fn () => H::setUp());

it('resolves AdminViewer to the request\'s own ContextualAdmin instance', function () {
    $viewer = app(AdminViewer::class);

    expect($viewer)->toBeInstanceOf(ContextualAdmin::class)
        ->and($viewer)->toBe(app(ContextualAdmin::class))
        ->and(app(AdminViewer::class))->toBe($viewer);
});

it('markRendered() through AdminViewer is the state KeepContextualPagesPrivate reads', function () {
    $middleware = app(KeepContextualPagesPrivate::class);

    $untouched = $middleware->handle(Request::create('/x'), fn () => new Response('ok'));
    expect((string) $untouched->headers->get('Cache-Control'))->not->toContain('no-store');

    app(AdminViewer::class)->markRendered();

    expect(app(ContextualAdmin::class)->rendered())->toBeTrue();
    $private = $middleware->handle(Request::create('/x'), fn () => new Response('ok'));
    expect($private->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('reports the viewing admin as ContextualAdmin does, and nobody for a visitor', function () {
    H::visitor();
    expect(app(AdminViewer::class)->active())->toBeFalse()->and(app(AdminViewer::class)->adminId())->toBeNull();

    $admin = H::admin([]);
    Auth::guard('admin')->setUser($admin);

    expect(app(AdminViewer::class)->active())->toBeTrue()
        ->and(app(AdminViewer::class)->adminId())->toBe((int) $admin->getKey());
});

it('keeps hidden Khotab content private for an admin without any Khotab permission', function () {
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    $group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1]);
    $series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $group, 'title' => 'سلسلة مخفية', 'hidden' => 1, 'vedio' => 1]);
    $item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة مخفية', 'vedio' => 1, 'group_id' => $group, 'ser_id' => 0, 'hidden' => 1]);

    $this->get("/khotab-item-{$item}.htm")->assertNotFound();
    $this->get("/khotab-series-{$series}.htm")->assertNotFound();

    $session = H::session(H::admin([], 'no-khotab'));
    foreach (["/khotab-item-{$item}.htm" => 'مادة مخفية', "/khotab-series-{$series}.htm" => 'سلسلة مخفية'] as $url => $title) {
        $response = $this->withSession($session)->get($url)->assertOk()->assertSee($title);

        expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    }
});
