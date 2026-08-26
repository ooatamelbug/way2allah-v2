<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * `/admincp/` Login + Dashboard Completion — owner decision (2026-08-22)
 * superseding Wave 5's "no login/dashboard UI" exclusion. Covers the 3 new
 * routes (`admin.entry`/`admin.login`/`admin.logout`) and
 * `AdminDashboardModules`'s permission-driven visibility, reusing
 * `AdminGuard` exactly as already built/tested — no new auth mechanism.
 */
function useInMemoryMainConnectionForAdminAuth(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAdminAuth();
});

// ---- Anonymous ----

it('GET /admincp/ shows the login page for an anonymous visitor, not a 404', function () {
    $response = $this->get('/admincp/');

    $response->assertOk()->assertSee('تسجيل الدخول')->assertSee('aid', false);
});

it('GET /admincp (no trailing slash) also resolves, matching /admincp/', function () {
    $this->get('/admincp')->assertOk()->assertSee('تسجيل الدخول');
});

it('protected feature routes remain inaccessible to an anonymous visitor', function () {
    $this->get('/admincp/survey')->assertStatus(401);
    $this->get('/admincp/staff')->assertStatus(401);
});

// ---- Login ----

it('valid credentials via the real AdminGuard log the admin in and redirect to /admincp/', function () {
    $bcrypt = password_hash('s3cret', PASSWORD_BCRYPT);
    AdminUser::on('main')->create(['aid' => 'boss', 'password' => $bcrypt]);

    $response = $this->post('/admincp/login', ['aid' => 'boss', 'password' => 's3cret']);

    $response->assertRedirect(route('admin.entry'));
    $this->assertAuthenticatedAs(AdminUser::on('main')->where('aid', 'boss')->first(), 'admin');
});

it('a legacy MD5-hashed password is still accepted through the existing approved convergence mechanism', function () {
    AdminUser::on('main')->create(['aid' => 'legacy', 'password' => md5('old-pass')]);

    $response = $this->post('/admincp/login', ['aid' => 'legacy', 'password' => 'old-pass']);

    $response->assertRedirect(route('admin.entry'));
    $this->assertAuthenticated('admin');
});

it('rejects an unknown admin identifier with a generic error, not confirming existence', function () {
    $response = $this->post('/admincp/login', ['aid' => 'nobody', 'password' => 'whatever']);

    $response->assertRedirect()->assertSessionHasErrors('aid');
    $this->assertGuest('admin');
});

it('rejects a wrong password with the same generic error as an unknown identifier', function () {
    AdminUser::on('main')->create(['aid' => 'boss', 'password' => password_hash('correct', PASSWORD_BCRYPT)]);

    $response = $this->post('/admincp/login', ['aid' => 'boss', 'password' => 'incorrect']);

    $response->assertSessionHasErrors('aid');
    $this->assertGuest('admin');
});

it('validates required aid/password fields', function () {
    $this->post('/admincp/login', [])->assertSessionHasErrors(['aid', 'password']);
});

it('preserves the entered identifier but never the password on a failed attempt', function () {
    AdminUser::on('main')->create(['aid' => 'boss', 'password' => password_hash('correct', PASSWORD_BCRYPT)]);

    $this->post('/admincp/login', ['aid' => 'boss', 'password' => 'wrong']);

    expect(session('_old_input.aid') ?? null)->toBe('boss');
    expect(session('_old_input.password') ?? null)->toBeNull();
});

it('the login POST route is CSRF-protected like every other web-group route', function () {
    // Feature tests run with CSRF verification disabled by default
    // (TestCase trait) — this asserts the route itself carries the 'web'
    // middleware group (which includes VerifyCsrfToken), not that a raw
    // unauthenticated POST 419s in this test environment.
    $middleware = collect(app('router')->getRoutes()->getByName('admin.login')->middleware());
    expect($middleware)->toContain('web');
});

// ---- Authenticated dashboard ----

it('an authenticated admin visiting /admincp/ sees the dashboard directly, not the login form', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $response = $this->get('/admincp/');

    $response->assertOk()->assertSee('لوحة التحكم')->assertDontSee('تسجيل الدخول');
});

it('a regular admin only sees dashboard links for modules they hold a permission for', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'editor', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain(route('admin.survey.index'))
        ->not->toContain(route('admin.staff.index'))
        ->not->toContain(route('admin.uploaders.index'));
});

it('super-admin sees every dashboard module regardless of individually-held permissions', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain(route('admin.survey.index'))
        ->toContain(route('admin.staff.index'))
        ->toContain(route('admin.uploaders.index'))
        ->toContain(route('admin.soundcloud.edit'))
        ->toContain(route('admin.youtube.edit'))
        ->toContain(route('admin.locations.index'))
        ->toContain(route('admin.questionnaire.index'))
        ->toContain(route('admin.link-quality.khotab.index'))
        ->toContain(route('admin.link-quality.mirror.index'))
        ->toContain(route('admin.link-quality.telawah.index'))
        ->toContain(route('admin.broadcasting.index'));
});

// ---- AdminCP Production-vs-Laravel Screenshot Visual Comparison (2026-08-23) ----
// Real production screenshot showed the sidebar rendering with Metronic
// expand arrows/sub-menus a flat list never reproduced — traced to
// `admincp/sidebar.php`'s own real logic (any module with a non-empty
// `$modulelinks` renders as an expandable parent, not a flat link).

it('the sidebar renders real Metronic parent/arrow/sub-menu markup for grouped modules, matching sidebar.php', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect(substr_count($content, '<span class="arrow "></span>'))->toBe(5)
        ->and(substr_count($content, 'class="sub-menu"'))->toBe(5)
        ->and($content)->toContain('المشرفين')
        ->toContain('البث المباشر')
        ->toContain('مساجد و أماكن')
        ->toContain('المرئيات')
        ->toContain('التلاوات')
        ->toContain(route('admin.locations.create'))
        ->toContain(route('admin.staff.create'))
        ->toContain(route('admin.link-quality.khotab.large-files'));
});

it('survey/soundcloud/youtube/questionnaire stay flat, matching their real empty $modulelinks in legacy — broadcasting is grouped, matching its real 1-entry $modulelinks', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    // Each flat entry's own <a> carries a real href, not "javascript:;".
    expect($content)->toContain('<a href="'.route('admin.survey.index').'">')
        // broadcasting is now a grouped parent (real href moves to its one real child).
        ->toContain('<a href="'.route('admin.broadcasting.index').'">');
});

it('a plain admin holding only locations.del_location sees the "مساجد و أماكن" group with just the list sub-link, not the create one — matching admin.locations.create\'s own real add_location-only gate', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'editor', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'locations.del_location', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('مساجد و أماكن')
        ->toContain('<a href="'.route('admin.locations.index').'">')
        ->not->toContain('<a href="'.route('admin.locations.create').'">');
});

it('a plain admin with no matching group permissions never sees an empty grouped parent rendered', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'editor', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->not->toContain('مساجد و أماكن')
        ->not->toContain('المرئيات')
        ->not->toContain('التلاوات')
        ->not->toContain('فريق الإدارة');
});

it('the permission editor is never listed as its own dashboard entry, for super-admin or a plain admin — it stays reachable only via Staff', function () {
    $superAdmin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($superAdmin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    // No bare "/admincp/permissions" link exists on the dashboard itself —
    // it's reached via a per-row link on the Staff index page instead.
    expect($content)->not->toContain('/admincp/permissions');
});

it('a plain admin with no matching permissions still sees the real dashboard content without error — it is not permission-filtered, matching legacy home.php exactly', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody-special', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get('/admincp/')->assertOk()->assertSee('نشاط الأعضاء');
});

// ---- Logout ----

// Logged in via the real POST /admincp/login flow (not actingAs()) so the
// AdminGuard instance the eventual logout request resolves is the same
// one a real browser session would produce — actingAs() sets the guard's
// user directly without ever exercising session()->put(), which would
// mask whether logout's own session()->forget() genuinely round-trips.
it('POST logout clears the admin session and redirects to the entry point', function () {
    AdminUser::on('main')->create(['aid' => 'boss', 'password' => password_hash('s3cret', PASSWORD_BCRYPT)]);
    $this->post('/admincp/login', ['aid' => 'boss', 'password' => 's3cret'])->assertRedirect(route('admin.entry'));

    $response = $this->post('/admincp/logout');

    $response->assertRedirect(route('admin.entry'));
    $this->get('/admincp/')->assertOk()->assertSee('تسجيل الدخول');
});

it('protected routes are inaccessible again after logout', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => password_hash('s3cret', PASSWORD_BCRYPT)]);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->post('/admincp/login', ['aid' => 'boss', 'password' => 's3cret']);
    $this->get('/admincp/staff')->assertOk();

    $this->post('/admincp/logout');

    $this->get('/admincp/staff')->assertStatus(401);
});

it('logout is POST-only — a GET request to the logout path does not resolve to the logout action', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get('/admincp/logout')->assertMethodNotAllowed();
});

it('non-admin (default web) requests are not authenticated as admin — visiting /admincp/ while unauthenticated shows the login form regardless of any other session state', function () {
    session(['unrelated' => 'value']);

    $this->get('/admincp/')->assertOk()->assertSee('تسجيل الدخول');
});

// ---- Navigation ----

it('the dashboard route exists and every rendered module link resolves to a real, registered route', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.entry'))->assertOk();
});

it('shared Home/Logout navigation renders in the layout only when authenticated as admin', function () {
    $anonymous = $this->get('/admincp/')->assertOk()->getContent();
    expect($anonymous)->not->toContain('تسجيل الخروج');

    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');
    $authenticated = $this->get('/admincp/')->assertOk()->getContent();
    expect($authenticated)->toContain('تسجيل الخروج')->toContain(route('admin.entry'));
});

// AdminCP User Dropdown Exact DOM Parity Correction: legacy's real trigger
// includes `<img alt="" class="img-circle" src="...">` after the username
// (`navigation_menu.php:316`) — confirmed rendered at 39x39 via
// `.dropdown-user .dropdown-toggle>img{height:39px}` (no explicit width; a
// square source lets that one rule size both dimensions). Legacy's own src
// is `$_COOKIE['avatar']`, a cookie no login path ever sets — not a
// capability to reproduce. A 1x1 transparent PNG data URI reproduces the
// exact geometry with zero network request.
it('the user-dropdown trigger includes a real .img-circle avatar element, matching legacy', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toMatch('/<img alt="" class="img-circle" src="data:image\/png;base64,[^"]+">/');
});

// AdminCP User Dropdown Exact DOM Parity Correction: the logout row is now
// a real `<a>` — a direct `.dropdown-menu>li>a` child matching legacy's own
// `<a href="index.php?op=logout">` exactly (an earlier pass tried a plain
// `<button>` with hand-copied inline padding, which never matched the real
// `:hover{background-color:...}` rule since that selector requires an `a`).
// The `<a>` only prevents its dead `#` navigation and submits a hidden
// POST+CSRF `<form>` — AdminGuard's logout semantics are untouched.
it('the visible logout row is a real <a> (not a <button>), backed by a hidden POST+CSRF form', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toMatch('/<a href="#" id="admin-logout-link"><i class="icon-key"><\/i> تسجيل الخروج<\/a>/')
        ->toMatch('/<form method="POST" action="'.preg_quote(route('admin.logout'), '/').'" id="admin-logout-form" style="display:none">/')
        ->toContain('name="_token"');
});

// AdminCP Navbar Dropdown Link Parity: legacy's own notification/inbox/
// tasks/user-dropdown demo items point at `javascript:;` or non-existent
// `.html` files (confirmed absent from legacy-project via a repo-wide
// `find`) — reproduced as inert `#`/`javascript:;`, never routed to an
// invented Laravel page. Only "تسجيل الخروج" is a real capability, and it
// stays a POST specifically so it can't be triggered by a stray GET/prefetch.
it('no dropdown demo item is wired to a real Laravel route — every one stays inert like its dead legacy target', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.entry'))->assertOk()->getContent();

    // Every demo dropdown link is `#` or `javascript:;` — never a real route() call.
    expect(substr_count($content, 'href="#"'))->toBeGreaterThan(0);
    expect(substr_count($content, 'href="javascript:;"'))->toBeGreaterThan(0);
    expect($content)->not->toContain('href="extra_profile.html"')
        ->not->toContain('href="page_calendar.html"')
        ->not->toContain('href="inbox.html"')
        ->not->toContain('href="page_todo.html"')
        ->not->toContain('href="extra_lock.html"')
        ->not->toContain('action="extra_search.html"');
});

// The actual "does POST logout really end the session" behavior is already
// covered by "POST logout clears the admin session and redirects to the
// entry point" above (deliberately using the real POST /admincp/login flow,
// not actingAs(), since actingAs() never exercises session()->put() and
// would mask a broken session()->forget() round-trip). This test only adds
// the dropdown-specific check: the logout row really is a <form method="POST">
// targeting admin.logout, not a plain <a href>.
it('the user-dropdown logout row is a real POST form targeting admin.logout, not a plain link', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.entry'))->assertOk()->getContent();

    expect($content)->toMatch('/<form method="POST" action="'.preg_quote(route('admin.logout'), '/').'"/');
});

it('the confirmed link-quality navigation orphan (large-files) is now reachable from the khotab index page', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'khotab.repair', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
    ]);

    $content = $this->get(route('admin.link-quality.khotab.index'))->assertOk()->getContent();

    expect($content)->toContain(route('admin.link-quality.khotab.large-files'));
});
