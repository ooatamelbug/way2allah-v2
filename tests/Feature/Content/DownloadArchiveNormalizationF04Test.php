<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;
use Tests\Support\ProxiedDownloadProbe;

uses(RefreshDatabase::class);

/**
 * F04 fix (owner-approved 2026-11-17, functional verification): the Khotab item / mirror, Telawah and lesson download
 * routes now hand the proxy the SAME normalised archive.org URL the player already uses (ArchiveOrgLink). A link
 * pinned to a retired storage node (`ia….us.archive.org/N/items/{folder}/{file}`) answered 500 as stored; archive.org's
 * own `/download/{folder}/{file}` serves it. Every other link — other hosts, `/details/` pages, local files — is
 * unchanged, and the stored value is never modified. The link the proxy would fetch is read with the pool full
 * (ProxiedDownloadProbe), so no real host is contacted.
 */
beforeEach(function () {
    H::setUp();
});

const F04N_PINNED = 'http://ia600204.us.archive.org/5/items/al-hekmah-tv-1/hakekah-1.WMV';
const F04N_CANONICAL = 'https://archive.org/download/al-hekmah-tv-1/hakekah-1.WMV';

function f04nKhotab(int $id, string $link, array $extra = []): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert(array_merge(['id' => $id, 'author' => 0, 'title' => 'مادة', 'hidden' => 0, 'downcount' => 0, 'link' => $link], $extra));
}

it('Khotab item: a pinned storage-node link is downloaded from the canonical archive.org URL; the stored link is unchanged', function () {
    f04nKhotab(1, F04N_PINNED);

    expect(ProxiedDownloadProbe::resolvedLink('/khotab-download-1.htm'))->toBe(F04N_CANONICAL)
        ->and(DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->value('link'))->toBe(F04N_PINNED);
});

it('Khotab mirror (the verified failing case shape, mirror 5 of item 2432): normalised; stored link unchanged', function () {
    f04nKhotab(2432, 'https://archive.org/download/main/main.mp4');
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 5, 'khid' => 2432, 'link' => F04N_PINNED, 'hits' => 0]);

    expect(ProxiedDownloadProbe::resolvedLink('/khotab-mirror-2432-5.htm'))->toBe(F04N_CANONICAL)
        ->and(DB::connection('main')->table('nuke_islamic_mirror')->where('id', 5)->value('link'))->toBe(F04N_PINNED);
});

it('Telawah and lessons: pinned links normalised the same way', function () {
    f04nKhotab(30, 'http://ia310821.us.archive.org/0/items/audio_02/lesson.mp3', ['location_id' => 10]);
    expect(ProxiedDownloadProbe::resolvedLink('/lesson-download-30.htm'))->toBe('https://archive.org/download/audio_02/lesson.mp3');

    InMemoryConnection::setup('main', [
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
    ]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 4, 'link' => 'http://ia341224.us.archive.org/2/items/quran-x/001.mp3', 'downcount' => 0]);
    expect(ProxiedDownloadProbe::resolvedLink('/recite-download-4.htm'))->toBe('https://archive.org/download/quran-x/001.mp3')
        ->and(DB::connection('main')->table('nuke_telawah_telawah')->where('id', 4)->value('link'))->toBe('http://ia341224.us.archive.org/2/items/quran-x/001.mp3');
});

it('the /download/ form is canonicalised to https archive.org; links the rules do not cover are passed unchanged', function (string $stored, string $expected) {
    f04nKhotab(7, $stored);

    expect(ProxiedDownloadProbe::resolvedLink('/khotab-download-7.htm'))->toBe($expected);
})->with([
    'http www /download/' => ['http://www.archive.org/download/f/a.mp4', 'https://archive.org/download/f/a.mp4'],
    'already canonical' => ['https://archive.org/download/f/a.mp4', 'https://archive.org/download/f/a.mp4'],
    'details page (not a file)' => ['https://archive.org/details/f', 'https://archive.org/details/f'],
    'other host' => ['https://example.com/media/a.mp4', 'https://example.com/media/a.mp4'],
    'other host, http' => ['http://example.com/a.mp3', 'http://example.com/a.mp3'],
]);

it('HEAD stays header-only: attachment with the file name, no slot, no count', function () {
    f04nKhotab(8, F04N_PINNED);

    $head = $this->call('HEAD', '/khotab-download-8.htm')->assertOk();

    expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('hakekah-1.WMV')
        ->and((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', 8)->value('downcount'))->toBe(0);
});

it('YouTube / SoundCloud mirrors keep their redirect (not touched by normalisation)', function () {
    f04nKhotab(9, 'https://archive.org/download/f/a.mp4');
    DB::connection('main')->table('nuke_islamic_mirror')->insert(['id' => 90, 'khid' => 9, 'link' => 'http://youtu.be/abc', 'hits' => 0]);

    $this->get('/khotab-mirror-9-90.htm')->assertRedirect('https://youtu.be/abc');
});

it('a local Khotab file still streams as before (normalisation leaves non-URLs alone)', function () {
    $file = tempnam(sys_get_temp_dir(), 'f04n');
    file_put_contents($file, 'local-bytes');
    f04nKhotab(10, $file);

    try {
        $response = $this->get('/khotab-download-10.htm')->assertOk();
        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
            ->and($response->streamedContent())->toBe('local-bytes');
    } finally {
        @unlink($file);
    }
});
