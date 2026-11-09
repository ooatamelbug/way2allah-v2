<?php

/*
 * Anasheed item comment form — page-to-endpoint integration (decision-log #105). The page's own script
 * (`public/scripts/anasheed_scripts.js`) posted to the unrouted Gen 2 URL `add-anasheed-comment-{id}.htm` without a
 * CSRF token; it now posts to the form's `data-comment-url` (`anasheed.item.store-comment`) with the form's token.
 * These tests drive the same request the script builds: URL and token taken from the rendered page.
 * (No browser here — the JavaScript itself is checked statically.)
 */

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
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
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 41, 'title' => 'نشيد للتعليق', 'hidden' => 0]);
});

/** @return array{url: string, token: string} what the script reads: the form's data-comment-url and its _token */
function acfFormContract(string $html): array
{
    preg_match('#<form name="comments_form" id="comments_form"[^>]*data-comment-url="([^"]+)"#', $html, $url);
    preg_match('#id="comments_form".*?<input type="hidden" name="_token" value="([^"]+)"#s', $html, $token);

    return ['url' => html_entity_decode($url[1] ?? ''), 'token' => $token[1] ?? ''];
}

/** Enforce CSRF exactly as in production (the framework skips it while unit tests run). */
function acfEnforceCsrf(): void
{
    app()->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

it('the rendered page points the comment form at the canonical endpoint, carries a CSRF token, and loads the fixed script', function () {
    $html = $this->get('/var-item-41.htm')->assertOk()->getContent();
    $form = acfFormContract($html);

    expect($form['url'])->toBe(route('anasheed.item.store-comment', 41))
        ->and($form['token'])->toBe(session()->token())
        ->and($html)->toContain('/scripts/anasheed_scripts.js?v=')
        ->not->toContain('add-anasheed-comment-');
});

it('the script posts the comment by POST to data-comment-url with the form token, and no longer names the Gen 2 URL', function () {
    $js = file_get_contents(public_path('scripts/anasheed_scripts.js'));
    preg_match('#\$\("\#send_comment"\)\.click\(function \(\)\{.*?\}\);\s*\}\);#s', $js, $handler);

    expect($handler[0] ?? '')->toContain('url: $("#comments_form").data("comment-url")')
        ->toContain('method: "POST"')
        ->toContain('user_nickname : user_nickname, user_comment:user_comment')
        ->toContain('_token: $("#comments_form input[name=\'_token\']").val()')
        ->toContain('if(data == 1)')->toContain('data == 2')->toContain('data == 3')
        ->and($js)->not->toContain('add-anasheed-comment-');
});

it('the request the page builds is accepted with CSRF enforced: "1", the comment stored unmoderated', function () {
    $form = acfFormContract($this->get('/var-item-41.htm')->getContent());
    acfEnforceCsrf();

    $this->post($form['url'], ['user_nickname' => 'زائر', 'user_comment' => 'تعليق من الصفحة', '_token' => $form['token']])
        ->assertOk()->assertSeeText('1');

    $row = DB::connection('main')->table('nuke_anasheed_comments')->where('khid', 41)->first();
    expect($row->uname)->toBe('زائر')->and($row->comment)->toBe('تعليق من الصفحة')->and((int) $row->view)->toBe(0);
});

it('with CSRF enforced, a missing or wrong token is refused (419) and nothing is stored', function (?string $token) {
    $form = acfFormContract($this->get('/var-item-41.htm')->getContent());
    acfEnforceCsrf();

    $payload = ['user_nickname' => 'زائر', 'user_comment' => 'تعليق'];
    if ($token !== null) {
        $payload['_token'] = $token;
    }

    $this->post($form['url'], $payload)->assertStatus(419);
    expect(DB::connection('main')->table('nuke_anasheed_comments')->count())->toBe(0);
})->with([
    'no token (the old script)' => [null],
    'wrong token' => ['not-the-session-token'],
]);

it('validation answers are the codes the script handles: 2 without a nickname, 3 without a comment', function (array $fields, string $code) {
    $form = acfFormContract($this->get('/var-item-41.htm')->getContent());
    acfEnforceCsrf();

    $this->post($form['url'], $fields + ['_token' => $form['token']])->assertOk()->assertSeeText($code);
    expect(DB::connection('main')->table('nuke_anasheed_comments')->count())->toBe(0);
})->with([
    'nickname missing' => [['user_nickname' => '  ', 'user_comment' => 'تعليق'], '2'],
    'comment missing' => [['user_nickname' => 'زائر', 'user_comment' => ''], '3'],
]);

it('the old Gen 2 comment URL stays unrouted (no compatibility route was added)', function () {
    $this->post('/add-anasheed-comment-41.htm', ['user_nickname' => 'زائر', 'user_comment' => 'تعليق'])->assertNotFound();
});
