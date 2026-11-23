<?php

/*
 * Admin session indicator (owner request 2026-11-23): on public pages, a signed-in Laravel administrator sees who they
 * are signed in as, a link to AdminCP and a secure logout — centred in the existing top bar (desktop) and as a compact
 * dropdown beside the mobile menu button. Visitors see the top bar unchanged. Isolated in-memory data.
 */

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '', 'vedio' => 1, 'audio' => 0]);
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة', 'vedio' => 1, 'hidden' => 0]);
    $this->page = '/khotab-video-7.htm';
});

function asiAdmin(array $permissions, int $uid, string $name = 'مشرف الاختبار'): AdminUser
{
    $admin = H::admin($permissions, 'asi-'.uniqid());
    $admin->forceFill(['uid' => $uid, 'name' => $name])->save();

    return $admin->fresh();
}

/** The top bar's own HTML (the .pre-header block). */
function asiTopBar(string $html): string
{
    $start = strpos($html, '<div class="pre-header">');
    $end = strpos($html, '<!-- END TOP BAR -->');

    return $start === false || $end === false ? '' : substr($html, $start, $end - $start);
}

it('shows a visitor no indicator, and the top bar exactly as before', function () {
    $response = $this->get($this->page)->assertOk();
    $html = (string) $response->getContent();

    expect($html)->not->toContain('data-admin-session')->not->toContain('data-admin-session-menu')
        ->not->toContain('w2a-admin-session')->not->toContain(route('admin.logout'))
        ->and(asiTopBar($html))->toContain('المنتــــدى')->toContain('fa-facebook')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('shows the signed-in administrator\'s name (escaped), the AdminCP link and a POST + CSRF logout — desktop and mobile', function (int $uid) {
    $admin = asiAdmin([], $uid, 'أبو <b>"x"</b> & y');
    $html = (string) $this->withSession(H::session($admin))->get($this->page)->assertOk()->getContent();
    $bar = asiTopBar($html);

    // desktop: inside the existing top bar's row, between the two columns
    expect($bar)->toContain('data-admin-session')
        ->toContain('مشرف: <strong>أبو &lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; y</strong>')
        ->not->toContain('<b>"x"</b>')
        ->toContain('href="'.route('admin.entry').'"')
        ->toMatch('~<form method="post" action="'.preg_quote(route('admin.logout'), '~').'">\s*<input type="hidden" name="_token" value="[^"]+"~u')
        ->toContain('name="return_to" value="'.$this->page.'"');
    expect(strpos($bar, 'data-admin-session'))->toBeGreaterThan(strpos($bar, 'additional-shop-info'))
        ->and(strpos($bar, 'data-admin-session'))->toBeLessThan(strpos($bar, 'social-media'));

    // tablet (768-1199px): the compact dropdown inside the same top-bar slot
    expect($bar)->toContain('<details class="w2a-admin-session-menu w2a-admin-session-menu--bar" data-admin-session-menu="bar">');

    // mobile (<= 767px): the same dropdown beside the menu button, outside the top bar
    $header = substr($html, strpos($html, '<div class="header">'));
    expect($header)->toContain('<details class="w2a-admin-session-menu w2a-admin-session-menu--header" data-admin-session-menu="header">')
        ->toContain('aria-label="حساب المشرف: أبو &lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; y"')
        ->toContain('role="group" aria-label="حساب المشرف"')
        ->and(strpos($header, 'data-admin-session-menu="header"'))->toBeLessThan(strpos($header, 'class="mobi-toggler"'));

    // every logout control (pill, top-bar dropdown, header dropdown) is a POST form with the CSRF token and the return page
    $logout = '~<form method="post" action="'.preg_quote(route('admin.logout'), '~').'">\s*<input type="hidden" name="_token" value="[^"]+"[^>]*>\s*<input type="hidden" name="return_to" value="'.preg_quote($this->page, '~').'">~u';
    expect(preg_match_all($logout, $html))->toBe(3)
        ->and(substr_count($html, 'action="'.route('admin.logout').'"'))->toBe(3);

    // the styles and the close-on-outside / Escape script are printed once
    expect(substr_count($html, '.w2a-admin-session-slot{direction:rtl}'))->toBe(1)
        ->and(substr_count($html, "event.key !== 'Escape'"))->toBe(1);

    // no session identifier is printed
    expect($html)->not->toContain(session()->getId());
})->with(['standalone (uid 0)' => [0], 'forum-linked' => [4242]]);

it('falls back to the login name when the administrator has no display name', function () {
    $admin = asiAdmin([], 0, '');
    $html = (string) $this->withSession(H::session($admin))->get($this->page)->getContent();

    expect($html)->toContain('مشرف: <strong>'.e($admin->aid).'</strong>');
});

it('refuses a GET logout, and the logout route is CSRF-protected', function () {
    $admin = asiAdmin([], 0);

    $this->withSession(H::session($admin))->get(route('admin.logout'))->assertStatus(405);

    // Laravel skips the CSRF check while unit tests run, so prove the wiring instead: POST-only, inside the web group,
    // whose CSRF middleware is present.
    $route = app('router')->getRoutes()->getByName('admin.logout');
    $web = app('router')->getMiddlewareGroups()['web'] ?? [];
    expect($route->methods())->toBe(['POST'])
        ->and($route->gatherMiddleware())->toContain('web')
        ->and(array_filter($web, fn ($m) => is_a($m, PreventRequestForgery::class, true)))->not->toBe([]);

    // still signed in: the indicator is still there
    expect((string) $this->withSession(H::session($admin))->get($this->page)->getContent())->toContain('data-admin-session');
});

it('logs out only the administrator, returns to the page, and the indicator and controls are gone', function () {
    $admin = asiAdmin(['khotab.editauthor'], 4242);
    $before = (string) $this->withSession(H::session($admin) + ['keep_me' => 'forum-side-data'])->get($this->page)->getContent();
    expect($before)->toContain('data-admin-session')->toContain('data-contextual-admin');

    $token = session()->token();
    $response = $this->post(route('admin.logout'), ['_token' => $token, 'return_to' => $this->page]);

    $response->assertRedirect($this->page);
    expect(session()->has('admin_auth_id'))->toBeFalse()
        ->and(session('keep_me'))->toBe('forum-side-data');   // only the admin key left the session

    Auth::forgetGuards();
    $after = (string) $this->get($this->page)->getContent();
    expect($after)->not->toContain('data-admin-session')->not->toContain('data-contextual-admin');
});

it('never redirects a logout off-site or into AdminCP', function (string $unsafe) {
    $admin = asiAdmin([], 0);
    $this->withSession(H::session($admin))->get($this->page);

    $this->post(route('admin.logout'), ['_token' => session()->token(), 'return_to' => $unsafe])->assertRedirect(route('admin.entry'));
})->with([
    'other host' => ['https://evil.example/x'],
    'protocol-relative' => ['//evil.example/x'],
    'backslash trick' => ['/\\evil.example'],
    'javascript scheme' => ['javascript:alert(1)'],
    'AdminCP' => ['/admincp/staff'],
    'empty' => [''],
]);

it('keeps the forum (vBulletin) sign-in untouched on logout', function () {
    $admin = asiAdmin([], 4242);
    $prefix = (string) config('services.vbulletin.cookie_prefix', 'bb_');
    $this->withSession(H::session($admin))->get($this->page);

    $response = $this->withUnencryptedCookies([$prefix.'sessionhash' => 'abc', $prefix.'userid' => '4242'])
        ->post(route('admin.logout'), ['_token' => session()->token(), 'return_to' => $this->page]);

    $names = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
    expect(array_filter($names, fn ($n) => str_starts_with($n, $prefix)))->toBe([]);   // no forum cookie is changed or expired
});

it('keeps every HTML page private for a signed-in administrator, even one without contextual controls', function () {
    $admin = asiAdmin([], 0);
    expect((string) $this->withSession(H::session($admin))->get($this->page)->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    // a page that renders no contextual control at all (only the session state differs)
    Route::middleware('web')->get('/__asi-html', fn () => response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'public, max-age=60']));
    expect((string) $this->withSession(H::session($admin))->get('/__asi-html')->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')->not->toContain('public');
});

it('leaves a visitor\'s HTML page caching exactly as the page set it', function () {
    Route::middleware('web')->get('/__asi-html', fn () => response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'public, max-age=60']));

    expect((string) $this->get('/__asi-html')->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60')->not->toContain('no-store');
});

it('does not touch non-HTML responses (images, downloads) for a signed-in administrator', function () {
    Route::middleware('web')->get('/__asi-image', fn () => response('png', 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=60']));
    $admin = asiAdmin([], 0);

    $response = $this->withSession(H::session($admin))->get('/__asi-image')->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60')->not->toContain('no-store');
});

it('does not change contextual controls or the Khotab media scope', function () {
    $sectionless = asiAdmin(['khotab.addseries'], 0);   // section-scoped key, no section
    $video = asiAdmin(['khotab.addseries', 'khotab.media_video'], 4242);
    $addSeries = 'href="'.route('admin.khotab.authors.series.create', [7, 'video']);

    expect((string) $this->withSession(H::session($sectionless))->get($this->page)->getContent())->toContain('data-admin-session')->not->toContain($addSeries)
        ->and((string) $this->withSession(H::session($video))->get($this->page)->getContent())->toContain('data-admin-session')->toContain($addSeries);
});
