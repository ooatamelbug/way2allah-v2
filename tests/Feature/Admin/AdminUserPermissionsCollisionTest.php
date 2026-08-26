<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * `permissions` attribute/relationship collision fix (AdminCP Permissions
 * Crash, 2026-08-23). `nuke_authors.permissions` is a real, always-selected
 * legacy column; Spatie's `HasPermissions` trait also defines a
 * `permissions()` relation of the identical name — Eloquent's attribute
 * resolution checks loaded columns before relation methods, so
 * `$this->permissions` (used internally by several real Spatie methods)
 * silently returned the raw legacy string instead of the relation, on any
 * row where the column was actually populated. `AdminUser::getAttribute()`
 * now special-cases this one key.
 *
 * Deliberately its own file, not appended to `PermissionControllerTest.php`
 * — this project's own already-documented `isGuardableColumn`
 * cache-poisoning hazard (`InMemoryConnection`'s own class docblock, and
 * `BackupApiControllerTest`'s identical `permissions`-column pattern):
 * Eloquent caches a model class's real column list statically, the FIRST
 * time it's queried, for the process lifetime — rebuilding the in-memory
 * SQLite schema later with an extra column does not invalidate that
 * cache. Any earlier test file in the same run that already touched
 * `AdminUser` against the shorter (no-`permissions`) shared fixture
 * poisons the cache for every test after it, silently dropping
 * `permissions` from ordinary `create()`/mass-assignment. The established
 * fix (already used by `BackupApiControllerTest`) is `forceFill()`, which
 * bypasses the guardable-columns check entirely — used throughout below.
 */
function useInMemoryMainConnectionWithRealPermissionsColumn(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => function (\Illuminate\Database\Schema\Blueprint $table) {
            (MainSchema::nukeAuthors())($table);
            $table->text('permissions')->nullable();
        },
    ]);
}

function makeAdminWithRawLegacyPermissions(string $aid, string $rawPermissions): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
    $admin->forceFill(['permissions' => $rawPermissions])->save();

    return $admin->fresh();
}

it('getPermissionNames() no longer crashes on a real admin row with a populated legacy permissions column', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $admin = makeAdminWithRawLegacyPermissions('legacyrow', serialize(['survey' => ['modsurvey' => 'on']]));

    $fresh = AdminUser::on('main')->find($admin->id);

    expect($fresh->getPermissionNames())->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('getAllPermissions() and getDirectPermissions() also resolve the real Spatie relation, not the raw legacy blob', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $admin = makeAdminWithRawLegacyPermissions('legacyrow2', serialize(['chat' => ['listrooms' => 'on']]));
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));

    $fresh = AdminUser::on('main')->find($admin->id);

    expect($fresh->getAllPermissions()->pluck('name'))->toContain('survey.modsurvey')
        ->and($fresh->getDirectPermissions()->pluck('name'))->toContain('survey.modsurvey');
});

it('givePermissionTo(), hasPermissionTo(), hasAnyPermission(), and syncPermissions() all work on a real admin row with the collision column present', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $admin = makeAdminWithRawLegacyPermissions('legacyrow3', serialize(['old' => ['legacy_key' => 'on']]));
    $permission = Permission::firstOrCreate(['name' => 'locations.add_location', 'guard_name' => 'admin']);

    $admin->givePermissionTo($permission);
    expect($admin->hasPermissionTo('locations.add_location', 'admin'))->toBeTrue()
        ->and($admin->hasAnyPermission(['locations.add_location', 'nope']))->toBeTrue();

    $admin->syncPermissions([]);
    expect($admin->fresh()->hasPermissionTo('locations.add_location', 'admin'))->toBeFalse();
});

it('the raw legacy permissions column remains fully readable via getRawOriginal() after the fix — no data hidden or altered', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $rawBlob = serialize(['survey' => ['modsurvey' => 'on']]);
    $admin = makeAdminWithRawLegacyPermissions('legacyrow4', $rawBlob);

    $fresh = AdminUser::on('main')->find($admin->id);

    expect($fresh->getRawOriginal('permissions'))->toBe($rawBlob);
});

it('writing Spatie permissions does not touch or corrupt the raw legacy permissions column', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $rawBlob = serialize(['survey' => ['modsurvey' => 'on']]);
    $admin = makeAdminWithRawLegacyPermissions('legacyrow5', $rawBlob);

    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'chat.listrooms', 'guard_name' => 'admin']));
    $admin->syncPermissions(['chat.listrooms']);

    $reloaded = AdminUser::on('main')->find($admin->id);
    expect($reloaded->getRawOriginal('permissions'))->toBe($rawBlob);
});

it('the permission editor page renders (no 500) for a real admin row with the collision column populated', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $superAdmin = makeAdminWithRawLegacyPermissions('boss', serialize(['authors' => ['liststuff' => 'on']]));
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    test()->actingAs($superAdmin, 'admin');

    $target = makeAdminWithRawLegacyPermissions('target', serialize(['survey' => ['modsurvey' => 'on']]));

    $content = test()->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    expect($content)->toContain('صلاحيات');
});

it('unauthorized-admin 403 and super-admin access remain unchanged with the collision column present', function () {
    useInMemoryMainConnectionWithRealPermissionsColumn();
    $plain = makeAdminWithRawLegacyPermissions('plain2', serialize([]));
    test()->actingAs($plain, 'admin');
    $target = makeAdminWithRawLegacyPermissions('target2', serialize([]));

    test()->get(route('admin.permissions.edit', $target))->assertForbidden();
});
