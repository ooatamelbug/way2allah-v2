<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Roadmap task 5.3. Replaces `admincp/authors/edit_author.php` (the one
 * working copy of 5) and fixes the confirmed weak-password-write finding (password reset always wrote plain
 * MD5, the only password-set path anywhere in `admincp/`, confirmed by
 * `admincp.md` §8).
 */
function useInMemoryMainConnectionForPermissionController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

function actingAsSuperAdminForPermissions(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryMainConnectionForPermissionController();
});

it('rejects a plain (non-super-admin) admin from the permission editor', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'plain', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $this->get(route('admin.permissions.edit', $target))->assertForbidden();
});

it('renders the permission grid grouped by module, checkbox state reflecting real assigned permissions', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    Permission::firstOrCreate(['name' => 'chat.listrooms', 'guard_name' => 'admin']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    // The assigned permission's checkbox is checked; an unassigned one is not.
    expect($content)
        ->toMatch('/name="permissions\[survey\.modsurvey\]"[^>]*checked/')
        ->not->toMatch('/name="permissions\[chat\.listrooms\]"[^>]*checked/');
});

it('updates an admin\'s permissions via syncPermissions — unchecked permissions are removed, not just additive', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    Permission::firstOrCreate(['name' => 'chat.listrooms', 'guard_name' => 'admin']);

    $this->put(route('admin.permissions.update', $target), [
        'permissions' => ['chat.listrooms' => '1'],
    ])->assertRedirect();

    $target->refresh();
    expect($target->hasPermissionTo('chat.listrooms', 'admin'))->toBeTrue()
        ->and($target->hasPermissionTo('survey.modsurvey', 'admin'))->toBeFalse();
});

it('Blueprint §16 item 4 fix: password reset writes a real bcrypt hash, not MD5', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $this->put(route('admin.permissions.password', $target), ['new_password' => 'a-real-password'])->assertRedirect();

    $target->refresh();
    expect(str_starts_with($target->password, '$2y$'))->toBeTrue()
        ->and($target->password)->not->toBe(md5('a-real-password'));
});

// ---- vBulletin profile/stats portlets (AdminCP Final Page-Level Visual-Parity Closure, 2026-08-22) ----

it('renders the profile-sidebar and member-stats portlets when the admin has a linked vBulletin account', function () {
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x', 'uid' => 501]);
    DB::connection('vbulletin')->table('user')->insert([
        'userid' => 501, 'username' => 'target', 'email' => 'target@example.com',
        'posts' => 1234, 'usertitle' => 'عضو مميز',
        'joindate' => time() - (100 * 86400), 'lastactivity' => time() - 3600, 'lastpost' => time() - 7200,
    ]);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    // "الملف الشخصي" alone also now appears in the header's real user-dropdown
    // (decision-log #41's reversal) — scoped to the profile-content portlet's
    // own icon+caption pairing so this still proves that specific portlet renders.
    expect($content)->toContain('بيانات العضو')
        ->toContain('icon-globe"></i>الملف الشخصي')
        ->toContain('عضو مميز')
        ->toContain('target@example.com')
        ->toContain('1,234');
});

it('renders no profile/stats portlets when the admin has no linked vBulletin account, without erroring', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    // "الملف الشخصي" alone now also appears in the header's real user-dropdown
    // (decision-log #41's reversal) on every page — scoped to the profile-content
    // portlet's own icon+caption pairing instead of the bare string.
    expect($content)->not->toContain('بيانات العضو')->not->toContain('icon-globe"></i>الملف الشخصي');
});

it('never renders the dead "حذف كمشرف" control from legacy\'s profile sidebar', function () {
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x', 'uid' => 502]);
    DB::connection('vbulletin')->table('user')->insert(['userid' => 502, 'username' => 'target']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->not->toContain('حذف كمشرف');
});

it('the permission form and its saving semantics are unaffected by the new profile portlets', function () {
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x', 'uid' => 503]);
    DB::connection('vbulletin')->table('user')->insert(['userid' => 503, 'username' => 'target']);
    Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']);

    $this->put(route('admin.permissions.update', $target), ['permissions' => ['survey.modsurvey' => '1']])->assertRedirect();

    $target->refresh();
    expect($target->hasPermissionTo('survey.modsurvey', 'admin'))->toBeTrue();
});

// ---- Authenticated Design/CSS Parity (2026-08-23) — real Metronic markup, not raw keys/bare checkboxes ----

it('renders each permission module\'s real legacy Arabic name and icon, not the raw internal module key', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->toContain('الاستبيانات')
        ->toContain('icon-calculator')
        ->not->toContain('caption"> survey')
        ->not->toContain('>survey<');
});

it('renders each permission as a real Metronic md-checkbox with its real legacy Arabic label, not a bare checkbox with the raw permission name', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->toContain('class="md-check"')
        ->toContain('md-checkbox has-error')
        ->toContain('مشرف استبيانات')
        ->not->toContain('>survey.modsurvey<');
});

it('the save button and password form use real Metronic button/form-control classes, not bare elements', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->toContain('class="btn green-haze"')
        ->toContain('class="form-control"')
        ->toContain('class="btn btn-primary"');
});
