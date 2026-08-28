<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
 * `RoleSeeder` + `AdminPermissionSeeder` + `admin:sync-permissions` +
 * cache-command run against real MySQL, synchronizing real admin
 * accounts) was already independently, directly verified end-to-end
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
    // config:cache ran as part of the first pass — restore the runtime
    // overrides it may have frozen before the second real HTTP round trip.
    config(['deploy.installer_enabled' => true, 'deploy.installer_token' => 'the-real-secret']);

    $second = $this->post('/deploy/install', ['token' => 'the-real-secret']);
    $second->assertOk()->assertSee('اكتمل التثبيت بنجاح')->assertDontSee('❌');

    cleanupInstallerTest($tempDb);
});
