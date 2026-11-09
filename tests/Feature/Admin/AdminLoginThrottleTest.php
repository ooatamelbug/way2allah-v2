<?php

/*
 * N2 (owner-approved, 2026-11-08) — brute-force throttling on POST /admincp/login: 10 failed
 * credential checks per IP and 5 per username + IP, each per 15 minutes; 429 + Retry-After with
 * the login page and a generic message, the guard not called while throttled; a success clears
 * only the username + IP counter. Password verification, legacy hashes, sessions unchanged.
 */

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminLoginThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

const ALT_IP = '198.51.100.10';
const ALT_OTHER_IP = '198.51.100.20';
const ALT_THROTTLED = 'محاولات دخول كثيرة';

beforeEach(function () {
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
    Cache::flush();
});

function altLogin($test, string $aid, string $password, string $ip = ALT_IP)
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])->post('/admincp/login', ['aid' => $aid, 'password' => $password]);
}

function altFail($test, int $times, string $aid = 'boss', string $ip = ALT_IP): void
{
    foreach (range(1, $times) as $i) {
        altLogin($test, $aid, "wrong-{$i}", $ip)->assertRedirect()->assertSessionHasErrors('aid');
    }
}

function altBoss(string $hash = 'bcrypt'): AdminUser
{
    $password = match ($hash) {
        'md5' => md5('s3cret'),
        'sha1' => sha1('s3cret'),
        default => password_hash('s3cret', PASSWORD_BCRYPT),
    };

    return AdminUser::on('main')->create(['aid' => 'boss', 'password' => $password]);
}

function altLogout($test): void
{
    $test->post('/admincp/logout');
    app('auth')->forgetGuards();
}

// ---------------------------------------------------------------- unchanged login behaviour

it('accepts valid bcrypt, MD5 and SHA1 logins exactly as before (redirect, rehash to bcrypt)', function (string $hash) {
    altBoss($hash);

    altLogin($this, 'boss', 's3cret')->assertRedirect(route('admin.entry'));

    $this->assertAuthenticated('admin');
    expect(AdminUser::on('main')->where('aid', 'boss')->value('password'))->toStartWith('$2y$');
})->with(['bcrypt', 'md5', 'sha1']);

it('keeps session regeneration on login and logout working', function () {
    altBoss();
    $this->get('/admincp/');
    $before = session()->getId();

    altLogin($this, 'boss', 's3cret')->assertRedirect(route('admin.entry'));
    expect(session()->getId())->not->toBe($before);
    $this->get('/admincp/')->assertOk()->assertDontSee('تسجيل الدخول</h3>', false);

    $this->post('/admincp/logout')->assertRedirect(route('admin.entry'));
    $this->assertGuest('admin');
});

// ---------------------------------------------------------------- counting

it('counts failed credentials and unknown usernames against both counters', function () {
    altBoss();
    altLogin($this, 'boss', 'wrong');
    altLogin($this, 'nobody', 'whatever');

    expect(app(RateLimiter::class)->attempts(AdminLoginThrottle::ipKey(ALT_IP)))->toBe(2)
        ->and(app(RateLimiter::class)->attempts(AdminLoginThrottle::usernameIpKey(ALT_IP, 'boss')))->toBe(1)
        ->and(app(RateLimiter::class)->attempts(AdminLoginThrottle::usernameIpKey(ALT_IP, 'nobody')))->toBe(1);
});

it('10 failures across usernames from one IP exhaust the IP quota; the 11th gets 429 + Retry-After', function () {
    foreach (range(1, 10) as $i) {
        altFail($this, 1, "user{$i}");
    }

    $response = altLogin($this, 'someone-new', 'x')->assertStatus(429);

    $retry = (int) $response->headers->get('Retry-After');
    expect($retry)->toBeGreaterThan(0)->toBeLessThanOrEqual(AdminLoginThrottle::DECAY_SECONDS)
        ->and($response->getContent())->toContain(ALT_THROTTLED);
});

it('5 failures for one username + IP exhaust that quota; the 6th gets 429', function () {
    altBoss();
    altFail($this, 5);

    altLogin($this, 'boss', 'wrong-again')->assertStatus(429)->assertHeader('Retry-After');
});

it('never checks a correct password while throttled (the guard is not called)', function () {
    AdminUser::on('main')->create(['aid' => 'boss', 'password' => md5('s3cret')]);
    altFail($this, 5);

    altLogin($this, 'boss', 's3cret')->assertStatus(429);

    $this->assertGuest('admin');
    // A successful attempt() always rehashes MD5 to bcrypt — the hash is untouched.
    expect(AdminUser::on('main')->where('aid', 'boss')->value('password'))->toBe(md5('s3cret'));
});

it('keeps username + IP quotas independent per IP, and shares the IP quota across usernames', function () {
    altBoss();
    altFail($this, 5, 'boss', ALT_IP);

    altLogin($this, 'boss', 's3cret', ALT_OTHER_IP)->assertRedirect(route('admin.entry'));   // other IP: own quota
    altLogout($this);

    altFail($this, 5, 'other', ALT_IP);                                                       // IP total now 10
    altLogin($this, 'third', 'x', ALT_IP)->assertStatus(429);
});

it('treats username case and surrounding whitespace as the same counter', function () {
    altBoss();
    altLogin($this, 'boss', 'w1');
    altLogin($this, 'BOSS', 'w2');
    altLogin($this, '  Boss ', 'w3');
    altLogin($this, 'bOsS', 'w4');
    altLogin($this, 'boss ', 'w5');

    altLogin($this, 'Boss', 's3cret')->assertStatus(429);
    expect(AdminLoginThrottle::normalizeUsername("  BoSS\t"))->toBe('boss');
});

it('does not count CSRF failures', function () {
    altBoss();
    app()->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    foreach (range(1, 12) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => ALT_IP])
            ->post('/admincp/login', ['aid' => 'boss', 'password' => 'wrong', '_token' => 'invalid'])
            ->assertStatus(419);
    }

    expect(app(RateLimiter::class)->attempts(AdminLoginThrottle::ipKey(ALT_IP)))->toBe(0);
});

it('does not count validation failures', function () {
    altBoss();

    foreach (range(1, 12) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => ALT_IP])->post('/admincp/login', ['aid' => 'boss'])->assertSessionHasErrors('password');
    }

    altLogin($this, 'boss', 's3cret')->assertRedirect(route('admin.entry'));
});

it('a successful login clears only the username + IP counter, not the IP counter', function () {
    altBoss();
    altFail($this, 4, 'boss');      // IP 4, boss|IP 4
    altFail($this, 5, 'nobody');    // IP 9

    altLogin($this, 'boss', 's3cret')->assertRedirect(route('admin.entry'));
    altLogout($this);

    $limiter = app(RateLimiter::class);
    expect($limiter->attempts(AdminLoginThrottle::usernameIpKey(ALT_IP, 'boss')))->toBe(0)
        ->and($limiter->attempts(AdminLoginThrottle::ipKey(ALT_IP)))->toBe(9);

    altFail($this, 1, 'boss');                                   // IP 10
    altLogin($this, 'boss', 's3cret')->assertStatus(429);       // IP limit still applies
});

it('lifts the limits after 15 minutes — no permanent lockout', function () {
    altBoss();
    $this->freezeTime();
    altFail($this, 5);
    altLogin($this, 'boss', 's3cret')->assertStatus(429);

    $this->travel(AdminLoginThrottle::DECAY_SECONDS - 5)->seconds();
    altLogin($this, 'boss', 's3cret')->assertStatus(429);

    $this->travel(10)->seconds();
    altLogin($this, 'boss', 's3cret')->assertRedirect(route('admin.entry'));
});

it('ignores client-supplied X-Forwarded-For (framework client IP only)', function () {
    altBoss();

    foreach (range(1, 5) as $i) {
        $this->withHeader('X-Forwarded-For', "203.0.113.{$i}");
        altLogin($this, 'boss', "wrong-{$i}");
    }

    $this->withHeader('X-Forwarded-For', '203.0.113.99');
    altLogin($this, 'boss', 's3cret')->assertStatus(429);
});

// ---------------------------------------------------------------- response privacy

it('gives the same throttled response for an existing and an unknown username', function () {
    altBoss();
    altFail($this, 5, 'boss', ALT_IP);
    altFail($this, 5, 'ghost', ALT_OTHER_IP);

    $existing = altLogin($this, 'boss', 'x', ALT_IP)->assertStatus(429);
    $unknown = altLogin($this, 'ghost', 'x', ALT_OTHER_IP)->assertStatus(429);

    $alert = fn ($r) => preg_match('#<div class="alert alert-danger">\s*<span>(.*?)</span>#s', $r->getContent(), $m) ? trim($m[1]) : null;
    expect($alert($existing))->toBe(AdminLoginThrottle::MESSAGE)->and($alert($unknown))->toBe(AdminLoginThrottle::MESSAGE);
});

it('keeps the username and never the password on throttled and failed responses', function () {
    altBoss();
    altFail($this, 5);

    $response = altLogin($this, 'boss', 'Sup3rSecretValue!')->assertStatus(429);

    expect($response->getContent())->toContain('value="boss"')->not->toContain('Sup3rSecretValue!')
        ->and(session('_old_input.password'))->toBeNull()
        ->and(session('_old_input.aid'))->toBe('boss');
});

it('logs throttling with hashed identifiers only — no username, password or IP', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.' '.json_encode($e->context);
    });
    AdminUser::on('main')->create(['aid' => 'secretadmin', 'password' => md5('Pa55word!')]);
    altFail($this, 5, 'secretadmin');

    altLogin($this, 'secretadmin', 'Pa55word!')->assertStatus(429);

    $log = implode("\n", $logged);
    expect($log)->toContain('admin-login: throttled')
        ->not->toContain('secretadmin')->not->toContain('Pa55word')->not->toContain(ALT_IP)->not->toContain('198.51.100')
        ->and(AdminLoginThrottle::ipKey(ALT_IP))->not->toContain('198.51')
        ->and(AdminLoginThrottle::usernameIpKey(ALT_IP, 'secretadmin'))->not->toContain('secretadmin');
});
