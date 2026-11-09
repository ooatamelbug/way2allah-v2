<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\AdminRecordConstraint;
use App\Domain\Admin\Support\PermissionLabels;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 0 — the six mirror permissions and the
 * record-scoped authorization extension point.
 *
 * `forceFill()` throughout for AdminUser, per the project's documented
 * `isGuardableColumn` cache-poisoning hazard (see
 * `AdminUserPermissionsCollisionTest`'s docblock).
 */
beforeEach(function () {
    // The shared `nuke_authors` fixture deliberately omits the real
    // `permissions` column (see its own comment, and
    // `AdminUserPermissionsCollisionTest`'s docblock on the
    // `isGuardableColumn` cache-poisoning hazard). The "no legacy import"
    // test below needs a populated blob to prove nothing reads it, so the
    // column is added here and every write uses forceFill().
    InMemoryConnection::setup('main', [
        'nuke_authors' => function (Blueprint $table) {
            (MainSchema::nukeAuthors())($table);
            $table->text('permissions')->nullable();
        },
    ]);

    (new AdminPermissionSeeder)->run();
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
});

function phase0Admin(string $aid, bool $superAdmin = false): AdminUser
{
    $admin = new AdminUser;
    $admin->setConnection('main');
    $admin->forceFill(['aid' => $aid, 'password' => 'x', 'radminsuper' => $superAdmin])->save();

    return $admin->fresh();
}

const PHASE0_MIRROR_PERMISSIONS = [
    'khotab.addmirror',
    'khotab.editmirror',
    'khotab.delmirror',
    'anasheed.addmirror',
    'anasheed.editmirror',
    'anasheed.delmirror',
];

it('seeds exactly the six mirror permissions on the admin guard', function () {
    foreach (PHASE0_MIRROR_PERMISSIONS as $name) {
        expect(Permission::where(['name' => $name, 'guard_name' => 'admin'])->exists())
            ->toBeTrue("missing permission {$name}");
    }
});

it('does not mint a movemirror permission', function () {
    expect(Permission::where('name', 'like', '%movemirror%')->exists())->toBeFalse();
});

it('gives Anasheed its own namespace rather than reusing khotab.*', function () {
    // Phase 3 integration: narrowed from `anasheed.%` to the mirror subset,
    // because Phase 3 legitimately adds `anasheed.addcat` to the namespace.
    // The assertion itself — exactly these three mirror keys, under
    // anasheed.* — is unchanged.
    $anasheed = Permission::where('name', 'like', 'anasheed.%mirror')->pluck('name')->sort()->values()->all();

    expect($anasheed)->toBe([
        'anasheed.addmirror',
        'anasheed.delmirror',
        'anasheed.editmirror',
    ]);
});

it('assigns no mirror permission to anybody automatically', function () {
    $admin = phase0Admin('plain-admin');

    foreach (PHASE0_MIRROR_PERMISSIONS as $name) {
        expect($admin->hasPermissionTo($name, 'admin'))->toBeFalse("{$name} was auto-granted");
    }
});

it('imports no legacy mirror grant from the nuke_authors permissions blob', function () {
    // A real-shaped legacy blob that *does* contain the mirror keys. Phase 0
    // must not read it: plan correction 1 forbids importing legacy grants.
    $admin = phase0Admin('legacy-granted');
    $blob = serialize(['khotab' => ['addmirror' => 'on', 'editmirror' => 'on', 'delmirror' => 'on']]);
    $admin->forceFill(['permissions' => $blob])->save();

    $fresh = $admin->fresh();

    foreach (PHASE0_MIRROR_PERMISSIONS as $name) {
        expect($fresh->hasPermissionTo($name, 'admin'))->toBeFalse("{$name} leaked in from the legacy blob");
    }
});

it('leaves SyncLegacyAdminPermissionsAction unextended for mirror capabilities', function () {
    $source = file_get_contents(base_path('app/Domain/Admin/Actions/SyncLegacyAdminPermissionsAction.php'));

    // Asserts on the capability tokens specifically. A bare 'mirror'
    // substring would false-positive on the word "mirroring" in that
    // class's own docblock, which is unrelated prose.
    foreach (['addmirror', 'editmirror', 'delmirror', 'movemirror'] as $token) {
        expect($source)->not->toContain($token, "the sync action references {$token}");
    }
});

it('reads no legacy moderator grant table anywhere in the Phase 0 code', function () {
    $phase0Files = [
        'app/Domain/Admin/Support/AdminRecordAuthorizer.php',
        'app/Domain/Admin/Support/AdminRecordConstraint.php',
        'app/Domain/Admin/Support/AdminAuditRecorder.php',
        'app/Domain/Admin/Support/LegacyAuditLogCompatibilityWriter.php',
        'app/Domain/Admin/Models/AdminAuditEntry.php',
    ];

    foreach ($phase0Files as $file) {
        $source = file_get_contents(base_path($file));

        foreach (['nuke_islamic_mod', 'nuke_anasheed_mod', 'nuke_telawah_mod', 'nuke_w2acd_mod'] as $table) {
            expect($source)->not->toContain($table, "{$file} references {$table}");
        }
    }
});

it('labels all six permissions, and the anasheed module, for the permission editor', function () {
    foreach (PHASE0_MIRROR_PERMISSIONS as $name) {
        expect(PermissionLabels::permissionLabel($name))->not->toBe($name, "{$name} has no label");
    }

    expect(PermissionLabels::moduleName('anasheed'))->not->toBe('anasheed');
    expect(PermissionLabels::moduleIcon('anasheed'))->not->toBe('icon-settings');
});

it('enforces a mirror permission for a normal admin once granted', function () {
    $admin = phase0Admin('granted-admin');
    $admin->givePermissionTo('khotab.editmirror');

    $authorizer = app(AdminRecordAuthorizer::class);

    expect($authorizer->allows($admin->fresh(), 'khotab.editmirror'))->toBeTrue();
    expect($authorizer->allows($admin->fresh(), 'khotab.delmirror'))->toBeFalse();
    expect($authorizer->allows($admin->fresh(), 'anasheed.editmirror'))->toBeFalse();
});

it('keeps the super-admin bypass intact for records and for bare permissions', function () {
    $superAdmin = phase0Admin('boss', superAdmin: true);
    $superAdmin->assignRole('super-admin');

    $authorizer = app(AdminRecordAuthorizer::class);
    $record = new stdClass;

    foreach (PHASE0_MIRROR_PERMISSIONS as $name) {
        expect($authorizer->allows($superAdmin->fresh(), $name))->toBeTrue();
        expect($authorizer->allows($superAdmin->fresh(), $name, $record))->toBeTrue();
    }
});

it('registers the authorizer as a singleton with a deliberately empty constraint list', function () {
    $authorizer = app(AdminRecordAuthorizer::class);

    expect($authorizer)->toBe(app(AdminRecordAuthorizer::class));
    expect($authorizer->constraints())->toBe([]);
});

it('consults a registered record constraint, and denies when it refuses', function () {
    $admin = phase0Admin('scoped-admin');
    $admin->givePermissionTo('khotab.editmirror');

    $videoOnly = new class implements AdminRecordConstraint
    {
        public function appliesTo(string $permission, object $record): bool
        {
            return str_starts_with($permission, 'khotab.');
        }

        public function permits(AdminUser $admin, string $permission, object $record): bool
        {
            return ($record->vedio ?? 0) === 1;
        }
    };

    $authorizer = new AdminRecordAuthorizer([$videoOnly]);

    $videoRecord = (object) ['vedio' => 1];
    $audioRecord = (object) ['vedio' => 0];

    expect($authorizer->allows($admin->fresh(), 'khotab.editmirror', $videoRecord))->toBeTrue();
    expect($authorizer->allows($admin->fresh(), 'khotab.editmirror', $audioRecord))->toBeFalse();

    // A constraint that does not claim the pair must never deny.
    expect($authorizer->allows($admin->fresh(), 'khotab.editmirror', null))->toBeTrue();
});

it('throws an authorization exception from authorize() when denied', function () {
    $admin = phase0Admin('no-grants');

    app(AdminRecordAuthorizer::class)->authorize($admin, 'khotab.delmirror');
})->throws(AuthorizationException::class);
