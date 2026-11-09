<?php

/*
 * Anasheed item send-to-friend — page-to-endpoint integration (decision-log #106). The page's script posted to the
 * existing route `send-friend-anasheed-{id}.htm` without a CSRF token (419 in production); it now posts to the
 * form's `data-send-url` (`anasheed.item.send-to-friend`) with the form's token. Mail is faked — no real email.
 * (No browser here — the JavaScript itself is checked statically.)
 */

use App\Domain\Content\Mail\AnasheedFriendMail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
        'ips' => MainSchema::ips(),
    ]);
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 3, 'title' => 'قسم']);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 52, 'title' => 'نشيد للإرسال', 'hidden' => 0, 'group_id' => 3]);
    Mail::fake();
});

/** @return array{url: string, token: string} what the script reads from #sendFriend_form */
function asfFormContract(string $html): array
{
    preg_match('#<form name="sendFriend_form" id="sendFriend_form"[^>]*data-send-url="([^"]+)"#', $html, $url);
    preg_match('#id="sendFriend_form".*?<input type="hidden" name="_token" value="([^"]+)"#s', $html, $token);

    return ['url' => html_entity_decode($url[1] ?? ''), 'token' => $token[1] ?? ''];
}

function asfEnforceCsrf(): void
{
    app()->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

function asfValidPayload(string $token): array
{
    return ['your_name' => 'أحمد', 'your_email' => 'ahmed@example.com', 'friend_name' => 'علي', 'friend_email' => 'ali@example.com', '_token' => $token];
}

it('the rendered page exposes the send-to-friend endpoint and a CSRF token on its form, and loads the updated script', function () {
    $html = $this->get('/var-item-52.htm')->assertOk()->getContent();
    $form = asfFormContract($html);

    expect($form['url'])->toBe(route('anasheed.item.send-to-friend', 52))
        ->and($form['token'])->toBe(session()->token())
        ->and($html)->toContain('/scripts/anasheed_scripts.js?v=20261108')
        ->toContain('send-friend-btn');
});

it('the script posts by POST to data-send-url with the form token and handles the 1 / -1 / 2 answers', function () {
    $js = file_get_contents(public_path('scripts/anasheed_scripts.js'));
    preg_match('#function anasheed_send_friend\(\)\{.*?\n\}#s', $js, $fn);

    expect($fn[0] ?? '')->toContain('url: $("#sendFriend_form").data("send-url")')
        ->toContain('method: "POST"')
        ->toContain('your_name : your_name, your_email:your_email, friend_name:friend_name, friend_email:friend_email')
        ->toContain('_token: $("#sendFriend_form input[name=\'_token\']").val()')
        ->toContain('if(data == 1)')->toContain('data == -1')->toContain('data == 2');
});

it('with CSRF enforced, the request built from the page is accepted: "1", exactly one mail to the friend', function () {
    $form = asfFormContract($this->get('/var-item-52.htm')->getContent());
    asfEnforceCsrf();

    $this->post($form['url'], asfValidPayload($form['token']))->assertOk()->assertSeeText('1');

    Mail::assertSent(AnasheedFriendMail::class, 1);
    Mail::assertSent(AnasheedFriendMail::class, fn (AnasheedFriendMail $mail) => $mail->hasTo('ali@example.com')
        && $mail->anasheedItem->id === 52 && $mail->yourName === 'أحمد' && $mail->friendName === 'علي');
});

it('with CSRF enforced, a missing or wrong token is refused (419) and no mail is sent', function (?string $token) {
    $form = asfFormContract($this->get('/var-item-52.htm')->getContent());
    asfEnforceCsrf();

    $payload = asfValidPayload('x');
    if ($token === null) {
        unset($payload['_token']);
    } else {
        $payload['_token'] = $token;
    }

    $this->post($form['url'], $payload)->assertStatus(419);
    Mail::assertNothingSent();
})->with([
    'no token (the old script)' => [null],
    'wrong token' => ['not-the-session-token'],
]);

it('invalid payloads answer "2" — the code the script shows as «يجب عليك اكمال البيانات المطلوبة» — and send nothing', function (array $override) {
    $form = asfFormContract($this->get('/var-item-52.htm')->getContent());
    asfEnforceCsrf();

    $this->post($form['url'], array_merge(asfValidPayload($form['token']), $override))->assertOk()->assertSeeText('2');
    Mail::assertNothingSent();
})->with([
    'your name missing' => [['your_name' => '']],
    'friend name missing' => [['friend_name' => ' ']],
    'your email invalid' => [['your_email' => 'not-an-email']],
    'friend email invalid' => [['friend_email' => 'ali@']],
]);

it('the comment form on the same page is unaffected: its own endpoint and token, still accepted', function () {
    $html = $this->get('/var-item-52.htm')->getContent();
    preg_match('#id="comments_form"[^>]*data-comment-url="([^"]+)"#', $html, $url);
    preg_match('#id="comments_form".*?<input type="hidden" name="_token" value="([^"]+)"#s', $html, $token);
    asfEnforceCsrf();

    $this->post(html_entity_decode($url[1]), ['user_nickname' => 'زائر', 'user_comment' => 'تعليق', '_token' => $token[1]])
        ->assertOk()->assertSeeText('1');
    Mail::assertNothingSent();
});
