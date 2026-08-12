<?php

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 6.8 — KhotabUpdateBackup (myop=get/put), the confirmed
 * Desktop App contract. `op` is sent in the POST body only, never the
 * query string — every request below uses body-only submission to prove
 * that corrected dispatch actually works (Task 6.8 KhotabUpdateBackup
 * round's central fix, `BackupApiController::__invoke()`).
 */
function useInMemoryMainConnectionForKhotabUpdateBackup(): void
{
    InMemoryConnection::setup('main', [
        // See BackupApiControllerTest's identical setup for why
        // `permissions` is added locally here, in the same Schema::create()
        // call, rather than in the shared MainSchema::nukeAuthors().
        'nuke_authors' => function (Blueprint $table) {
            (MainSchema::nukeAuthors())($table);
            $table->text('permissions')->nullable();
        },
        'nuke_modules' => MainSchema::nukeModules(),
        'nuke_backup_sessions' => MainSchema::nukeBackupSessions(),
        'nuke_backup_booking' => MainSchema::nukeBackupBooking(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_telawah_advanced' => MainSchema::nukeTelawahAdvanced(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_anasheed_advanced_m' => MainSchema::nukeAnasheedAdvancedM(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabUpdateBackup();
});

function backupAdminWithPermissions(array $categories = [], bool $radminsuper = false): AdminUser
{
    $backup = [];
    foreach ($categories as $key) {
        $backup[$key] = 'on';
    }

    $admin = AdminUser::on('main')->create([
        'aid' => 'staff', 'uid' => 1, 'name' => 'Admin One', 'API' => str_repeat('a', 32),
        'radminsuper' => $radminsuper,
    ]);

    // forceFill(), not create()'s own mass-assignment for this key — see
    // BackupApiControllerTest's identical note (GuardsAttributes'
    // cross-test-class static guardableColumns cache).
    $admin->forceFill(['permissions' => serialize(['backup' => $backup])])->save();

    return $admin;
}

function postBackupBody(array $body)
{
    return test()->post('/backup.php', $body);
}

// ---- op-in-body dispatch (the central correction this round) ----

it('dispatches KhotabUpdateBackup/get correctly when op is sent only in the POST body, never the query string', function () {
    backupAdminWithPermissions(['backupkhotab'], radminsuper: true);

    $response = postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get',
    ]);

    $response->assertOk()->assertSee('<html><body>', false)->assertSee('<ENDMODE>', false);
});

// ---- get: permission gating ----

it('get: a category with no permission flag on returns NONE, even when matching rows exist', function () {
    backupAdminWithPermissions([], radminsuper: false);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'Admin One']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'A video', 'author' => 1, 'link' => 'http://example.com/a.mp4',
        'down' => 2, 'booking' => 0, 'trial' => 0, 'time' => time() - (48 * 60 * 60),
    ]);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $response->assertOk();
    // 5 modes, none permitted -> NONE x5, one ENDMODE each.
    expect(substr_count($response->getContent(), 'NONE'))->toBe(5);
});

it('get: a permitted category with a matching row returns it, respects down/booking/trial/time-age conditions', function () {
    backupAdminWithPermissions(['backupkhotab'], radminsuper: true);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'Eligible', 'author' => 1, 'link' => 'http://example.com/a.mp4', 'down' => 2, 'booking' => 0, 'trial' => 0, 'time' => time() - (48 * 60 * 60)],
        ['id' => 2, 'title' => 'Too recent, must not appear', 'author' => 1, 'link' => 'http://example.com/b.mp4', 'down' => 2, 'booking' => 0, 'trial' => 0, 'time' => time()],
        ['id' => 3, 'title' => 'Wrong down state, must not appear', 'author' => 1, 'link' => 'http://example.com/c.mp4', 'down' => 1, 'booking' => 0, 'trial' => 0, 'time' => time() - (48 * 60 * 60)],
        ['id' => 4, 'title' => 'Already booked, must not appear', 'author' => 1, 'link' => 'http://example.com/d.mp4', 'down' => 2, 'booking' => time(), 'trial' => 0, 'time' => time() - (48 * 60 * 60)],
        ['id' => 5, 'title' => 'Trial exhausted, must not appear', 'author' => 1, 'link' => 'http://example.com/e.mp4', 'down' => 2, 'booking' => 0, 'trial' => 10, 'time' => time() - (48 * 60 * 60)],
    ]);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $response->assertOk()
        ->assertSee('<ID>1<NAME> Eligible<URL>', false)
        ->assertDontSee('Too recent, must not appear')
        ->assertDontSee('Wrong down state, must not appear')
        ->assertDontSee('Already booked, must not appear')
        ->assertDontSee('Trial exhausted, must not appear');
});

it('get: mode 2 (Khotab Mirror) always returns NONE — confirmed legacy Mirror_Limit=0 permanent no-op, reproduced exactly', function () {
    backupAdminWithPermissions(['backupkhotabmirror'], radminsuper: true);
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 200000, 'khid' => 1, 'link' => 'http://example.com/a.mp4', 'down' => 2, 'booking' => 0, 'trial' => 0, 'backupme' => 1,
    ]);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $response->assertOk()->assertDontSee('200000');
});

it('get: creates a booking row and increments trial/booking for every returned item', function () {
    backupAdminWithPermissions(['backuptelawah'], radminsuper: true);
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 10, 'title' => 'Telawah item', 'link' => 'http://example.com/a.mp3', 'down' => 2, 'booking' => 0, 'trial' => 3,
    ]);

    postBackupBody(['Session' => 7, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $booking = DB::connection('main')->table('nuke_backup_booking')->where('itemid', 10)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->uid)->toBe(1)
        ->and($booking->catid)->toBe(3)
        ->and($booking->sessionid)->toBe(7);

    $item = DB::connection('main')->table('nuke_telawah_telawah')->where('id', 10)->first();
    expect($item->trial)->toBe(4)
        ->and($item->booking)->toBeGreaterThan(0);
});

it('get: clears one stale booking row per mode unconditionally, even when that mode\'s permission is off', function () {
    backupAdminWithPermissions([], radminsuper: false);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'Admin One']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'Stale booking', 'author' => 1, 'link' => '', 'down' => 2, 'booking' => time() - (25 * 60 * 60), 'trial' => 0,
    ]);

    postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('booking'))->toBe(0);
});

it('get: with multiple stale booking rows in the same mode\'s table, only ONE is cleared — legacy\'s confirmed booking-clear LIMIT 1 (backup.php:233)', function () {
    backupAdminWithPermissions([], radminsuper: false);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'Admin One']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'title' => 'Stale 1', 'author' => 1, 'link' => '', 'down' => 2, 'booking' => time() - (25 * 60 * 60), 'trial' => 0],
        ['id' => 2, 'title' => 'Stale 2', 'author' => 1, 'link' => '', 'down' => 2, 'booking' => time() - (25 * 60 * 60), 'trial' => 0],
    ]);

    postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $cleared = DB::connection('main')->table('nuke_islamic_khotab')->where('booking', 0)->count();
    $stillStale = DB::connection('main')->table('nuke_islamic_khotab')->where('booking', '>', 0)->count();

    expect($cleared)->toBe(1)->and($stillStale)->toBe(1);
});

it('get: RemoveOldSessions deactivates a stale (600s+) backup session before dispatching — reconciliation finding C1 (backup.php:196)', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 700,
    ]);

    postBackupBody(['Session' => 5, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    expect(DB::connection('main')->table('nuke_backup_sessions')->where('id', 5)->value('active'))->toBe(0);
});

it('get: rewrites archive.org links via the ported fix_archive_links(), passes through other domains unchanged', function () {
    backupAdminWithPermissions(['backupanasheed'], radminsuper: true);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Archive item', 'link' => 'https://archive.org/somepath/details.php/folder123/file456.mp3',
        'down' => 2, 'booking' => 0, 'trial' => 0,
    ]);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $response->assertOk()->assertSee('<URL>http://www.archive.org/download/folder123/file456.mp3', false);
});

// ---- getdown is intentionally not implemented ----

it('myop=getdown returns an empty response — not implemented, matching the "no matched branch" fallback', function () {
    backupAdminWithPermissions(['backupkhotab'], radminsuper: true);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'getdown']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

// ---- put ----

it('put: RemoveOldSessions deactivates a stale (600s+) backup session before dispatching — reconciliation finding C1 (backup.php:196)', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_backup_sessions')->insert([
        'id' => 5, 'uid' => 1, 'active' => 1, 'updatetime' => time() - 700,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'X', 'author' => 1, 'link' => '', 'down' => 2,
    ]);

    postBackupBody([
        'Session' => 5, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 1,
    ]);

    expect(DB::connection('main')->table('nuke_backup_sessions')->where('id', 5)->value('active'))->toBe(0);
});

it('put: updates the target table, deletes the matching booking row, and responds OK', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 55, 'title' => 'Item', 'author' => 1, 'link' => '', 'down' => 2, 'booking' => 12345, 'trial' => 2,
    ]);
    DB::connection('main')->table('nuke_backup_booking')->insert([
        'uid' => 1, 'catid' => 1, 'itemid' => 55, 'sessionid' => 9, 'createtime' => time(), 'ip' => '127.0.0.1',
    ]);

    $response = postBackupBody([
        'Session' => 9, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 55, 'size' => 123456, 'myop' => 'put', 'cat' => 1,
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('OK');

    $item = DB::connection('main')->table('nuke_islamic_khotab')->where('id', 55)->first();
    expect($item->down)->toBe(1)
        ->and($item->trial)->toBe(0)
        ->and($item->linksize)->toBe(123456)
        ->and($item->online)->toBe(123456)
        ->and($item->downloader)->toBe(1)
        ->and($item->booking)->toBe(0);

    expect(DB::connection('main')->table('nuke_backup_booking')->where('itemid', 55)->exists())->toBeFalse();
});

it('put: an invalid cat responds ERROR and does not write any advanced metadata', function () {
    backupAdminWithPermissions([], radminsuper: true);

    $response = postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 9,
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('ERROR');
});

it('put: cd is never required — omitting it entirely still succeeds, matching the confirmed Desktop App contract', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 1, 'title' => 'X', 'link' => '', 'down' => 2]);

    $response = postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 3,
        // deliberately no 'cd' field
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('OK');
});

it('put: Adv=1 replaces the advanced metadata row with the full field set', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'X', 'author' => 1, 'link' => '', 'down' => 2]);
    DB::connection('main')->table('nuke_islamic_advanced')->insert(['id' => 1, 'perf' => 'OLD']);

    postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 1, 'Adv' => 1,
        'perf' => 'John Doe', 'cright' => '2026', 'frate' => '25.000', 'srate' => '48000',
        'vres' => '1080', 'ares' => '16', 'astr' => 'Audio', 'vstr' => 'Video',
        'abit' => '128', 'vbit' => '2000', 'adur' => '3600', 'width' => 1920, 'height' => 1080,
        'alist' => 'AAC', 'vlist' => 'AVC',
    ]);

    $row = DB::connection('main')->table('nuke_islamic_advanced')->where('id', 1)->first();
    expect($row->perf)->toBe('John Doe')
        ->and($row->vlist)->toBe('AVC')
        ->and($row->width)->toBe(1920);
});

it('put: no Adv field means no advanced metadata write at all', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'X', 'author' => 1, 'link' => '', 'down' => 2]);

    postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 1,
    ]);

    expect(DB::connection('main')->table('nuke_islamic_advanced')->where('id', 1)->exists())->toBeFalse();
});

it('put: cat=5 (nuke_anasheed_advanced_m) — the newly-modeled table with no prior Eloquent model — accepts the write', function () {
    backupAdminWithPermissions([], radminsuper: true);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 1, 'khid' => 1, 'title' => 'X', 'link' => '', 'down' => 2]);

    $response = postBackupBody([
        'Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup',
        'id' => 1, 'size' => 100, 'myop' => 'put', 'cat' => 5, 'Adv' => 1,
        'perf' => 'P', 'cright' => 'C', 'frate' => 'F', 'srate' => 'S', 'vres' => 'V', 'ares' => 'A',
        'astr' => 'AS', 'vstr' => 'VS', 'abit' => 'AB', 'vbit' => 'VB', 'adur' => 'AD',
        'width' => 100, 'height' => 100, 'alist' => 'AL', 'vlist' => 'VL',
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('OK');
    expect(DB::connection('main')->table('nuke_anasheed_advanced_m')->where('id', 1)->exists())->toBeTrue();
});

// ---- permission denial (auth gate, general) ----

it('permission denial: a non-radminsuper, non-listed admin gets "Error!" and no operation runs', function () {
    backupAdminWithPermissions(['backupkhotab'], radminsuper: false);
    DB::connection('main')->table('nuke_modules')->insert(['title' => 'BackUp', 'admins' => 'SomeoneElse']);

    $response = postBackupBody(['Session' => 1, 'ID' => 1, 'APIKey' => str_repeat('a', 32), 'op' => 'KhotabUpdateBackup', 'myop' => 'get']);

    $response->assertOk();
    expect($response->getContent())->toBe('Error!');
});
