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
 * wired. Telawah now uses the same endpoint; chat-room lessons reuse the
 * existing `khotab` type because they are rows from that table.
 */
function useInMemoryMainConnectionForMediaPlayer(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForMediaPlayer();
});

function expectModernVideoPlayer(string $content, string $source): void
{
    expect($content)
        ->toStartWith('<div class="w2a-video-player-wrapper">')
        ->toContain('<video controls autoplay playsinline preload="metadata">')
        ->toContain('src="'.$source.'"');
}

function expectModernAudioPlayer(string $content, string $source, string $title): void
{
    expect($content)
        ->toStartWith('<div class="w2a-audio-player-wrapper">')
        ->toContain('class="w2a-audio-anim-bars"')
        ->toContain('<h4>'.$title.'</h4>')
        ->toContain('<audio controls autoplay>')
        ->toContain('src="'.$source.'"');
}

it('POST /media-player: khotab type, mp4 (video) renders a native <video> tag, matching w2a_mada_play()\'s video+mp4 branch', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://cdn.example.com/a.mp4');
});

it('POST /media-player: khotab type, mp3 renders a native <audio> tag regardless of vedio, matching w2a_mada_play()\'s dual mp3 check', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/a.mp3', 'Item');
});

it('POST /media-player: khotab_mirror type, mp4 renders a native <video> tag, resolving via nuke_islamic_mirror (not nuke_islamic_khotab)', function () {
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'A quality', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/hd.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab_mirror']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://cdn.example.com/hd.mp4');
});

it('POST /media-player: khotab_mirror type, mp3 renders a native <audio> tag', function () {
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => 1, 'khid' => 1, 'comment' => 'A quality', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab_mirror']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/a.mp3', 'A quality');
});

it('POST /media-player: anasheed type, mp4 renders a native <video> tag, resolving via nuke_anasheed_anasheed (var-item-{id}.htm parity)', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://cdn.example.com/a.mp4');
});

it('POST /media-player: anasheed_mirror type, mp3 renders a native <audio> tag, resolving via nuke_anasheed_mirror (var-item-{id}.htm parity)', function () {
    // OLD PlayMirror() plays a mirror only while its item exists with hidden='0' (visitor parity A6).
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'Item', 'vedio' => 0, 'hidden' => 0, 'link' => 'https://cdn.example.com/item.mp3']);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => 1, 'khid' => 1, 'title' => 'A quality', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://cdn.example.com/a.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'anasheed_mirror']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/a.mp3', 'A quality');
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
    expect($response->getContent())
        ->toStartWith('<div class="w2a-video-player-wrapper">')
        ->toContain('src="https://www.youtube.com/embed/ihWoHVmdEpU?autoplay=1"')
        ->toContain('title="Item"');
});

it('POST /media-player: a youtu.be short link (video) strips the prefix for the embed id, matching w2a_mada_play():830-832', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0,
        'link' => 'https://youtu.be/ihWoHVmdEpU',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())
        ->toStartWith('<div class="w2a-video-player-wrapper">')
        ->toContain('src="https://www.youtube.com/embed/ihWoHVmdEpU?autoplay=1"');
});

it('POST /media-player: a soundcloud.com link (audio only) renders a SoundCloud iframe embed, matching w2a_mada_play()\'s audio-branch-only check', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 0, 'hidden' => 0,
        'link' => 'https://soundcloud.com/shiekhahmedgalal/20-8-2014a',
    ]);

    $response = $this->post('/media-player', ['id' => 1, 'type' => 'khotab']);

    $response->assertOk();
    expect($response->getContent())
        ->toStartWith('<div class="w2a-audio-player-wrapper">')
        ->toContain('<h4>Item</h4>')
        ->toContain('src="https://w.soundcloud.com/player/?url=https://soundcloud.com/shiekhahmedgalal/20-8-2014a')
        ->toContain('auto_play=true');
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
    expectModernVideoPlayer($response->getContent(), 'a.mp4');
    expect(DB::connection('main')->table('nuke_islamic_khotab')->count())->toBe(1);
});

// B2 / F05-2 Part 2 (owner-approved Option B, decision-log #144) supersedes the former "null, pending a product
// decision": still never a (faked) player, but the approved explanation. These relative links cannot be served by the
// download route, so no download is offered. Formats outside the approved list (e.g. asf) keep returning null.
it('MediaPlayerService::play(): never renders a player for the obsolete formats (rm/rmvb/wmv/wma, and asf since F07-A #147) — the approved Option B explanation instead', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'RM', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.rm'],
        ['id' => 2, 'author' => 1, 'title' => 'RMVB', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.rmvb'],
        ['id' => 3, 'author' => 1, 'title' => 'WMV', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.wmv'],
        ['id' => 4, 'author' => 1, 'title' => 'WMA audio', 'vedio' => 0, 'hidden' => 0, 'link' => 'a.wma'],
        ['id' => 5, 'author' => 1, 'title' => 'ASF', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.asf'],
    ]);

    $service = app(MediaPlayerService::class);

    foreach ([1, 2, 3, 4, 5] as $id) {
        expect($service->play('khotab', $id))
            ->toContain(MediaPlayerService::UNSUPPORTED_FORMAT_MESSAGE.' '.MediaPlayerService::UNSUPPORTED_FORMAT_NO_DOWNLOAD)
            ->not->toContain('<video')->not->toContain('<audio')->not->toContain('<embed')->not->toContain('<object');
    }
});

it('the legacy literal get-mada-player.htm path remains unrouted', function () {
    $this->post('/get-mada-player.htm', ['id' => 1, 'type' => 'khotab'])->assertNotFound();
});

it('POST /media-player: telawat type resolves a visible recitation as audio', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 71,
        'title' => 'Recitation title',
        'link' => 'https://cdn.example.com/recitation.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 71, 'type' => 'telawat']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/recitation.mp3', 'Recitation title');
});

// ---- fatawa-all-{id}.htm owner-approved answer2.php reconstruction: MediaPlayerService::fromFatwaQuestion() ----

it('POST /media-player: fatawa type, mp4 renders a native <video> tag, resolving by the real nuke_fatwa_questions.id (not legacy\'s page-ordinal position)', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert([
        'id' => 42, 'question_text' => 'Q', 'media_link' => 'https://cdn.example.com/fatwa.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 42, 'type' => 'fatawa']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://cdn.example.com/fatwa.mp4');
});

it('POST /media-player: fatawa type is unconditionally treated as video, matching get_w2a_mada_player()\'s dead media_type if/else (both arms call the same thing)', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert([
        'id' => 43, 'question_text' => 'Q', 'media_type' => 'audio', 'media_link' => 'https://cdn.example.com/fatwa.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 43, 'type' => 'fatawa']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://cdn.example.com/fatwa.mp4');
});

it('POST /media-player: fatawa type, an unresolvable id returns an empty 200 body', function () {
    $response = $this->post('/media-player', ['id' => 999999999, 'type' => 'fatawa']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('resolves modern YouTube URL variants to a clean video id', function (string $link) {
    // The mirror's visible parent item (OLD PlayMirror(), visitor parity A6).
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 17346, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'link' => 'https://cdn.example.com/item.mp4']);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => 19024, 'khid' => 17346, 'title' => 'YouTube quality',
        'vedio' => 1, 'hidden' => 0, 'link' => $link,
    ]);

    $this->post('/media-player', ['id' => 19024, 'type' => 'anasheed_mirror'])
        ->assertOk()
        ->assertSee('https://www.youtube.com/embed/q1-HyMYFoGQ?autoplay=1', false);
})->with([
    'shorts with sharing parameter' => 'https://youtube.com/shorts/q1-HyMYFoGQ?si=share',
    'short URL with time parameter' => 'https://youtu.be/q1-HyMYFoGQ?t=12',
    'http short URL' => 'http://youtu.be/q1-HyMYFoGQ',
    'mobile watch URL' => 'https://m.youtube.com/watch?v=q1-HyMYFoGQ&feature=share',
    'embed URL' => 'https://www.youtube.com/embed/q1-HyMYFoGQ',
    'live URL' => 'https://www.youtube.com/live/q1-HyMYFoGQ',
]);

it('does not render an empty or malformed YouTube embed', function (string $link) {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0, 'link' => $link,
    ]);

    expect($this->post('/media-player', ['id' => 1, 'type' => 'khotab'])->assertOk()->getContent())->toBe('');
})->with([
    'missing id' => 'https://youtube.com/watch',
    'invalid id' => 'https://youtube.com/shorts/not-valid',
    'array query' => 'https://youtube.com/watch?v[]=q1-HyMYFoGQ',
    'lookalike host' => 'https://youtube.example.com/watch?v=q1-HyMYFoGQ',
]);

// ---- Telawah mp4 playback (owner-approved targeted gap, 2026-11-07): fromTelawahItem() follows the row's `vedio`
// flag like Gen 2's get_w2a_mada_player() (audio branch only when vedio = 0), instead of always audio. ----

it('POST /media-player: telawat type, mp4 with vedio=1 renders a native <video> player from the stored link', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 9205, 'title' => 'سورة الفاتحة', 'vedio' => 1,
        'link' => 'https://archive.org/download/yosef-ma3aty-2023/swra_alfatha_.mp4',
    ]);

    $response = $this->post('/media-player', ['id' => 9205, 'type' => 'telawat']);

    $response->assertOk();
    expectModernVideoPlayer($response->getContent(), 'https://archive.org/download/yosef-ma3aty-2023/swra_alfatha_.mp4');
    expect($response->getContent())->not->toContain('<audio')->not->toBe('');
});

it('POST /media-player: telawat type, mp3 with vedio=0 keeps the audio player (no video)', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 72, 'title' => 'Audio recitation', 'vedio' => 0, 'link' => 'https://cdn.example.com/recitation-72.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 72, 'type' => 'telawat']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/recitation-72.mp3', 'Audio recitation');
    expect($response->getContent())->not->toContain('<video');
});

it('POST /media-player: telawat type, mp3 with vedio=1 still uses the audio player (mp3 is checked before the video flag)', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 73, 'title' => 'Flagged video mp3', 'vedio' => 1, 'link' => 'https://cdn.example.com/recitation-73.mp3',
    ]);

    $response = $this->post('/media-player', ['id' => 73, 'type' => 'telawat']);

    $response->assertOk();
    expectModernAudioPlayer($response->getContent(), 'https://cdn.example.com/recitation-73.mp3', 'Flagged video mp3');
    expect($response->getContent())->not->toContain('<video');
});

it('POST /media-player: telawat type, obsolete formats (rm / rmvb) still get no player under decision-log #20, whatever the vedio flag', function (string $link, int $vedio) {
    DB::connection('main')->table('nuke_telawah_telawah')->insert([
        'id' => 74, 'title' => 'Legacy format', 'vedio' => $vedio, 'link' => $link,
    ]);

    $response = $this->post('/media-player', ['id' => 74, 'type' => 'telawat']);

    $response->assertOk();
    expect($response->getContent())->toBe('');
})->with([
    'rm, vedio=0' => ['https://cdn.example.com/recitation.rm', 0],
    'rmvb, vedio=1' => ['https://cdn.example.com/recitation.rmvb', 1],
]);
