<?php

use App\Support\Downloads\SaveAttachmentLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * F04 «حفظ المادة» (2026-11-17). Redirect-delivered save links to a host the site operates
 * (`download_save.attachment_query_hosts`) carry `w2a_save=1`, which that host's web-server rule answers with
 * `Content-Disposition: attachment` (verified separately on Apache). Everything else — archive.org, third-party
 * hosts, malformed links — is redirected exactly as before: NOT a guaranteed download, documented as unsupported.
 * PHP never streams these files (no proxy); the Khotab proxy and the capacity guard are unchanged.
 */
beforeEach(function () {
    H::setUp();
    config(['download_save.attachment_query_hosts' => ['vars.way2allah.com']]);
});

// ---------------------------------------------------------------- the marker rule

it('marks only an exact operated host, preserving path, query and fragment', function (string $in, string $out) {
    expect(SaveAttachmentLink::apply($in))->toBe($out);
})->with([
    'plain' => ['https://vars.way2allah.com/cartoon/amin2/clip.mp4', 'https://vars.way2allah.com/cartoon/amin2/clip.mp4?w2a_save=1'],
    'http kept' => ['http://vars.way2allah.com/a.mp3', 'http://vars.way2allah.com/a.mp3?w2a_save=1'],
    'upper-case host' => ['https://VARS.way2allah.com/a.mp4', 'https://VARS.way2allah.com/a.mp4?w2a_save=1'],
    'trailing dot' => ['https://vars.way2allah.com./a.mp4', 'https://vars.way2allah.com./a.mp4?w2a_save=1'],
    'existing query' => ['https://vars.way2allah.com/a.mp4?x=1', 'https://vars.way2allah.com/a.mp4?x=1&w2a_save=1'],
    'fragment' => ['https://vars.way2allah.com/a.mp4#t=10', 'https://vars.way2allah.com/a.mp4?w2a_save=1#t=10'],
    'already marked' => ['https://vars.way2allah.com/a.mp4?w2a_save=1', 'https://vars.way2allah.com/a.mp4?w2a_save=1'],
    'explicit 443' => ['https://vars.way2allah.com:443/a.mp4', 'https://vars.way2allah.com:443/a.mp4?w2a_save=1'],
]);

it('leaves every other link byte-for-byte unchanged (archive.org, third-party, invalid or malicious)', function (string $url) {
    expect(SaveAttachmentLink::apply($url))->toBe($url);
})->with([
    'archive.org video' => ['https://archive.org/download/x/lecture.mp4'],
    'archive.org node' => ['https://ia800204.us.archive.org/5/items/x/a.mp4'],
    'third-party video' => ['https://www.alafasy.me/media/a.mp4'],
    'youtube' => ['https://www.youtube.com/watch?v=abc'],
    'suffix lookalike' => ['https://vars.way2allah.com.evil.example/a.mp4'],
    'prefix lookalike' => ['https://evilvars.way2allah.com/a.mp4'],
    'userinfo trick' => ['https://vars.way2allah.com@evil.example/a.mp4'],
    'backslash trick' => ['https://evil.example\\@vars.way2allah.com/a.mp4'],
    'other port' => ['https://vars.way2allah.com:8443/a.mp4'],
    'javascript' => ['javascript:alert(1)//vars.way2allah.com'],
    'protocol-relative' => ['//vars.way2allah.com/a.mp4'],
    'relative path' => ['/cartoon/amin2/a.mp4'],
    'whitespace' => [' https://vars.way2allah.com/a.mp4'],
    'newline injection' => ["https://vars.way2allah.com/a.mp4\r\nSet-Cookie: x=1"],
    'empty' => [''],
    'ftp' => ['ftp://vars.way2allah.com/a.mp4'],
]);

it('changes nothing at all while no operated host is configured (the default)', function () {
    config(['download_save.attachment_query_hosts' => []]);

    expect(SaveAttachmentLink::apply('https://vars.way2allah.com/a.mp4'))->toBe('https://vars.way2allah.com/a.mp4');
});

it('the config default is empty: listing a host is an explicit step after its server rule is deployed', function () {
    $config = require base_path('config/download_save.php');

    expect($config['attachment_query_hosts'])->toBe([]);
});

// ---------------------------------------------------------------- the routes

function f04Anasheed(int $id, string $link): void
{
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => $id, 'title' => 'مادة', 'group_id' => 1, 'parent_id' => 1,
        'hidden' => 0, 'weight' => 1, 'vedio' => 1, 'mirror' => 0, 'link' => $link, 'downcount' => 0]);
}

it('Variety item: an operated-host file is redirected with the save marker and counted once', function () {
    f04Anasheed(1, 'http://vars.way2allah.com/cartoon/amin2/clip.mp4');

    $this->get('/var-download-1.htm')->assertRedirect('https://vars.way2allah.com/cartoon/amin2/clip.mp4?w2a_save=1');
    expect((int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 1)->value('downcount'))->toBe(1);
});

it('Variety item: an archive.org video is never marked; it now goes to the protected proxy (HEAD: attachment headers, no source contact)', function () {
    f04Anasheed(2, 'http://archive.org/download/x/clip.mp4');

    // F04 proxy expansion (2026-11-17): not a redirect any more. HEAD proves the route without touching archive.org;
    // the GET path is covered by SaveMaterialProxyF04Test against a local fixture server.
    $response = $this->call('HEAD', '/var-download-2.htm')->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('clip.mp4')
        ->and($response->headers->get('Location'))->toBeNull();
});

it('Variety mirror: same rule', function () {
    f04Anasheed(3, 'https://archive.org/download/x/a.mp4');
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 30, 'khid' => 3, 'link' => 'https://vars.way2allah.com/m/a.mp4', 'hidden' => 0, 'hits' => 0]);

    $this->get('/var-mirror-3-30.htm')->assertRedirect('https://vars.way2allah.com/m/a.mp4?w2a_save=1');
});

it('a missing or hidden Variety item is still 404 and nothing is counted', function () {
    f04Anasheed(4, 'https://vars.way2allah.com/a.mp4');
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 4)->update(['hidden' => 1]);

    $this->get('/var-download-4.htm')->assertNotFound();
    $this->get('/var-download-999.htm')->assertNotFound();
    expect((int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 4)->value('downcount'))->toBe(0);
});

it('a lookalike host is never marked as an operated host (it is an ordinary public host for the proxy)', function () {
    f04Anasheed(5, 'https://vars.way2allah.com.evil.example/a.mp4');

    $response = $this->call('HEAD', '/var-download-5.htm')->assertOk();
    expect($response->headers->get('Location'))->toBeNull()
        ->and((string) $response->headers->get('Content-Disposition'))->not->toContain('w2a_save');
});

it('HEAD keeps the same redirect and still does not count', function () {
    f04Anasheed(6, 'https://vars.way2allah.com/a.mp4');

    $this->call('HEAD', '/var-download-6.htm')->assertRedirect('https://vars.way2allah.com/a.mp4?w2a_save=1');
    expect((int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 6)->value('downcount'))->toBe(0);
});

it('chat-room lesson and Fatwa save links follow the same rule; an empty Fatwa link still goes home', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 7, 'author' => 0, 'title' => 'درس', 'hidden' => 0, 'downcount' => 0, 'link' => 'https://vars.way2allah.com/l.mp3']);
    $this->get('/lesson-download-7.htm')->assertRedirect('https://vars.way2allah.com/l.mp3?w2a_save=1');

    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 8, 'media_link' => 'https://vars.way2allah.com/f.mp4', 'num_download' => 0]);
    $this->get('/fatawa-download-8.htm')->assertRedirect('https://vars.way2allah.com/f.mp4?w2a_save=1');

    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 9, 'media_link' => '', 'num_download' => 0]);
    expect($this->get('/fatawa-download-9.htm')->headers->get('Location'))->toBe('/');   // unchanged from before F04
});

it('Telawah follows the same rule; a third-party recitation host goes to the protected proxy', function () {
    InMemoryConnection::setup('main', [
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_telawah_mirror' => MainSchema::nukeTelawahMirror(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
    ]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 1, 'link' => 'https://vars.way2allah.com/q/1.mp3', 'downcount' => 0]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 2, 'link' => 'https://quranrabbi.ghyth.com/1.mp3', 'downcount' => 0]);

    $this->get('/recite-download-1.htm')->assertRedirect('https://vars.way2allah.com/q/1.mp3?w2a_save=1');
    $head = $this->call('HEAD', '/recite-download-2.htm')->assertOk();
    expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')->and($head->headers->get('Location'))->toBeNull();
});

it('the save marker never reaches the player: playback links stay unmarked', function () {
    f04Anasheed(10, 'https://vars.way2allah.com/cartoon/play.mp4');

    $html = $this->get('/var-item-10.htm')->assertOk()->getContent();
    expect($html)->not->toContain('w2a_save')->toContain('/var-download-10.htm');
});
