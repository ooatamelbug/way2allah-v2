<?php

/*
 * #106 send-to-friend hardening (owner-approved, 2026-11-08) — Khotab, Anasheed and Fatwa:
 * shared IP (3 / rolling 10 min) and recipient (10 / rolling 24 h) quotas, 100 / 254 length
 * limits, fixed site From with the visitor as Reply-To, APP_URL-based mail links, and a
 * controlled response on a mail-transport failure. No real mail is sent: Mail::fake() or the
 * in-memory `array` transport.
 */

use App\Domain\Content\Mail\AnasheedFriendMail;
use App\Domain\Content\Mail\FatwaFriendMail;
use App\Domain\Content\Mail\KhotabFriendMail;
use App\Domain\Content\Services\SendToFriend\SendToFriendRateLimiter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const STF_URLS = [
    'khotab' => '/khotab-item-1/send-friend',
    'anasheed' => '/send-friend-anasheed-1.htm',
    'fatawa' => '/fatawa-friend-sendemail-1.htm',
];

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'A Lesson', 'vedio' => 1, 'hidden' => 0]);
    $db->table('nuke_anasheed_groups')->insert(['id' => 9, 'title' => 'A Group']);
    $db->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'A Nasheed', 'group_id' => 9]);
    $db->table('nuke_fatwa_questions')->insert(['id' => 1, 'question_text' => 'A fatwa question', 'auther_id' => 1]);

    config(['app.url' => 'https://way2allah.com']);
    Cache::flush();
});

/** @param array<string, string> $override */
function stfPayload(array $override = []): array
{
    return $override + [
        'your_name' => 'Ahmed', 'your_email' => 'ahmed@example.com',
        'friend_name' => 'Sami', 'friend_email' => 'sami@example.com',
    ];
}

function stfPost($test, string $module, array $payload = [], string $ip = '198.51.100.1')
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])->post(STF_URLS[$module], stfPayload($payload));
}

function stfSent(): int
{
    return Mail::sent(KhotabFriendMail::class)->count()
        + Mail::sent(AnasheedFriendMail::class)->count()
        + Mail::sent(FatwaFriendMail::class)->count();
}

function stfAssertSent($response, string $module): void
{
    $module === 'fatawa'
        ? $response->assertOk()->assertSee('تم إرسال الرسالة إلى صديقك')
        : $response->assertOk()->assertContent('1');
}

// ================================================================ A. rate limits

it('allows 3 sends per IP in 10 minutes and refuses the 4th with 429 and no mail', function (string $module) {
    Mail::fake();

    foreach (range(1, 3) as $i) {
        stfAssertSent(stfPost($this, $module, ['friend_email' => "friend{$i}@example.com"]), $module);
    }

    $refused = stfPost($this, $module, ['friend_email' => 'friend4@example.com'])->assertStatus(429);

    expect(stfSent())->toBe(3)
        ->and($refused->getContent())->toContain('تم تجاوز الحد المسموح')
        ->and($refused->getContent())->not->toContain('friend4@example.com');
})->with(array_keys(STF_URLS));

it('shares the IP quota across all three modules', function () {
    Mail::fake();

    stfAssertSent(stfPost($this, 'khotab', ['friend_email' => 'a@example.com']), 'khotab');
    stfAssertSent(stfPost($this, 'anasheed', ['friend_email' => 'b@example.com']), 'anasheed');
    stfAssertSent(stfPost($this, 'fatawa', ['friend_email' => 'c@example.com']), 'fatawa');

    foreach (array_keys(STF_URLS) as $module) {
        stfPost($this, $module, ['friend_email' => 'd@example.com'])->assertStatus(429);
    }

    expect(stfSent())->toBe(3);
});

it('ignores client-supplied forwarding headers: the quota follows the connecting IP', function () {
    Mail::fake();

    foreach (range(1, 4) as $i) {
        $this->withHeader('X-Forwarded-For', "203.0.113.{$i}");
        $response = stfPost($this, 'khotab', ['friend_email' => "x{$i}@example.com"]);
        $i <= 3 ? $response->assertOk() : $response->assertStatus(429);
    }
});

it('uses a rolling 10-minute IP window', function () {
    Mail::fake();
    $this->freezeTime();

    stfPost($this, 'khotab', ['friend_email' => 'a@example.com'])->assertOk();           // t = 0
    $this->travel(300)->seconds();
    stfPost($this, 'khotab', ['friend_email' => 'b@example.com'])->assertOk();           // t = 300
    stfPost($this, 'khotab', ['friend_email' => 'c@example.com'])->assertOk();           // t = 300
    stfPost($this, 'khotab', ['friend_email' => 'd@example.com'])->assertStatus(429);    // 3 in window

    $this->travel(301)->seconds();                                                         // t = 601: first one left the window
    stfPost($this, 'khotab', ['friend_email' => 'e@example.com'])->assertOk();
    stfPost($this, 'khotab', ['friend_email' => 'f@example.com'])->assertStatus(429);    // t=300 ×2 still inside

    $this->travel(300)->seconds();                                                         // t = 901: both t=300 left
    stfPost($this, 'khotab', ['friend_email' => 'g@example.com'])->assertOk();
});

it('allows 10 sends to one recipient in 24 hours and refuses the 11th — shared across modules', function () {
    Mail::fake();
    $modules = array_keys(STF_URLS);

    foreach (range(1, 10) as $i) {
        $module = $modules[$i % 3];
        stfAssertSent(stfPost($this, $module, [], "198.51.100.{$i}"), $module);   // distinct IPs: only the recipient quota is in play
    }

    foreach ($modules as $j => $module) {
        stfPost($this, $module, [], '198.51.100.'.(50 + $j))->assertStatus(429);
    }

    expect(stfSent())->toBe(10);

    $this->travel(86401)->seconds();
    stfAssertSent(stfPost($this, 'khotab', [], '198.51.100.99'), 'khotab');
});

it('treats equivalent recipient addresses (spacing, domain case) as one recipient', function () {
    Mail::fake();
    $variants = ['sami@example.com', ' sami@EXAMPLE.com ', 'sami@Example.Com'];

    foreach (range(1, 10) as $i) {
        stfPost($this, 'khotab', ['friend_email' => $variants[$i % 3]], "198.51.100.{$i}")->assertOk();
    }

    stfPost($this, 'anasheed', ['friend_email' => 'sami@EXAMPLE.COM'], '198.51.100.77')->assertStatus(429);
});

it('keeps different recipients apart — including a different local-part case, which is never folded', function () {
    Mail::fake();

    foreach (range(1, 10) as $i) {
        stfPost($this, 'khotab', ['friend_email' => 'sami@example.com'], "198.51.100.{$i}")->assertOk();
    }

    stfPost($this, 'khotab', ['friend_email' => 'sami@example.com'], '198.51.100.70')->assertStatus(429);
    stfPost($this, 'khotab', ['friend_email' => 'other@example.com'], '198.51.100.71')->assertOk();
    stfPost($this, 'khotab', ['friend_email' => 'Sami@example.com'], '198.51.100.72')->assertOk();
});

it('a refusal by one limit does not consume the other quota', function () {
    Mail::fake();

    foreach (range(1, 10) as $i) {
        stfPost($this, 'khotab', ['friend_email' => 'full@example.com'], "198.51.100.{$i}")->assertOk();
    }

    // Recipient full: refused from a fresh IP three times — the IP's quota stays untouched.
    foreach (range(1, 3) as $i) {
        stfPost($this, 'khotab', ['friend_email' => 'full@example.com'], '198.51.100.200')->assertStatus(429);
    }
    foreach (range(1, 3) as $i) {
        stfPost($this, 'khotab', ['friend_email' => "new{$i}@example.com"], '198.51.100.200')->assertOk();
    }
});

it('invalid input consumes no quota and sends no mail', function (string $module) {
    Mail::fake();

    foreach (range(1, 5) as $i) {
        stfPost($this, $module, ['friend_email' => 'not-an-email']);
        stfPost($this, $module, ['your_name' => str_repeat('ا', 101)]);
    }

    expect(stfSent())->toBe(0);
    foreach (range(1, 3) as $i) {
        stfAssertSent(stfPost($this, $module, ['friend_email' => "ok{$i}@example.com"]), $module);
    }
})->with(array_keys(STF_URLS));

it('a mail-transport failure keeps the consumed attempt (no refund)', function () {
    Mail::shouldReceive('to->send')->times(3)->andThrow(new TransportException('smtp down'));

    foreach (range(1, 3) as $i) {
        stfPost($this, 'khotab', ['friend_email' => "f{$i}@example.com"])->assertOk()->assertContent('-1');
    }

    stfPost($this, 'khotab', ['friend_email' => 'f4@example.com'])->assertStatus(429);
});

it('cache keys hold a keyed hash, never the raw address or IP', function () {
    Mail::fake();
    stfPost($this, 'khotab', ['friend_email' => 'Sami@Example.com'])->assertOk();

    $recipientKey = SendToFriendRateLimiter::recipientKey('Sami@Example.com');
    $ipKey = SendToFriendRateLimiter::ipKey('198.51.100.1');

    expect(Cache::has($recipientKey))->toBeTrue()->and(Cache::has($ipKey))->toBeTrue()
        ->and($recipientKey)->toMatch('/^send-to-friend:to:[0-9a-f]{64}$/')
        ->and($recipientKey)->not->toContain('sami')->not->toContain('example')
        ->and($ipKey)->toMatch('/^send-to-friend:ip:[0-9a-f]{64}$/')->not->toContain('198.51')
        ->and(Cache::has('send-to-friend:to:Sami@example.com'))->toBeFalse()
        ->and(SendToFriendRateLimiter::normalizeEmail('  Sami@Example.COM '))->toBe('Sami@example.com');
});

// ================================================================ B. validation

it('rejects over-long names and email addresses, with no mail', function (string $module, array $payload) {
    Mail::fake();

    $response = stfPost($this, $module, $payload);

    $module === 'fatawa' ? $response->assertRedirect()->assertSessionHasErrors() : $response->assertOk()->assertContent('2');
    expect(stfSent())->toBe(0);
})->with(function () {
    $long = str_repeat('a', 243).'@example.com';   // 255 characters
    foreach (array_keys(STF_URLS) as $m) {
        yield "{$m}: your_name 101" => [$m, ['your_name' => str_repeat('ب', 101)]];
        yield "{$m}: friend_name 101" => [$m, ['friend_name' => str_repeat('ب', 101)]];
        yield "{$m}: your_email 255" => [$m, ['your_email' => $long]];
        yield "{$m}: friend_email 255" => [$m, ['friend_email' => $long]];
    }
});

it('accepts names of exactly 100 characters', function (string $module) {
    Mail::fake();

    stfAssertSent(stfPost($this, $module, ['your_name' => str_repeat('ب', 100), 'friend_name' => str_repeat('ج', 100)]), $module);
})->with(array_keys(STF_URLS));

it('keeps the existing required / minimum-length / format rules', function () {
    Mail::fake();

    stfPost($this, 'khotab', ['your_name' => ''])->assertContent('2');
    stfPost($this, 'anasheed', ['friend_email' => 'bad'])->assertContent('2');
    stfPost($this, 'fatawa', ['your_name' => 'A'])->assertSessionHasErrors('your_name');   // Fatwa's 2-character minimum
    stfPost($this, 'anasheed', ['your_name' => 'A'])->assertContent('1');                // Anasheed has no minimum

    expect(stfSent())->toBe(1);
});

// ================================================================ C. email security (array transport, nothing leaves the process)

function stfArrayMessages()
{
    return app('mailer')->getSymfonyTransport()->messages();
}

it('sends From info@way2allah.com with the visitor as Reply-To, whatever the visitor submits', function (string $module) {
    config(['mail.default' => 'array']);

    stfPost($this, $module, [
        'your_name' => "Ahmed\r\nBcc: victim@evil.test",
        'your_email' => 'ahmed@example.com',
        'from' => 'spoof@evil.test', 'reply_to' => 'spoof@evil.test', 'headers' => 'Bcc: victim@evil.test',
    ])->assertOk();

    $messages = stfArrayMessages();
    expect($messages)->toHaveCount(1);
    $email = $messages->first()->getOriginalMessage();

    expect(array_map(fn ($a) => $a->getAddress(), $email->getFrom()))->toBe(['info@way2allah.com'])
        ->and(array_map(fn ($a) => $a->getAddress(), $email->getReplyTo()))->toBe(['ahmed@example.com'])
        ->and(array_map(fn ($a) => $a->getAddress(), $email->getTo()))->toBe(['sami@example.com'])
        ->and($email->getBcc())->toBe([])
        ->and($email->getCc())->toBe([]);

    // CR/LF in the visitor's name cannot open a new header: it stays inside the Reply-To display
    // name (Symfony strips the line breaks), and none of the extra posted fields reach the mail.
    $headers = $email->getHeaders()->toString();
    expect($headers)->not->toMatch('/^Bcc:/mi')->not->toMatch('/^Cc:/mi')
        ->not->toContain('spoof@evil.test')
        ->and(preg_match_all('/^Reply-To:/mi', $headers))->toBe(1)
        ->and(preg_match_all('/^From:/mi', $headers))->toBe(1);
})->with(array_keys(STF_URLS));

it('builds mail links from APP_URL even when the request Host differs', function (string $module, string $path) {
    config(['mail.default' => 'array']);

    $this->withHeader('Host', 'attacker.example');
    stfPost($this, $module)->assertOk();

    $body = (string) stfArrayMessages()->first()->getOriginalMessage()->getHtmlBody();

    expect($body)->toContain('href="https://way2allah.com'.$path.'"')
        ->and($body)->not->toContain('attacker.example')
        ->and($body)->not->toContain('http://localhost');
})->with([
    'khotab item' => ['khotab', '/khotab-item-1.htm'],
    'anasheed item' => ['anasheed', '/var-item-1.htm'],
    'anasheed group' => ['anasheed', '/var-group-9.htm'],
    'fatwa' => ['fatawa', '/fatawa-1.htm'],
    'fatwa footer link' => ['fatawa', '/khotab-audio.htm'],
]);

it('keeps the existing mail content: subjects, names and titles', function () {
    config(['mail.default' => 'array']);

    stfPost($this, 'khotab')->assertOk();
    stfPost($this, 'anasheed')->assertOk();
    stfPost($this, 'fatawa')->assertOk();

    [$k, $a, $f] = stfArrayMessages()->map(fn ($m) => $m->getOriginalMessage())->all();

    expect($k->getSubject())->toBe('A Lesson - موقع الطريق الى الله')
        ->and($a->getSubject())->toBe('A Nasheed - موقع الطريق الى الله')
        ->and($f->getSubject())->toContain('فتوى A fatwa question')
        ->and($k->getHtmlBody())->toContain('Sami')->toContain('Ahmed')->toContain('A Lesson')
        ->and($f->getHtmlBody())->toContain('A fatwa question');
});

// ================================================================ D. failure handling

it('answers a mail-transport failure with a controlled response, never success, and logs no mail data', function (string $module) {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->level.' '.$e->message.' '.json_encode($e->context);
    });
    Mail::shouldReceive('to->send')->once()->andThrow(new TransportException('550 relay denied for sami@example.com by smtp.secret-host:465 pass=hunter2'));

    $response = stfPost($this, $module);

    if ($module === 'fatawa') {
        $response->assertStatus(503)->assertSee('حدث خطأ اثناء ارسال الرسالة')->assertDontSee('تم إرسال الرسالة إلى صديقك');
    } else {
        $response->assertOk()->assertContent('-1');
    }

    $log = implode("\n", $logged);
    expect($log)->toContain('send-to-friend: mail transport failure')->toContain($module)
        ->not->toContain('sami@example.com')->not->toContain('ahmed@example.com')
        ->not->toContain('secret-host')->not->toContain('hunter2')->not->toContain('relay denied');
})->with(array_keys(STF_URLS));

it('does not swallow non-transport errors', function () {
    Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('programming error'));

    stfPost($this, 'khotab')->assertStatus(500);
});

// ================================================================ E. regression

it('keeps the route contracts: POST only, same names and paths', function () {
    foreach (['khotab.item.send-friend' => '/khotab-item-1/send-friend', 'anasheed.item.send-to-friend' => '/send-friend-anasheed-1.htm', 'fatawa.question.send-to-friend' => '/fatawa-friend-sendemail-1.htm'] as $name => $path) {
        $id = str_contains($name, 'khotab') ? ['khotab' => 1] : (str_contains($name, 'anasheed') ? ['anasheed' => 1] : ['question' => 1]);
        expect(route($name, $id, false))->toBe($path);
        $this->get($path)->assertStatus(405);
    }
});

it('still requires a valid CSRF token when CSRF is enforced, without consuming quota', function () {
    Mail::fake();
    // Laravel skips the CSRF check in unit tests; enforce it here (same technique as
    // AnasheedSendToFriendIntegrationTest).
    app()->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    foreach (range(1, 5) as $i) {
        stfPost($this, 'khotab', ['_token' => 'wrong'])->assertStatus(419);
        stfPost($this, 'fatawa', ['_token' => 'wrong'])->assertStatus(419);
    }

    expect(stfSent())->toBe(0)
        ->and(Cache::has(SendToFriendRateLimiter::ipKey('198.51.100.1')))->toBeFalse();
});
