<?php

use App\Domain\Identity\Guards\VbulletinSessionGuard;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Http\Request;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

/**
 * Uses an in-memory SQLite override of the 'vbulletin' connection so the
 * Guard's logic is fully verifiable without real vBulletin infrastructure
 * (Infrastructure Confirmation #3 is still open) — per Roadmap task 0.3.
 */
function useInMemoryVbulletinConnection(): void
{
    InMemoryConnection::setup('vbulletin', [
        'user' => VbulletinSchema::user(),
        'session' => VbulletinSchema::session(),
    ]);
}

function makeVbulletinRequest(array $cookies = []): Request
{
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.42',
        'HTTP_USER_AGENT' => 'PestTestAgent/1.0',
    ]);

    foreach ($cookies as $name => $value) {
        $request->cookies->set($name, $value);
    }

    return $request;
}

function expectedIdHashForTestRequest(): string
{
    return md5('PestTestAgent/1.0'.'203.0.113');
}

beforeEach(function () {
    useInMemoryVbulletinConnection();
    config(['services.vbulletin.cookie_salt' => 'test-cookie-salt']);
});

it('resolves a user from a valid bb_sessionhash cookie', function () {
    DB::connection('vbulletin')->table('user')->insert(['userid' => 42, 'password' => 'irrelevant-for-this-path']);
    DB::connection('vbulletin')->table('session')->insert([
        'sessionhash' => 'valid-hash',
        'userid' => 42,
        'idhash' => expectedIdHashForTestRequest(),
        'lastactivity' => time() - 60,
    ]);

    $guard = new VbulletinSessionGuard(makeVbulletinRequest(['bb_sessionhash' => 'valid-hash']));

    expect($guard->user())->toBeInstanceOf(VbUser::class)
        ->and($guard->user()->userid)->toBe(42);
});

it('rejects a session cookie whose idhash does not match the requesting user agent/IP', function () {
    DB::connection('vbulletin')->table('user')->insert(['userid' => 42, 'password' => 'x']);
    DB::connection('vbulletin')->table('session')->insert([
        'sessionhash' => 'valid-hash',
        'userid' => 42,
        'idhash' => md5('a-different-agent'.'203.0.113'),
        'lastactivity' => time() - 60,
    ]);

    $guard = new VbulletinSessionGuard(makeVbulletinRequest(['bb_sessionhash' => 'valid-hash']));

    expect($guard->user())->toBeNull();
});

it('rejects a session whose lastactivity exceeds the 900-second legacy TTL', function () {
    DB::connection('vbulletin')->table('user')->insert(['userid' => 42, 'password' => 'x']);
    DB::connection('vbulletin')->table('session')->insert([
        'sessionhash' => 'stale-hash',
        'userid' => 42,
        'idhash' => expectedIdHashForTestRequest(),
        'lastactivity' => time() - 901,
    ]);

    $guard = new VbulletinSessionGuard(makeVbulletinRequest(['bb_sessionhash' => 'stale-hash']));

    expect($guard->user())->toBeNull();
});

it('resolves a user from a valid bb_userid/bb_password cookie pair', function () {
    $storedPassword = 'legacy-stored-password-hash';
    DB::connection('vbulletin')->table('user')->insert(['userid' => 7, 'password' => $storedPassword]);

    $validCookie = md5($storedPassword.'test-cookie-salt');

    $guard = new VbulletinSessionGuard(makeVbulletinRequest([
        'bb_userid' => '7',
        'bb_password' => $validCookie,
    ]));

    expect($guard->user())->toBeInstanceOf(VbUser::class)
        ->and($guard->user()->userid)->toBe(7);
});

it('rejects a bb_userid/bb_password pair whose password cookie does not match', function () {
    DB::connection('vbulletin')->table('user')->insert(['userid' => 7, 'password' => 'legacy-stored-password-hash']);

    $guard = new VbulletinSessionGuard(makeVbulletinRequest([
        'bb_userid' => '7',
        'bb_password' => 'not-the-right-hash',
    ]));

    expect($guard->user())->toBeNull();
});

it('fails closed when VBULLETIN_COOKIE_SALT is not configured, even with an otherwise-valid cookie pair', function () {
    config(['services.vbulletin.cookie_salt' => null]);

    $storedPassword = 'legacy-stored-password-hash';
    DB::connection('vbulletin')->table('user')->insert(['userid' => 7, 'password' => $storedPassword]);

    $guard = new VbulletinSessionGuard(makeVbulletinRequest([
        'bb_userid' => '7',
        'bb_password' => md5($storedPassword.''),
    ]));

    expect($guard->user())->toBeNull();
});

it('resolves to null when no legacy cookies are present at all', function () {
    $guard = new VbulletinSessionGuard(makeVbulletinRequest());

    expect($guard->user())->toBeNull();
});
