<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Guards\AdminGuard;
use App\Domain\Identity\Services\LegacyPasswordVerifier;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Uses an in-memory SQLite override of the 'main' connection so AdminGuard's
 * logic (including the rehash-on-login write) is fully verifiable without
 * real infrastructure — per Roadmap task 0.4.
 */
function useInMemoryMainConnectionForAdmin(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

function makeAdminGuard(): AdminGuard
{
    $request = Request::create('/admincp/index.php', 'POST');
    $session = new Store('admin_test_session', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);

    return new AdminGuard($request, new LegacyPasswordVerifier);
}

beforeEach(function () {
    useInMemoryMainConnectionForAdmin();
});

it('authenticates with an already-bcrypt password and does not rewrite it', function () {
    $bcrypt = password_hash('s3cret', PASSWORD_BCRYPT);
    AdminUser::on('main')->create(['aid' => 'admin1', 'password' => $bcrypt]);

    $guard = makeAdminGuard();

    expect($guard->attempt(['aid' => 'admin1', 'password' => 's3cret']))->toBeTrue()
        ->and($guard->user()->aid)->toBe('admin1');

    expect(DB::connection('main')->table('nuke_authors')->where('aid', 'admin1')->value('password'))
        ->toBe($bcrypt);
});

it('authenticates with a legacy MD5 password and transparently rehashes it to bcrypt', function () {
    AdminUser::on('main')->create(['aid' => 'admin2', 'password' => md5('legacy-pass')]);

    $guard = makeAdminGuard();

    expect($guard->attempt(['aid' => 'admin2', 'password' => 'legacy-pass']))->toBeTrue();

    $stored = DB::connection('main')->table('nuke_authors')->where('aid', 'admin2')->value('password');

    expect(str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$'))->toBeTrue()
        ->and(password_verify('legacy-pass', $stored))->toBeTrue();
});

it('falls back to the pwd column when password is empty, matching legacy priority', function () {
    AdminUser::on('main')->create(['aid' => 'admin3', 'password' => null, 'pwd' => sha1('old-style')]);

    $guard = makeAdminGuard();

    expect($guard->attempt(['aid' => 'admin3', 'password' => 'old-style']))->toBeTrue();
});

it('rejects an incorrect password', function () {
    AdminUser::on('main')->create(['aid' => 'admin4', 'password' => password_hash('correct', PASSWORD_BCRYPT)]);

    $guard = makeAdminGuard();

    expect($guard->attempt(['aid' => 'admin4', 'password' => 'incorrect']))->toBeFalse()
        ->and($guard->user())->toBeNull();
});

it('never accepts a plaintext-stored value, even if it matches exactly', function () {
    // Reproduces what admincp/index.php's now-removed plaintext fallback
    // would have accepted — AdminGuard must reject it (Blueprint §16 item 3).
    AdminUser::on('main')->create(['aid' => 'admin5', 'password' => 'plainpassword']);

    $guard = makeAdminGuard();

    expect($guard->attempt(['aid' => 'admin5', 'password' => 'plainpassword']))->toBeFalse();
});

it('logs out and clears the resolved user', function () {
    AdminUser::on('main')->create(['aid' => 'admin6', 'password' => password_hash('s3cret', PASSWORD_BCRYPT)]);

    $guard = makeAdminGuard();
    $guard->attempt(['aid' => 'admin6', 'password' => 's3cret']);
    expect($guard->check())->toBeTrue();

    $guard->logout();

    expect($guard->check())->toBeFalse()
        ->and($guard->user())->toBeNull();
});
