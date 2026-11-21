<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 0 — the six mirror permissions as they appear
 * in the real permission-management surface.
 *
 * `MirrorPermissionsPhase0Test` covers the permissions and the authorizer
 * in isolation; this exercises the actual `admin.permissions.edit` route so
 * the Khotab labels, the new `anasheed` module block, and the
 * "nothing pre-granted" state are verified end-to-end rather than through
 * `PermissionLabels` alone. Same harness as `PermissionControllerTest`.
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        // F02 / owner decision 16: permission edits are now audited.
        'admin_audit_log' => MainSchema::adminAuditLog(),
    ]);

    (new AdminPermissionSeeder)->run();
});

function phase0UiSuperAdmin(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'ui-boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

it('renders the Khotab mirror permissions with their real legacy Arabic labels', function () {
    phase0UiSuperAdmin();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    foreach (['khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror'] as $permission) {
        expect($content)->toContain($permission);
    }

    foreach (['إضافة وصلة بديلة', 'تعديل وصلة بديلة', 'حذف وصلة بديلة'] as $label) {
        expect($content)->toContain($label);
    }
});

it('renders the new anasheed module block with its own three permissions', function () {
    phase0UiSuperAdmin();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->toContain('الأناشيد');

    foreach (['anasheed.addmirror', 'anasheed.editmirror', 'anasheed.delmirror'] as $permission) {
        expect($content)->toContain($permission);
    }
});

it('shows every mirror permission unchecked for an admin who has not been granted one', function () {
    phase0UiSuperAdmin();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    foreach ([
        'khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror',
        'anasheed.addmirror', 'anasheed.editmirror', 'anasheed.delmirror',
    ] as $permission) {
        expect($content)->not->toMatch('/value="'.preg_quote($permission, '/').'"[^>]*checked/');
    }
});

it('grants and revokes a mirror permission through the real update route', function () {
    phase0UiSuperAdmin();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    // The editor posts a checkbox map, not a list — `PermissionController::update()`
    // reads `array_keys(array_filter($request->input('permissions', [])))`.
    $this->put(route('admin.permissions.update', $target), [
        'permissions' => ['khotab.editmirror' => '1'],
    ])->assertRedirect();

    expect($target->fresh()->hasPermissionTo('khotab.editmirror', 'admin'))->toBeTrue();

    // syncPermissions semantics: omitting it removes it, not merely no-op.
    $this->put(route('admin.permissions.update', $target), ['permissions' => []])->assertRedirect();

    expect($target->fresh()->hasPermissionTo('khotab.editmirror', 'admin'))->toBeFalse();
});

it('consults no legacy permission source when rendering the editor', function () {
    phase0UiSuperAdmin();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->get(route('admin.permissions.edit', $target))->assertOk();

    $joined = implode(' | ', $queries);

    foreach (['nuke_islamic_mod', 'nuke_anasheed_mod', 'nuke_telawah_mod', 'nuke_w2acd_mod'] as $legacyTable) {
        expect($joined)->not->toContain($legacyTable);
    }
});
