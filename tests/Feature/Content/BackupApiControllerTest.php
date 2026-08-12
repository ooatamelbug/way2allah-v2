<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\BackupSession;
use App\Domain\Content\Support\BackupApiAuthenticator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 6.8. `RefreshDatabase` (default connection) backs Spatie's
 * permission tables for the `backupCategoryPermissions()` test, same
 * pattern as AdminStaffControllerTest; `main` is the legacy-shaped tables
 * via InMemoryConnection, same pattern as every other feature test.
 */
uses(RefreshDatabase::class);

function useInMemoryMainConnectionForBackup(): void
{
    InMemoryConnection::setup('main', [
        // `permissions` is added locally, here, rather than in the shared
        // MainSchema::nukeAuthors() — see BackupApiAuthenticator's
        // docblock: that column shadows Spatie's own `permissions()`
        // relation for ANY AdminUser row loaded elsewhere in the suite
        // once it's part of the shared fixture (confirmed by breaking
        // PermissionControllerTest). Confined to this file's own
        // in-memory table only, added in the same Schema::create() call
        // (not a separate later ALTER — a follow-up Schema::table() call
        // was found to be dropped when an earlier test class in the same
        // run wraps connections in a RefreshDatabase transaction).
        'nuke_authors' => function (Blueprint $table) {
            (MainSchema::nukeAuthors())($table);
            $table->text('permissions')->nullable();
        },
        'nuke_modules' => MainSchema::nukeModules(),
        'nuke_backup_sessions' => MainSchema::nukeBackupSessions(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForBackup();
});

function createBackupAdmin(array $overrides = []): AdminUser
{
    return AdminUser::on('main')->create(array_merge([
        'aid' => 'me',
        'uid' => 1,
        'name' => 'Admin One',
        'API' => str_repeat('a', 32),
        'radminsuper' => false,
    ], $overrides));
}

// ---- Auth gate ----

it('rejects an API key that is not exactly 32 characters', function () {
    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => 'short']);

    $response->assertOk()->assertSee('API Error 1:5', false);
});

it('rejects a well-formed key with no matching nuke_authors row', function () {
    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 999, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('API Error 2', false);
});

it('rejects a well-formed key that does not match the stored API column', function () {
    createBackupAdmin(['API' => str_repeat('a', 32)]);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('b', 32)]);

    $response->assertOk()->assertSee('API Error 2', false);
});

it('rejects a valid key/uid when the admin is neither radminsuper nor listed in nuke_modules BackUp admins', function () {
    createBackupAdmin(['name' => 'Nobody', 'radminsuper' => false]);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'SomeoneElse,AnotherOne']);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('Error!', false);
});

it('accepts a valid key/uid listed by exact name in nuke_modules BackUp admins', function () {
    createBackupAdmin(['name' => 'Admin One', 'radminsuper' => false]);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'SomeoneElse,Admin One']);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('<w2abackupspacer>', false);
});

it('radminsuper bypasses the nuke_modules admins-list check entirely, even with an empty admins list', function () {
    createBackupAdmin(['name' => 'Anyone', 'radminsuper' => true]);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => '']);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('<w2abackupspacer>', false);
});

it('backupCategoryPermissions reads the raw legacy nuke_authors.permissions column, matching backup.php:94-104 exactly', function () {
    // Corrected this round (KhotabUpdateBackup implementation): an earlier
    // version of this method read Spatie's admin-panel-sourced backup.*
    // permissions instead — a different, unrelated permission surface
    // from what backup.php itself reads. See BackupApiAuthenticator's
    // own docblock for the full correction note.
    $admin = createBackupAdmin();
    // forceFill(), not update() — Eloquent's mass-assignment guarding
    // caches each model class's "guardable columns" in a static array on
    // first use (GuardsAttributes::isGuardableColumn()), keyed only by
    // class name. If another test class loads AdminUser first against a
    // schema without this locally-added `permissions` column, that stale
    // cache silently drops `permissions` from update()/create() calls
    // here too. forceFill() bypasses guarding entirely, avoiding the
    // cross-test cache dependency.
    $admin->forceFill(['permissions' => serialize(['backup' => ['backupkhotab' => 'on']])])->save();

    $permissions = (new BackupApiAuthenticator)->backupCategoryPermissions($admin);

    expect($permissions['backupkhotab'])->toBeTrue()
        ->and($permissions['backuptelawah'])->toBeFalse();
});

it('backupCategoryPermissions: backupallsite is a confirmed dead flag — being "on" does not imply any other category flag', function () {
    $admin = createBackupAdmin();
    $admin->forceFill(['permissions' => serialize(['backup' => ['backupallsite' => 'on']])])->save();

    $permissions = (new BackupApiAuthenticator)->backupCategoryPermissions($admin);

    expect($permissions['allsite'])->toBeTrue()
        ->and($permissions['backupkhotab'])->toBeFalse();
});

it('backupCategoryPermissions: a missing/unserializable permissions column returns all-false, not an error', function () {
    $admin = createBackupAdmin();

    $permissions = (new BackupApiAuthenticator)->backupCategoryPermissions($admin);

    expect($permissions)->toBe([
        'allsite' => false, 'backupkhotab' => false, 'backupkhotabmirror' => false,
        'backuptelawah' => false, 'backupanasheed' => false, 'backupanasheedmirror' => false,
    ]);
});

// ---- CreateSession ----

it('CreateSession creates a session row and responds "{id}<w2abackupspacer>{aid}"', function () {
    $admin = createBackupAdmin(['aid' => 'staff-42', 'radminsuper' => true]);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk();
    $session = DB::connection('main')->table('nuke_backup_sessions')->first();
    expect($session)->not->toBeNull()
        ->and($session->uid)->toBe(1)
        ->and($response->getContent())->toBe($session->id.'<w2abackupspacer>staff-42');
});

it('CreateSession: confirmed legacy bug reproduced — blocks a new session once ANY existing active session\'s own id exceeds 9 (not a count of 10)', function () {
    $admin = createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 10, 'uid' => 1, 'active' => 1, 'updatetime' => time(),
    ]);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk();
    expect($response->getContent())->toBe('0');
});

it('CreateSession: does NOT block when the existing active session\'s own id is 9 or lower', function () {
    $admin = createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 9, 'uid' => 1, 'active' => 1, 'updatetime' => time(),
    ]);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('<w2abackupspacer>', false);
});

it('CreateSession: RemoveOldSessions deactivates a stale (600s+) session before the limit check runs, so it does not block', function () {
    $admin = createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 10, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 700,
    ]);

    $response = $this->post('/backup.php?op=CreateSession', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk()->assertSee('<w2abackupspacer>', false);
    expect(DB::connection('main')->table('nuke_backup_sessions')->where('id', 10)->value('active'))->toBe(0);
});

// ---- LiveUpdate ----

it('LiveUpdate updates an active session belonging to the admin and responds "OK"', function () {
    $admin = createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 10,
    ]);

    $response = $this->post('/backup.php?op=LiveUpdate', [
        'ID' => 1, 'APIKey' => str_repeat('a', 32), 'Session' => 5,
        // Deliberately included to prove they are NOT read — legacy's own
        // LiveUpdate() parameters are never populated from the request.
        'size' => '99999', 'downloaded' => '50', 'count' => '3', 'speed' => '128', 'itemid' => '77', 'catid' => '1',
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('OK');
});

it('LiveUpdate: intentionally writes empty-string telemetry columns, never reading size/downloaded/count/speed/itemid/catid from the request', function () {
    createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 10,
    ]);

    $this->post('/backup.php?op=LiveUpdate', [
        'ID' => 1, 'APIKey' => str_repeat('a', 32), 'Session' => 5,
        'size' => '99999', 'downloaded' => '50', 'count' => '3', 'speed' => '128', 'itemid' => '77', 'catid' => '1',
    ]);

    $row = DB::connection('main')->table('nuke_backup_sessions')->where('id', 5)->first();
    expect($row->size)->toBe('')
        ->and($row->downloaded)->toBe('')
        ->and($row->count)->toBe('')
        ->and($row->speed)->toBe('')
        ->and($row->itemid)->toBe('')
        ->and($row->catid)->toBe('');
});

it('LiveUpdate responds "Restart" when the session id does not match an active session for this admin', function () {
    createBackupAdmin(['radminsuper' => true]);

    $response = $this->post('/backup.php?op=LiveUpdate', ['ID' => 1, 'APIKey' => str_repeat('a', 32), 'Session' => 999]);

    $response->assertOk();
    expect($response->getContent())->toBe('Restart');
});

it('LiveUpdate responds "Restart" for a session belonging to a different uid', function () {
    createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 999, 'active' => 1, 'updatetime' => time() - 10,
    ]);

    $response = $this->post('/backup.php?op=LiveUpdate', ['ID' => 1, 'APIKey' => str_repeat('a', 32), 'Session' => 5]);

    expect($response->getContent())->toBe('Restart');
});

it('LiveUpdate responds "Restart" for a session that RemoveOldSessions has just deactivated as stale', function () {
    createBackupAdmin(['radminsuper' => true]);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 700,
    ]);

    $response = $this->post('/backup.php?op=LiveUpdate', ['ID' => 1, 'APIKey' => str_repeat('a', 32), 'Session' => 5]);

    expect($response->getContent())->toBe('Restart');
});

// ---- Unimplemented ops ----

it('any op other than CreateSession/LiveUpdate returns an empty 200 response after successful auth, not an error', function () {
    createBackupAdmin(['radminsuper' => true]);

    $response = $this->post('/backup.php?op=KhotabUpdateBackup', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('a missing op also returns an empty 200 response after successful auth', function () {
    createBackupAdmin(['radminsuper' => true]);

    $response = $this->post('/backup.php', ['ID' => 1, 'APIKey' => str_repeat('a', 32)]);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('only POST is registered for /backup.php', function () {
    $this->get('/backup.php')->assertMethodNotAllowed();
});
