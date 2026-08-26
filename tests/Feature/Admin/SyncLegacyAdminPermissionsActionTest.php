<?php

use App\Domain\Admin\Actions\SyncLegacyAdminPermissionsAction;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDashboardModules;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * AdminCP Real-User Permission Parity Reconciliation (2026-08-23). Covers
 * `SyncLegacyAdminPermissionsAction` — the bridge `RoleSeeder`'s own
 * docblock explicitly deferred and that was never implemented, which left
 * `model_has_roles`/`model_has_permissions` completely empty for every
 * real admin in this environment (confirmed via direct inspection before
 * writing this fix, not assumed).
 *
 * Deliberately its own file, no competing `beforeEach()` — same
 * `isGuardableColumn` cache-poisoning precedent as
 * `AdminUserPermissionsCollisionTest`: the `permissions` column must be
 * set via `forceFill()`, never mass-assignment, and this file must not run
 * `AdminUser::on('main')->create()` against a shorter schema before the
 * `permissions` column is added to the local fixture.
 */
function useInMemoryMainConnectionWithRealPermissionsColumnForSync(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => function (\Illuminate\Database\Schema\Blueprint $table) {
            (MainSchema::nukeAuthors())($table);
            $table->text('permissions')->nullable();
        },
        'nuke_survey' => MainSchema::nukeSurvey(),
    ]);
}

function makeLegacyAdmin(string $aid, bool $radminsuper, ?string $rawPermissions): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
    $admin->forceFill(['radminsuper' => $radminsuper, 'permissions' => $rawPermissions])->save();

    return $admin->fresh();
}

function seedRealPermissions(): void
{
    foreach (['survey.modsurvey', 'survey.modquestion', 'backup.allsite', 'chat.listrooms', 'authors.liststuff'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
    }
}

it('assigns the super-admin role for radminsuper=1 and grants only real, matching Spatie permissions from the legacy blob', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $blob = serialize(['survey' => ['modsurvey' => 'on', 'modquestion' => 'on'], 'ghost' => ['nope' => 'on']]);
    $admin = makeLegacyAdmin('boss', true, $blob);

    (new SyncLegacyAdminPermissionsAction)->execute();

    $admin = $admin->fresh();
    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and($admin->hasPermissionTo('survey.modsurvey', 'admin'))->toBeTrue()
        ->and($admin->hasPermissionTo('survey.modquestion', 'admin'))->toBeTrue()
        ->and($admin->getAllPermissions()->pluck('name'))->not->toContain('ghost.nope');
});

it('never grants a permission with no matching legacy key — dead/non-migrated capabilities are ignored, never invented', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $blob = serialize(['khotab' => ['someuncrudkey' => 'on']]);
    $admin = makeLegacyAdmin('plain', false, $blob);

    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->getAllPermissions())->toHaveCount(0);
});

it('a plain (radminsuper=0) admin never receives the super-admin role even with a populated permissions blob', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('plain2', false, serialize(['backup' => ['allsite' => 'on']]));

    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($admin->fresh()->hasPermissionTo('backup.allsite', 'admin'))->toBeTrue();
});

it('is idempotent — running twice produces the identical grant set with no errors', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('rerun', true, serialize(['chat' => ['listrooms' => 'on']]));

    $action = new SyncLegacyAdminPermissionsAction;
    $action->execute();
    $first = $admin->fresh()->getAllPermissions()->pluck('name')->sort()->values();
    $action->execute();
    $second = $admin->fresh()->getAllPermissions()->pluck('name')->sort()->values();

    expect($first->all())->toBe($second->all())
        ->and($admin->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('re-syncing after radminsuper flips from 1 to 0 revokes the super-admin role — mirrors legacy losing full access', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('demoted', true, null);
    (new SyncLegacyAdminPermissionsAction)->execute();
    expect($admin->fresh()->hasRole('super-admin'))->toBeTrue();

    $admin->fresh()->forceFill(['radminsuper' => false])->save();
    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('a malformed/corrupt legacy blob does not crash the sync and results in zero grants, not a fatal error', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('corrupt', false, 'not-a-valid-serialized-string{{{');

    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->getAllPermissions())->toHaveCount(0);
});

it('a null/empty legacy blob (serialize(null), the real "N;" shape) results in zero grants without error', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('nograb', false, serialize(null));

    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->getAllPermissions())->toHaveCount(0);
});

it('the raw legacy permissions column is never modified by the sync — read-only, one-directional', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $rawBlob = serialize(['survey' => ['modsurvey' => 'on']]);
    $admin = makeLegacyAdmin('readonly', false, $rawBlob);

    (new SyncLegacyAdminPermissionsAction)->execute();

    expect($admin->fresh()->getRawOriginal('permissions'))->toBe($rawBlob);
});

it('dry-run mode inspects and reports but writes no role/permission changes', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('preview', true, serialize(['survey' => ['modsurvey' => 'on']]));

    $result = (new SyncLegacyAdminPermissionsAction)->execute(dryRun: true);

    expect($result->firstWhere('aid', 'preview')['permissions'])->toBe(['survey.modsurvey'])
        ->and($admin->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($admin->fresh()->getAllPermissions())->toHaveCount(0);
});

it('after sync, dashboard module visibility exactly matches the granted permissions — no more, no less', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('navcheck', false, serialize(['survey' => ['modsurvey' => 'on']]));

    (new SyncLegacyAdminPermissionsAction)->execute();

    $visible = (new AdminDashboardModules)->visibleFor($admin->fresh());
    expect(collect($visible)->pluck('route')->all())->toBe(['admin.survey.index']);
});

it('after sync, the granted permission unlocks its real route (200) and an ungranted permission still 403s', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('routecheck', false, serialize(['survey' => ['modsurvey' => 'on']]));
    (new SyncLegacyAdminPermissionsAction)->execute();

    test()->actingAs($admin->fresh(), 'admin');

    test()->get(route('admin.survey.index'))->assertOk();
    test()->get(route('admin.staff.index'))->assertForbidden();
});

it('after sync, a super-admin sees every currently-implemented dashboard module regardless of the legacy blob contents', function () {
    useInMemoryMainConnectionWithRealPermissionsColumnForSync();
    seedRealPermissions();
    $admin = makeLegacyAdmin('supernav', true, null);

    (new SyncLegacyAdminPermissionsAction)->execute();

    $visible = (new AdminDashboardModules)->visibleFor($admin->fresh());
    expect(count($visible))->toBeGreaterThan(1);
});
