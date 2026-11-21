<?php

/*
 * F05-1 (owner-approved, 2026-11-14) — the Khotab item and mirror players apply
 * the same render-time `ArchiveOrgLink::normalize()` the Anasheed branches
 * already use (visitor parity A2): retired storage-node and http archive.org
 * URLs play from `https://archive.org/download/{folder}/{file}`. Stored links,
 * other hosts and file formats are untouched.
 *
 * This fixes the http / retired-node cause only. It does NOT by itself prove the
 * reported medium-quality failure is resolved (that needs the real item and
 * mirror ids), and it cannot make `.rmvb`/`.wmv`/... playable.
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
    ]);
});

function f051Item(string $link, int $vedio = 1, int $id = 1): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => $id, 'author' => 1, 'title' => 'Item', 'vedio' => $vedio, 'hidden' => 0, 'link' => $link,
    ]);
}

function f051Mirror(string $link, int $vedio = 1, int $id = 1): void
{
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => $id, 'khid' => 1, 'comment' => 'جودة متوسطة', 'vedio' => $vedio, 'hidden' => 0, 'link' => $link,
    ]);
}

function f051Play(string $type, int $id = 1): string
{
    return (string) test()->post('/media-player', ['id' => $id, 'type' => $type])->assertOk()->getContent();
}

it('normalises a retired-node http archive.org item link to the canonical https download URL (video mp4)', function () {
    f051Item('http://ia600700.us.archive.org/12/items/w2a_folder/lecture.mp4');

    expect(f051Play('khotab'))
        ->toContain('<video controls autoplay playsinline preload="metadata"><source src="https://archive.org/download/w2a_folder/lecture.mp4" type="video/mp4">')
        ->not->toContain('ia600700');
});

it('normalises an http archive.org /download/ item link (audio mp3)', function () {
    f051Item('http://www.archive.org/download/w2a_folder/lecture.mp3', 0);

    expect(f051Play('khotab'))
        ->toContain('<audio controls autoplay><source src="https://archive.org/download/w2a_folder/lecture.mp3" type="audio/mpeg">');
});

it('normalises a medium-quality mirror on a retired node (the F05 shape)', function () {
    f051Mirror('http://ia311333.us.archive.org/3/items/w2a_folder/lecture_medium.mp4');

    expect(f051Play('khotab_mirror'))
        ->toContain('src="https://archive.org/download/w2a_folder/lecture_medium.mp4"');
});

it('keeps an already-canonical https link, including its query string', function () {
    f051Mirror('https://archive.org/download/w2a_folder/a.mp4?x=1');

    expect(f051Play('khotab_mirror'))->toContain('src="https://archive.org/download/w2a_folder/a.mp4?x=1"');
});

it('does not touch links on other hosts, http or https, nor look-alike hosts', function (string $link) {
    f051Item($link);

    expect(f051Play('khotab'))->toContain('src="'.$link.'"');
})->with([
    'third-party http' => 'http://cdn.example.com/a.mp4',
    'third-party https' => 'https://cdn.example.com/items/folder/a.mp4',
    'look-alike host' => 'http://archive.org.example.com/download/folder/a.mp4',
    'own subdomain' => 'http://vars.way2allah.com/media/a.mp4',
]);

it('does not change formats: an archive.org .rmvb quality still renders no player (F05-2 Part 2: the explanation instead)', function () {
    f051Mirror('http://ia311333.us.archive.org/3/items/w2a_folder/lecture_medium.rmvb');

    expect(f051Play('khotab_mirror'))->toContain('w2a-player-unsupported')
        ->not->toContain('<video')->not->toContain('archive.org');
});

it('leaves YouTube and SoundCloud players unchanged', function () {
    f051Mirror('https://www.youtube.com/watch?v=abcdefghijk', 1, 1);
    f051Mirror('https://soundcloud.com/a/b', 0, 2);

    expect(f051Play('khotab_mirror', 1))->toContain('https://www.youtube.com/embed/abcdefghijk')
        ->and(f051Play('khotab_mirror', 2))->toContain('https://w.soundcloud.com/player/?url=https://soundcloud.com/a/b');
});

it('never rewrites the stored link', function () {
    $item = 'http://ia600700.us.archive.org/12/items/w2a_folder/lecture.mp4';
    $mirror = 'http://ia311333.us.archive.org/3/items/w2a_folder/lecture_medium.mp4';
    f051Item($item);
    f051Mirror($mirror);

    f051Play('khotab');
    f051Play('khotab_mirror');

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('link'))->toBe($item)
        ->and(DB::connection('main')->table('nuke_islamic_mirror')->where('id', 1)->value('link'))->toBe($mirror);
});

it('still refuses hidden items and mirrors', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'x', 'vedio' => 1, 'hidden' => 1, 'link' => 'http://ia1.us.archive.org/1/items/f/a.mp4']);
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 1, 'khid' => 1, 'comment' => 'x', 'vedio' => 1, 'hidden' => 1, 'link' => 'http://ia1.us.archive.org/1/items/f/a.mp4']);

    expect(f051Play('khotab'))->toBe('')->and(f051Play('khotab_mirror'))->toBe('');
});
