<?php

/*
 * N1 / R14-A (owner-approved, 2026-11-08) — forum cookies through the real HTTP stack.
 *
 * The forum sets its auth cookies unencrypted; Laravel's EncryptCookies used to null them, so
 * VbulletinSessionGuard never saw a forum session over HTTP. These tests send synthetic,
 * unencrypted forum cookies through the full web middleware stack (no `actingAs`), against
 * in-memory forum `session` / `user` tables — no real sessions or credentials.
 */

use App\Http\Middleware\EncryptCookies;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\ContextualAdminHarness;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;
use Tests\Support\MobileHarness;

uses(RefreshDatabase::class);

const FC_IP = '203.0.113.42';
const FC_UA = 'ForumCookieTest/1.0';
const FC_HASH = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const FC_SALT = 'test-cookie-salt';
const MEMBERS_ONLY = 'للأعضاء المسجلين فقط';

function fcForum(): void
{
    InMemoryConnection::setup('vbulletin', [
        'user' => VbulletinSchema::user(),
        'session' => VbulletinSchema::session(),
    ]);
    DB::connection('vbulletin')->table('user')->insert(['userid' => 501, 'username' => 'أبو يحيى', 'password' => 'stored-hash-501']);
}

/** @param array<string, mixed> $override */
function fcSession(array $override = []): void
{
    DB::connection('vbulletin')->table('session')->insert($override + [
        'sessionhash' => FC_HASH,
        'userid' => 501,
        'idhash' => md5(FC_UA.'203.0.113'),
        'host' => FC_IP,                 // vBulletin stores IPADDRESS as session.host (D1)
        'lastactivity' => time() - 60,
    ]);
}

beforeEach(function () {
    MobileHarness::setUp();
    fcForum();
    config(['services.vbulletin.cookie_salt' => FC_SALT]);
    $this->withServerVariables(['REMOTE_ADDR' => FC_IP, 'HTTP_USER_AGENT' => FC_UA]);
});

/** Probe: Mobile's members-only submission page shows the form only to a recognised member. */
function fcIsMember($test): bool
{
    $html = $test->get('/mobiles-admobile.htm')->assertOk()->getContent();

    return ! str_contains($html, MEMBERS_ONLY);
}

// ---------------------------------------------------------------- positive

it('authenticates a valid bb_sessionhash cookie through the real web stack (member-only Mobile page)', function () {
    fcSession();

    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH);
    $html = $this->get('/mobiles-admobile.htm')->assertOk()->getContent();

    expect($html)->not->toContain(MEMBERS_ONLY)->toContain(route('mobile.submit', [], false));
});

it('lets a cookie-authenticated member post a poll comment while anonymous posting is off', function () {
    ContextualAdminHarness::setUp();
    fcForum();
    fcSession();
    DB::connection('main')->table('nuke_config')->update(['anonymous' => 'زائر', 'anonpost' => 0, 'pollcomm' => 1, 'commentlimit' => 4096]);
    $poll = ContextualAdminHarness::insert('nuke_poll_desc', ['pollTitle' => 'ما رأيك؟', 'timeStamp' => 1, 'voters' => 0, 'planguage' => 'arabic', 'artid' => 0, 'comments' => 0], 'pollID');

    // Without the forum cookie: refused (anonpost = 0).
    $this->post(route('engagement.polls.comments.submit', $poll), ['op' => 'ok', 'comment' => 'زائر'])->assertForbidden();

    // The test application is reused between the two requests; the guard built for the first
    // one holds that request. A real request always starts with a fresh application.
    app('auth')->forgetGuards();

    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH)
        ->post(route('engagement.polls.comments.submit', $poll), ['op' => 'ok', 'subject' => 'موضوع', 'comment' => 'تعليق عضو'])
        ->assertRedirect();

    $row = DB::connection('main')->table('nuke_pollcomments')->where('pollID', $poll)->first();
    expect($row)->not->toBeNull()->and($row->name)->toBe('أبو يحيى')->and($row->comment)->toContain('تعليق عضو');
});

it('keeps the guard\'s IP rule: a different last IPv4 octet is the same /24 and still matches', function () {
    fcSession();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200', 'HTTP_USER_AGENT' => FC_UA])
        ->withUnencryptedCookie('bb_sessionhash', FC_HASH);

    expect(fcIsMember($this))->toBeTrue();
});

// ---------------------------------------------------------------- session validation (fail closed)

it('rejects an invalid session hash, a missing session row, userid 0 and a missing forum user', function (array $session, string $cookie) {
    if ($session !== []) {
        fcSession($session);
    }

    $this->withUnencryptedCookie('bb_sessionhash', $cookie);

    expect(fcIsMember($this))->toBeFalse();
})->with([
    'invalid hash' => [[], 'ffffffffffffffffffffffffffffffff'],
    'no session row at all' => [[], FC_HASH],
    'userid 0 (guest session)' => [['userid' => 0], FC_HASH],
    'forum user missing' => [['userid' => 999], FC_HASH],
]);

it('rejects an expired session (900-second window kept)', function (int $age, bool $member) {
    fcSession(['lastactivity' => time() - $age]);
    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH);

    expect(fcIsMember($this))->toBe($member);
})->with([
    'just inside the window' => [880, true],
    'exactly 900 s' => [900, false],
    'long expired' => [5000, false],
]);

it('rejects a wrong user agent and a wrong IP (/24) — the idhash check is kept', function (array $server) {
    fcSession();

    $this->withServerVariables($server + ['REMOTE_ADDR' => FC_IP, 'HTTP_USER_AGENT' => FC_UA])
        ->withUnencryptedCookie('bb_sessionhash', FC_HASH);

    expect(fcIsMember($this))->toBeFalse();
})->with([
    'other user agent' => [['HTTP_USER_AGENT' => 'OtherBrowser/2.0']],
    'other /24 network' => [['REMOTE_ADDR' => '198.51.100.42']],
]);

it('does not touch the forum session row (read-only guard)', function () {
    fcSession(['lastactivity' => $before = time() - 300]);
    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH);

    expect(fcIsMember($this))->toBeTrue()
        ->and((int) DB::connection('vbulletin')->table('session')->value('lastactivity'))->toBe($before);
});

// ---------------------------------------------------------------- remember-me fallback (unchanged)

it('keeps the remember-me fallback: md5(password . salt) accepted, anything else refused', function (array $cookies, bool $member) {
    foreach ($cookies as $name => $value) {
        $this->withUnencryptedCookie($name, $value);
    }

    expect(fcIsMember($this))->toBe($member);
})->with([
    'valid pair' => [['bb_userid' => '501', 'bb_password' => md5('stored-hash-501'.FC_SALT)], true],
    'wrong password cookie' => [['bb_userid' => '501', 'bb_password' => md5('other'.FC_SALT)], false],
    'vB5-style token (unsupported)' => [['bb_userid' => '501', 'bb_password' => 'a-remember-me-token'], false],
    'non-numeric user id' => [['bb_userid' => '501x', 'bb_password' => md5('stored-hash-501'.FC_SALT)], false],
    'unknown user' => [['bb_userid' => '777', 'bb_password' => md5('x'.FC_SALT)], false],
    'password cookie alone' => [['bb_password' => md5('stored-hash-501'.FC_SALT)], false],
]);

it('fails closed when the cookie salt is not configured', function () {
    config(['services.vbulletin.cookie_salt' => null]);
    $this->withUnencryptedCookie('bb_userid', '501')->withUnencryptedCookie('bb_password', md5('stored-hash-501'.FC_SALT));

    expect(fcIsMember($this))->toBeFalse();
});

// ---------------------------------------------------------------- exemption scope

it('uses the replacement EncryptCookies in the web group, exempting exactly the three configured names', function () {
    $web = app(Kernel::class)->getMiddlewareGroups()['web'];
    $mw = app(EncryptCookies::class);

    expect($web)->toContain(EncryptCookies::class)
        ->not->toContain(Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->and($mw->isDisabled('bb_sessionhash'))->toBeTrue()
        ->and($mw->isDisabled('bb_userid'))->toBeTrue()
        ->and($mw->isDisabled('bb_password'))->toBeTrue()
        ->and($mw->isDisabled(config('session.cookie')))->toBeFalse()
        ->and($mw->isDisabled('XSRF-TOKEN'))->toBeFalse()
        ->and($mw->isDisabled('bbsessionhash'))->toBeFalse()
        ->and($mw->isDisabled('bb_cpsession'))->toBeFalse()
        ->and($mw->isDisabled('anything_else'))->toBeFalse();
});

it('still encrypts Laravel\'s own session and XSRF cookies', function () {
    fcSession();
    $response = $this->withUnencryptedCookie('bb_sessionhash', FC_HASH)->get('/mobiles-admobile.htm')->assertOk();

    $session = $response->getCookie(config('session.cookie'), decrypt: false);
    $xsrf = $response->getCookie('XSRF-TOKEN', decrypt: false);

    expect($session)->not->toBeNull()->and($xsrf)->not->toBeNull();
    // The raw values are encrypted payloads, and they decrypt with the app key.
    expect(fn () => Crypt::decryptString($session->getValue()))->not->toThrow(Throwable::class)
        ->and(fn () => Crypt::decryptString($xsrf->getValue()))->not->toThrow(Throwable::class)
        ->and($session->getValue())->not->toBe(Crypt::decryptString($session->getValue()));
    // Laravel never writes the forum cookies.
    expect($response->getCookie('bb_sessionhash', decrypt: false))->toBeNull();
});

it('still discards unrelated unencrypted cookies, while the forum cookie passes through', function () {
    $request = Request::create('/', 'GET', [], ['some_plain_cookie' => 'tampered', 'bb_sessionhash' => FC_HASH, 'bbsessionhash' => FC_HASH]);

    app(EncryptCookies::class)->handle($request, function (Request $r) {
        expect($r->cookie('some_plain_cookie'))->toBeNull()
            ->and($r->cookie('bbsessionhash'))->toBeNull()
            ->and($r->cookie('bb_sessionhash'))->toBe(FC_HASH);

        return response('ok');
    });
});

// ---------------------------------------------------------------- one configurable prefix

it('a configured prefix changes the guard lookup and the exemption together', function () {
    config(['services.vbulletin.cookie_prefix' => 'bb']);
    fcSession();
    $mw = app(EncryptCookies::class);

    expect($mw->isDisabled('bbsessionhash'))->toBeTrue()->and($mw->isDisabled('bbuserid'))->toBeTrue()->and($mw->isDisabled('bbpassword'))->toBeTrue()
        ->and($mw->isDisabled('bb_sessionhash'))->toBeFalse();

    $this->withUnencryptedCookie('bbsessionhash', FC_HASH);
    expect(fcIsMember($this))->toBeTrue();
});

it('does not accept an unconfigured alternative prefix', function (string $prefix, string $cookieName) {
    config(['services.vbulletin.cookie_prefix' => $prefix]);
    fcSession();

    $this->withUnencryptedCookie($cookieName, FC_HASH);

    expect(fcIsMember($this))->toBeFalse();
})->with([
    'default bb_, vB5-style bbsessionhash sent' => ['bb_', 'bbsessionhash'],
    'prefix bb, Gen 2-style bb_sessionhash sent' => ['bb', 'bb_sessionhash'],
    'prefix vb_, bb_sessionhash sent' => ['vb_', 'bb_sessionhash'],
]);

it('an invalid configured prefix exempts nothing and authenticates nobody', function () {
    config(['services.vbulletin.cookie_prefix' => 'bb;evil']);
    fcSession();
    $mw = app(EncryptCookies::class);

    expect($mw->isDisabled('bb;evilsessionhash'))->toBeFalse()->and($mw->isDisabled('bb_sessionhash'))->toBeFalse();

    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH);
    expect(fcIsMember($this))->toBeFalse();
});

// ---------------------------------------------------------------- no logging of cookie values

it('never logs forum cookie values', function () {
    fcSession();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.' '.json_encode($e->context);
    });

    $this->withUnencryptedCookie('bb_sessionhash', FC_HASH)
        ->withUnencryptedCookie('bb_userid', '501')
        ->withUnencryptedCookie('bb_password', $pw = md5('stored-hash-501'.FC_SALT));
    fcIsMember($this);
    $this->withUnencryptedCookie('bb_sessionhash', 'ffffffffffffffffffffffffffffffff');
    fcIsMember($this);

    $all = implode("\n", $logged);
    expect($all)->not->toContain(FC_HASH)->not->toContain($pw)->not->toContain('ffffffffffffffffffffffffffffffff');
});
