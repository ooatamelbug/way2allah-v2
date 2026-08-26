<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * AdminCP Full Visual/Layout Parity Reconstruction (2026-08-22). Asserts
 * the reconstructed shell renders (source-level, not pixel-level — see
 * the closure report's `HTML_SOURCE_PARITY_VERIFIED` /
 * `BROWSER_RENDERING_UNVERIFIED` classification), the permission-aware
 * sidebar keeps working, and none of the confirmed legacy demo/dead
 * content leaked back in.
 */
function useInMemoryMainConnectionForVisualParity(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForVisualParity();
});

// ---- Login page shell ----

it('renders the reconstructed login shell — logo, RTL, form title, no legacy debug/cookie markup', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('dir="rtl"')
        ->toContain('login-logo.png')
        ->toContain('تسجيل الدخول')
        ->toContain('name="aid"')
        ->toContain('name="password"')
        ->not->toContain('var_dump')
        ->not->toContain('name="pwd_md5"')
        ->not->toContain('name="remember"');
});

// ---- Login Final Visual-Parity Pass (2026-08-22) ----

it('login shell includes the sidebar-toggler div, matching legacy markup exactly', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('menu-toggler sidebar-toggler');
});

it('login page loads the required jquery-validation and backstretch assets, with self-hosted background images (not the remote origin)', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('jquery.validate.min.js')
        ->toContain('additional-methods.min.js')
        ->toContain('jquery.backstretch.min.js')
        ->toContain('vendor/login-bg/1.jpg')
        ->toContain('vendor/login-bg/2.jpg')
        ->toContain('vendor/login-bg/3.jpg')
        ->toContain('vendor/login-bg/4.jpg')
        ->not->toContain('/new/assets/pages/media/bg')
        ->not->toContain('way2allah.com/assets/pages/media/bg');
});

it('login form carries the login-form class and IE8/9 fallback labels, matching legacy structure', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('class="login-form"')
        ->toContain('visible-ie8 visible-ie9')
        ->toContain('اسم المستخدم')
        ->toContain('كلمة المرور');
});

it('login inputs rely on client-side validation, not native required attributes, matching legacy exactly', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->not->toMatch('/name="aid"[^>]*required/')
        ->not->toMatch('/name="password"[^>]*required/');
});

it('CSRF protection remains present on the login form after the visual-parity pass', function () {
    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('_token');
});

it('login with valid credentials still succeeds after the visual-parity pass', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => password_hash('s3cret', PASSWORD_BCRYPT)]);

    $response = $this->post('/admincp/login', ['aid' => 'boss', 'password' => 's3cret']);

    $response->assertRedirect(route('admin.entry'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

// ---- Authenticated shell ----

it('the 4 self-hosted login background images actually resolve, matching the file(s) reachability check used for every other vendored asset', function () {
    foreach ([1, 2, 3, 4] as $index) {
        $path = public_path("vendor/login-bg/{$index}.jpg");
        expect(file_exists($path))->toBeTrue("missing {$path}");
        expect(filesize($path))->toBeGreaterThan(1000);
    }
});

it('renders the reconstructed authenticated shell — logo, sidebar wrapper, portlet, footer, real logout form', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('page-sidebar-wrapper')
        ->toContain('page-header')
        ->toContain('logo-light.png')
        ->toContain('portlet light')
        ->toContain('page-footer')
        ->toContain('boss')
        ->toContain(route('admin.logout'))
        ->toContain('_token');
});

// Corrected 2026-08-26 (owner-supplied real production-vs-Laravel
// screenshots): the standing `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE`
// exclusion this test used to enforce was explicitly reversed by the
// owner — the dashboard now reproduces `admincp/home.php`'s real content
// verbatim, hardcoded demo numbers included (every number in this test
// is transcribed directly from that legacy file, not invented). See
// `resources/views/admin/dashboard.blade.php`'s own docblock.
it('renders the real legacy dashboard demo content verbatim — stat cards, member table, activity feed', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('17800')
        ->toContain('sales_statistics')
        ->toContain('sparkline_bar')
        ->toContain('region_statistics')
        ->toContain('أبوسلمى المصري')
        ->toContain('لا تنسوا الدعاء لأخيكم الأدمن');
});

// Corrected 2026-08-26 (owner-supplied real header screenshots,
// decision-log #41's reversal extended to the header): the
// notification/inbox/todo dropdowns are now reproduced verbatim — see
// `layouts/admin.blade.php`'s own docblock. The "Actions" dropdown and
// the user-menu's Profile/Calendar/Lock-Screen entries remain correctly
// excluded (not evidenced as visually different in anything supplied).
it('renders the real notification/inbox/todo dropdown triggers, but not the Actions menu or the dead user-menu entries', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain('header_notification_bar')
        ->toContain('header_inbox_bar')
        ->toContain('header_task_bar')
        ->not->toContain('extra_profile.html')
        ->not->toContain('extra_lock.html')
        ->not->toContain('page_todo.html');
});

// ---- Permission-aware sidebar ----

it('the sidebar lists only permitted modules', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'editor', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->toContain(route('admin.survey.index'))
        ->not->toContain(route('admin.staff.index'));
});

it('the sidebar Home link is active on the dashboard itself', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    $iconHomePosition = strpos($content, 'icon-home');
    $precedingLi = strrpos(substr($content, 0, $iconHomePosition), '<li');
    $liTag = substr($content, $precedingLi, $iconHomePosition - $precedingLi);
    expect($liTag)->toContain('active');
});

it('a sidebar module link carries the active class while visiting one of its own sub-pages', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    $staffLinkPosition = strpos($content, 'href="'.route('admin.staff.index').'"');
    $precedingMarkup = substr($content, max(0, $staffLinkPosition - 80), 80);
    expect($precedingMarkup)->toContain('active');
});

// ---- Representative feature-page shell ----

it('a representative feature page (Staff index) inherits the real portlet/shell chrome without any change to its own business content', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->toContain('portlet box purple')
        ->toContain('portlet-title')
        ->toContain('المشرفين');
});

it('a representative feature page (Broadcasting index) inherits the real shell — sidebar, portlet, no dead add/delete controls', function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.broadcasting.index'))->assertOk()->getContent();

    expect($content)->toContain('page-sidebar-wrapper')
        ->toContain('portlet box purple')
        ->not->toContain('addstream')
        ->not->toContain('delete_stream');
});

// ---- No dead menu items anywhere in the sidebar ----

it('the sidebar never contains a link to a dead/demo legacy target', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get('/admincp/')->assertOk()->getContent();

    expect($content)->not->toContain('extra_search.html')
        ->not->toContain('inbox.html')
        ->not->toContain('page_calendar.html');
});
