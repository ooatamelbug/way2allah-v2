<?php

use App\Domain\Admin\Models\AdminUser;
use App\Http\Controllers\DeploymentInstallerController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * cPanel No-SSH Deployment Repackaging — verifies the browser-driven
 * installer's defense-in-depth gates (disabled-by-default, wrong token,
 * post-success lock) and that a full successful run genuinely performs
 * the real deployment sequence (a real HTTP 200 with every named step
 * reported "ok", and the lock file created).
 *
 * The controller's own real behavior (a real `migrate --force` +
 * `RoleSeeder` + `AdminPermissionSeeder` + cache-command run against real
 * MySQL — at the time also `admin:sync-permissions`, removed from the
 * installer on 2026-10-14, see the last tests below) was already
 * independently, directly verified end-to-end
 * during this package's cPanel smoke test (extracted the built ZIP into
 * a clean directory, ran the equivalent CLI sequence for real against
 * the real local database, confirmed "Synchronized 48 admin accounts.").
 * These tests are deliberately narrower — the installer's *access
 * control* (the part unique to this browser-facing wrapper) — rather
 * than re-proving the underlying Artisan commands work, which is
 * Laravel's/the prior task's own already-covered territory.
 *
 * Deliberately does NOT use `RefreshDatabase` — that trait wraps each
 * test in its own outer transaction, which conflicts with the
 * controller's own nested `Artisan::call('migrate'/'optimize:clear'/...)`
 * calls (confirmed via a real `cannot VACUUM from within a transaction`
 * failure while writing these tests — a SQLite-testing-only concern,
 * not a real production behavior).
 *
 * The lock file lives on the REAL local filesystem (by design — it must
 * survive independently of any test transaction), so every test clears
 * it both before and after itself rather than relying on any framework-
 * provided isolation.
 */
function useTempFileSqliteForInstallerTest(): string
{
    $path = sys_get_temp_dir().'/installer-test-'.uniqid().'.sqlite';
    touch($path);
    config(['database.connections.sqlite.database' => $path]);
    DB::purge('sqlite');

    return $path;
}

function cleanupInstallerTest(?string $tempDb = null): void
{
    Artisan::call('optimize:clear');
    Storage::disk('local')->delete('deployment-installed.lock');
    if ($tempDb !== null) {
        DB::purge('sqlite');
        @unlink($tempDb);
    }
}

function useInMemoryMainConnectionForInstaller(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

beforeEach(function () {
    // Defensive: a previous test erroring out before its own cleanup
    // must never leak a real lock file into the next test.
    Storage::disk('local')->delete('deployment-installed.lock');
});

afterEach(function () {
    cleanupInstallerTest();
});

it('is a 404 when DEPLOY_INSTALLER_ENABLED is not set (the real default)', function () {
    config(['deploy.installer_enabled' => false]);

    $this->get('/deploy/install')->assertNotFound();
    $this->post('/deploy/install', ['token' => 'anything'])->assertNotFound();
});

it('rejects an empty or wrong token without running anything', function () {
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    useInMemoryMainConnectionForInstaller();

    $this->post('/deploy/install', ['token' => ''])->assertOk()->assertSee('رمز التثبيت غير صحيح');
    $this->post('/deploy/install', ['token' => 'wrong-guess'])->assertOk()->assertSee('رمز التثبيت غير صحيح');

    expect(Storage::disk('local')->exists('deployment-installed.lock'))->toBeFalse();
});

it('runs the full real deployment sequence on a correct token and creates the lock file', function () {
    $tempDb = useTempFileSqliteForInstallerTest();
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    useInMemoryMainConnectionForInstaller();

    $response = $this->post('/deploy/install', ['token' => 'the-real-secret']);

    $response->assertOk()->assertSee('اكتمل التثبيت بنجاح')->assertDontSee('❌');
    expect(Storage::disk('local')->exists('deployment-installed.lock'))->toBeTrue();

    cleanupInstallerTest($tempDb);
});

it('is permanently 410 after the lock file exists, even with the correct token, without even checking it', function () {
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    Storage::disk('local')->put('deployment-installed.lock', 'locked');

    $this->get('/deploy/install')->assertStatus(410);
    $this->post('/deploy/install', ['token' => 'the-real-secret'])->assertStatus(410);
});

it('the full sequence is safe to re-run from scratch after being run once already (idempotency)', function () {
    $tempDb = useTempFileSqliteForInstallerTest();
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    useInMemoryMainConnectionForInstaller();

    $this->post('/deploy/install', ['token' => 'the-real-secret'])->assertOk()->assertSee('اكتمل التثبيت بنجاح');
    Storage::disk('local')->delete('deployment-installed.lock'); // simulate "not yet locked" to re-test the sequence itself

    // The first pass's own `config:cache`/`route:cache`/`view:cache` steps
    // each boot a fresh, throwaway Application instance internally
    // (Illuminate\Foundation\Console\ConfigCacheCommand::getFreshConfiguration()
    // et al. `require bootstrap/app.php` directly) — Laravel's container
    // becomes the last one constructed, so every facade call for the rest
    // of THIS PHP process silently resolves through that fresh instance
    // instead of this test's original one, discarding every runtime
    // `config()` override made before it (confirmed directly: `config(
    // 'database.connections.main')` reverts to the real, blank-credential
    // `config/database.php` definition, and the default `sqlite`
    // connection's `database` path reverts to `.env`'s own value, not
    // `$tempDb`). This has no real production consequence — a real
    // browser-driven install request ends immediately after `install()`
    // returns, so there is no "next request" reusing the same PHP
    // process without a fresh bootstrap — it is purely an artifact of
    // this ONE test method issuing 2 requests back to back against the
    // same process. Both connection overrides (not just the 2
    // `deploy.*` keys the app's own gate checks) must be re-established
    // before the second request for it to be a genuine re-run against
    // the SAME already-migrated database, not an accidental fresh one.
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    config(['database.connections.sqlite.database' => $tempDb]);
    DB::purge('sqlite');
    useInMemoryMainConnectionForInstaller();

    $second = $this->post('/deploy/install', ['token' => 'the-real-secret']);
    $second->assertOk()->assertSee('اكتمل التثبيت بنجاح')->assertDontSee('❌');

    cleanupInstallerTest($tempDb);
});

/*
| 2026-10-14 (plan §27) — the installer no longer runs
| `admin:sync-permissions`. That command replaces every admin's Spatie
| grants with the legacy `nuke_authors` state; run on every install, it
| imported legacy assignments and erased grants made in Laravel's
| permission editor. Laravel/Spatie is the authorization authority; the
| command remains for deliberate, manual legacy bootstrap only.
*/

it('runs exactly the deployment steps — migrations, both seeders, the cache commands — and no permission sync', function () {
    $steps = (new ReflectionClassConstant(DeploymentInstallerController::class, 'STEPS'))->getValue();

    expect(array_column($steps, 'key'))->toBe(['db', 'migrate', 'roles', 'permissions', 'optimize_clear', 'config_cache', 'route_cache', 'view_cache'])
        ->and(json_encode($steps, JSON_UNESCAPED_UNICODE))->not->toContain('sync');
});

it('never invokes admin:sync-permissions, while every other step is still called, in order', function () {
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    useInMemoryMainConnectionForInstaller();
    // Spy on the facade the installer calls: records every Artisan::call()
    // without executing it, so the exact invocations can be asserted.
    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $parameters = []) use (&$calls) {
        $calls[] = $command.(isset($parameters['--class']) ? ':'.$parameters['--class'] : '');

        return 0;
    });

    $this->post('/deploy/install', ['token' => 'the-real-secret'])->assertOk()->assertSee('اكتمل التثبيت بنجاح');

    expect($calls)->toBe([
        'migrate',
        'db:seed:Database\\Seeders\\RoleSeeder',
        'db:seed:Database\\Seeders\\AdminPermissionSeeder',
        'optimize:clear',
        'config:cache',
        'route:cache',
        'view:cache',
    ])->and($calls)->not->toContain('admin:sync-permissions');
});

it('a rerun cannot change existing Laravel grants or super-admin memberships through legacy data', function () {
    $tempDb = useTempFileSqliteForInstallerTest();
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);
    useInMemoryMainConnectionForInstaller();
    Schema::connection('main')->table('nuke_authors', fn ($table) => $table->text('permissions')->nullable());

    // An environment already installed once: schema, roles, permission definitions.
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\AdminPermissionSeeder']);

    // A grant made in Laravel's editor that legacy data does not contain.
    $editorGranted = AdminUser::on('main')->create(['aid' => 'editor-granted', 'password' => 'x']);
    $editorGranted->givePermissionTo(['polls.managepolls', 'mobile.managedownloads']);
    // Legacy data that WOULD map to Laravel grants and roles if synced.
    $legacyOnly = AdminUser::on('main')->create(['aid' => 'legacy-only', 'password' => 'x', 'radminsuper' => 1]);
    $blob = serialize(['khotab' => ['addmirror' => 'on', 'addcat' => 'on', 'addadv' => 'on']]);
    DB::connection('main')->table('nuke_authors')->whereIn('aid', ['editor-granted', 'legacy-only'])->update(['permissions' => $blob]);

    $snapshot = fn () => [
        'model_has_permissions' => DB::connection('sqlite')->table('model_has_permissions')->orderBy('model_id')->orderBy('permission_id')->get()->map(fn ($r) => (array) $r)->all(),
        'model_has_roles' => DB::connection('sqlite')->table('model_has_roles')->orderBy('model_id')->orderBy('role_id')->get()->map(fn ($r) => (array) $r)->all(),
    ];
    $before = $snapshot();
    expect($before['model_has_permissions'])->toHaveCount(2)->and($before['model_has_roles'])->toBe([]);

    $this->post('/deploy/install', ['token' => 'the-real-secret'])->assertOk()->assertSee('اكتمل التثبيت بنجاح');

    // config:cache swapped the container mid-request (see the idempotency
    // test above): point the default connection back at this test's DB.
    config(['database.connections.sqlite.database' => $tempDb]);
    DB::purge('sqlite');

    expect($snapshot())->toBe($before);

    cleanupInstallerTest($tempDb);
});
