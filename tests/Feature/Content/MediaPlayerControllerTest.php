<?php

use App\Domain\Content\Services\MediaPlayerService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Batch 4 (media player, khotab-item-298784.htm investigation) +
 * var-item-{id}.htm parity batch. Replaces `get-mada-player.htm`
 * (`ajax_3K2r.php?op=get-mada-player` → `get_w2a_mada_player()` →
 * `get_w2a_mada()` + `w2a_mada_play()`, `functions.php:794-896`).
 * `khotab`/`khotab_mirror` (Batch 4), `anasheed`/`anasheed_mirror`
 * (var-item-{id}.htm parity batch), and now `fatawa`
 * (`fatawa-all-{id}.htm` owner-approved `answer2.php` reconstruction) are
 * wired; telawah/chat_room remain unwired, per each batch's own approved
 * scope.
 */
function useInMemoryMainConnectionForMediaPlayer(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForMediaPlayer();
});

it('POST /media-player: khotab type, mp4 (video) renders a native <video> tag, matching w2a_mada_play()\'s video+mp4 branch', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="https://cdn.example.com/a.mp4" type="video/mp4"></video>');
});

it('POST /media-player: khotab type, mp3 renders a native <audio> tag regardless of vedio, matching w2a_mada_play()\'s dual mp3 check', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('<audio controls autoplay><source src="https://cdn.example.com/a.mp3" type="audio/mpeg"></audio>');
});

it('POST /media-player: khotab_mirror type, mp4 renders a native <video> tag, resolving via nuke_islamic_mirror (not nuke_islamic_khotab)', function () {
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'A quality', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/hd.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab_mirror']);

    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="https://cdn.example.com/hd.mp4" type="video/mp4"></video>');
});

it('POST /media-player: khotab_mirror type, mp3 renders a native <audio> tag', function () {
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'A quality', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab_mirror']);

    $response->assertOk();
    expect($response->getContent())->toBe('<audio controls autoplay><source src="https://cdn.example.com/a.mp3" type="audio/mpeg"></audio>');
});

it('POST /media-player: anasheed type, mp4 renders a native <video> tag, resolving via nuke_anasheed_anasheed (var-item-{id}.htm parity)', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed']);

    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="https://cdn.example.com/a.mp4" type="video/mp4"></video>');
});

it('POST /media-player: anasheed_mirror type, mp3 renders a native <audio> tag, resolving via nuke_anasheed_mirror (var-item-{id}.htm parity)', function () {
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => 1, 'khid' => 1, 'title' => 'A quality', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed_mirror']);

    $response->assertOk();
    expect($response->getContent())->toBe('<audio controls autoplay><source src="https://cdn.example.com/a.mp3" type="audio/mpeg"></audio>');
});

it('POST /media-player: anasheed type returns empty for a hidden item, same hidden=0 filter as khotab', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 1,
        'link' => 'https://cdn.example.com/a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('POST /media-player: a youtube.com link (video) renders a YouTube iframe embed, extracting the ?v= id, matching w2a_mada_play():826-829', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'http://www.youtube.com/watch?v=ihWoHVmdEpU',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('<div class="embed-responsive embed-responsive-16by9"><iframe src="https://www.youtube.com/embed/ihWoHVmdEpU" frameborder="0" allowfullscreen></iframe></div>');
});

it('POST /media-player: a youtu.be short link (video) strips the prefix for the embed id, matching w2a_mada_play():830-832', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://youtu.be/ihWoHVmdEpU',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('<div class="embed-responsive embed-responsive-16by9"><iframe src="https://www.youtube.com/embed/ihWoHVmdEpU" frameborder="0" allowfullscreen></iframe></div>');
});

it('POST /media-player: a soundcloud.com link (audio only) renders a SoundCloud iframe embed, matching w2a_mada_play()\'s audio-branch-only check', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://soundcloud.com/shiekhahmedgalal/20-8-2014a',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toContain('src="https://w.soundcloud.com/player/?url=https://soundcloud.com/shiekhahmedgalal/20-8-2014a')
        ->and($response->getContent())->toStartWith('<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay"');
});

it('POST /media-player: an invalid/nonexistent id returns an empty 200 body, matching get_w2a_mada_player()\'s own silent-failure contract (confirmed live)', function () {
    $response = $this->post('/media-player', ['id' => 999999999, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('POST /media-player: an unsupported type (e.g. anasheed — not in this batch\'s scope) returns an empty 200 body, not a lookup against the wrong table', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('POST /media-player: missing id or type returns an empty 200 body, matching get_w2a_mada_player()\'s empty($id)||empty($type) early return', function () {
    $missingType = $this->post('/media-player', ['id' => 1]);
    $missingType->assertOk();
    expect($missingType->getContent())->toBe('');

    $missingId = $this->post('/media-player', ['type' => 'khotab']);
    $missingId->assertOk();
    expect($missingId->getContent())->toBe('');

    $empty = $this->post('/media-player');
    $empty->assertOk();
    expect($empty->getContent())->toBe('');
});

it('POST /media-player: a hidden khotab item is never resolved — defensive hidden=0 filter, consistent with KhotabItemController\'s own standing policy', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 1, 'link' => 'a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('POST /media-player: a SQL-injection-shaped id parameter does not execute injected SQL — Eloquent parameterizes by construction, unlike get_w2a_mada()\'s raw string concatenation', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => '1 OR 1=1', 'type' => 'khotab']);

    // The malicious string casts to int 1 (Laravel's `(int)` cast, not raw
    // SQL) — this looks up the real item 1 by its actual numeric id,
    // never executes "OR 1=1" as SQL. Table remains fully intact either way.
    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="a.mp4" type="video/mp4"></video>');
    expect(DB::connection('main')->table('nuke_islamic_khotab')->count())->toBe(1);
});

it('MediaPlayerService::play(): returns null for the confirmed-material-but-deliberately-unimplemented obsolete formats (rm/rmvb/wmv/avi/asf), pending a product decision — not silently faked', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'RM', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.rm'],
        ['id' => 2, 'author' => 1, 'title' => 'RMVB', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.rmvb'],
        ['id' => 3, 'author' => 1, 'title' => 'WMV', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.wmv'],
        ['id' => 4, 'author' => 1, 'title' => 'WMA audio', 'vedio' => 0, 'hidden' => 0, 'link' => 'a.wma'],
    ]);

    $service = app(MediaPlayerService::class);

    foreach ([1, 2, 3, 4] as $id) {
        expect($service->play('khotab', $id))->toBeNull();
    }
});

it('the legacy literal get-mada-player.htm path remains unrouted', function () {
    $this->post('/get-mada-player.htm', ['id' => 1, 'type' => 'khotab'])->assertNotFound();
});

// ---- fatawa-all-{id}.htm owner-approved answer2.php reconstruction: MediaPlayerService::fromFatwaQuestion() ----

it('POST /media-player: fatawa type, mp4 renders a native <video> tag, resolving by the real nuke_fatwa_questions.id (not legacy\'s page-ordinal position)', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert([
        'id' => 42, 'question_text' => 'Q', 'media_link' => 'https://cdn.example.com/fatwa.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 42, 'type' => 'fatawa']);

    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="https://cdn.example.com/fatwa.mp4" type="video/mp4"></video>');
});

it('POST /media-player: fatawa type is unconditionally treated as video, matching get_w2a_mada_player()\'s dead media_type if/else (both arms call the same thing)', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert([
        'id' => 43, 'question_text' => 'Q', 'media_type' => 'audio', 'media_link' => 'https://cdn.example.com/fatwa.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 43, 'type' => 'fatawa']);

    $response->assertOk();
    expect($response->getContent())->toBe('<video controls autoplay><source src="https://cdn.example.com/fatwa.mp4" type="video/mp4"></video>');
});

it('POST /media-player: fatawa type, an unresolvable id returns an empty 200 body', function () {
    $response = $this->post('/media-player', ['id' => 999999999, 'type' => 'fatawa']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});
