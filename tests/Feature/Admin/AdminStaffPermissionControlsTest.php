<?php

/*
 * F02-UI (owner-approved, 2026-11-14) — staff-management controls are shown
 * only to viewers the backend would allow; the backend checks stay.
 *
 * Before: every viewer of the staff list (`authors.liststuff`) saw
 * «تعديل الصلاحيات» on every row and «إضافة مشرف», and a non-super-admin who
 * added staff was redirected into the super-admin-only permission editor —
 * each ending in a 403. After: those controls render only for a super-admin
 * (the permission editor) or an `authors.addstuff` holder (add staff).
 */

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDashboardModules;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

beforeEach(function () {
    // `admin_audit_log`: adding an admin is audited (`create_admin`) since administrator management (2026-11-17).
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors(), 'admin_audit_log' => MainSchema::adminAuditLog()]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);

    $this->colleague = AdminUser::on('main')->create(['aid' => 'colleague', 'password' => 'x', 'radminsuper' => 0]);
    $this->boss = AdminUser::on('main')->create(['aid' => 'boss2', 'password' => 'x', 'radminsuper' => 1]);
});

function staffViewer(array $permissions = [], bool $superAdmin = false): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'viewer', 'password' => 'x', 'radminsuper' => $superAdmin ? 1 : 0]);
    foreach ($permissions as $name) {
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
    }
    if ($superAdmin) {
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    }

    return $admin;
}

it('a super-admin sees «تعديل الصلاحيات» on every row, the column header, and «إضافة مشرف»', function () {
    $this->actingAs(staffViewer([], true), 'admin');

    $html = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($html)->toContain('<th>الصلاحيات</th>')
        ->toContain('href="'.route('admin.permissions.edit', $this->colleague).'"')
        ->toContain('href="'.route('admin.permissions.edit', $this->boss).'"')
        ->toContain('href="'.route('admin.staff.create').'"')
        ->and(substr_count($html, 'تعديل الصلاحيات'))->toBe(3);
});

it('a list-only admin sees the roster but no permission controls and no add link', function () {
    $this->actingAs(staffViewer(['authors.liststuff']), 'admin');

    $html = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($html)->toContain('colleague')->toContain('boss2')
        ->not->toContain('تعديل الصلاحيات')
        ->not->toContain('<th>الصلاحيات</th>')
        ->not->toContain('/admincp/permissions/')
        ->not->toContain('href="'.route('admin.staff.create').'"');
});

it('a list + add admin sees «إضافة مشرف» but still no permission controls', function () {
    $this->actingAs(staffViewer(['authors.liststuff', 'authors.addstuff']), 'admin');

    $html = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('admin.staff.create').'"')
        ->not->toContain('تعديل الصلاحيات');
});

it('backend authorization is unchanged: a non-super-admin still gets 403 on every permission-editor route', function () {
    $this->actingAs(staffViewer(['authors.liststuff', 'authors.addstuff']), 'admin');

    $this->get(route('admin.permissions.edit', $this->colleague))->assertForbidden();
    $this->put(route('admin.permissions.update', $this->colleague), ['permissions' => []])->assertForbidden();
    $this->put(route('admin.permissions.password', $this->colleague), ['new_password' => 'abcdefgh1234'])->assertForbidden();
});

it('backend authorization is unchanged: the staff list and add routes keep their own permission checks', function () {
    $this->actingAs(staffViewer(), 'admin');

    $this->get(route('admin.staff.index'))->assertForbidden();
    $this->get(route('admin.staff.create'))->assertForbidden();
    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertForbidden();
});

it('after adding staff, a super-admin is taken to the permission editor', function () {
    $this->actingAs(staffViewer([], true), 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com']);

    $response = $this->post(route('admin.staff.store'), ['vbuid' => '99']);
    $new = AdminUser::on('main')->where('uid', 99)->sole();

    $response->assertRedirect(route('admin.permissions.edit', $new));
});

it('after adding staff, a non-super-admin is sent to a page they can open, never to the 403 permission editor', function () {
    DB::connection('vbulletin')->table('user')->insert([
        ['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com'],
        ['userid' => 98, 'username' => 'otherstaff', 'email' => 'other@example.com'],
    ]);

    $this->actingAs(staffViewer(['authors.liststuff', 'authors.addstuff']), 'admin');
    $this->post(route('admin.staff.store'), ['vbuid' => '99'])
        ->assertRedirect(route('admin.staff.index'))
        ->assertSessionHas('success');
    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeTrue();

    $addOnly = AdminUser::on('main')->create(['aid' => 'addonly', 'password' => 'x']);
    $addOnly->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));
    $this->actingAs($addOnly, 'admin');
    $this->post(route('admin.staff.store'), ['vbuid' => '98'])->assertRedirect(route('admin.staff.create'));
});

it('the staff menu entries are gated by the same permissions as their routes, and the permission editor has no menu entry', function () {
    $menu = app(AdminDashboardModules::class);
    $routesFor = function (AdminUser $admin) use ($menu): array {
        $routes = [];
        foreach ($menu->visibleFor($admin) as $module) {
            foreach ($module['children'] ?? [$module] as $entry) {
                $routes[] = $entry['route'];
            }
        }

        return $routes;
    };

    expect($routesFor(staffViewer()))->not->toContain('admin.staff.index')->not->toContain('admin.staff.create');

    AdminUser::on('main')->where('aid', 'viewer')->delete();
    expect($routesFor(staffViewer(['authors.liststuff'])))->toContain('admin.staff.index')->not->toContain('admin.staff.create');

    AdminUser::on('main')->where('aid', 'viewer')->delete();
    $boss = staffViewer([], true);
    expect($routesFor($boss))->toContain('admin.staff.index')->toContain('admin.staff.create')
        ->not->toContain('admin.permissions.edit');
});
