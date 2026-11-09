<?php

/*
 * Visitor parity A2 (owner, 2026-11-04) — OLD `fix_archive_links()`, made safe. The samples are
 * the stored shapes verified against archive.org on 2026-11-04 (decision-log #87).
 */

use App\Support\Media\ArchiveOrgLink;

it('normalises archive.org storage-node links to the canonical https download URL', function (string $stored, string $canonical) {
    expect(ArchiveOrgLink::normalize($stored))->toBe($canonical);
})->with([
    'retired ia node, http (item 3)' => [
        'http://ia331419.us.archive.org/3/items/way2allah_islamics505/3_4_abt-nfsa.rmvb',
        'https://archive.org/download/way2allah_islamics505/3_4_abt-nfsa.rmvb',
    ],
    'ia node, https (item 17327)' => [
        'https://ia601301.us.archive.org/23/items/tghyan_alnfs/meqat_almo7been.mp4',
        'https://archive.org/download/tghyan_alnfs/meqat_almo7been.mp4',
    ],
    'dn node (item 17346)' => [
        'https://dn721605.ca.archive.org/0/items/en_lm_tst3_alesla7/mshhd_mheb.mp4',
        'https://archive.org/download/en_lm_tst3_alesla7/mshhd_mheb.mp4',
    ],
    'percent-encoded file name kept as stored (mirror 15559)' => [
        'https://ia601509.us.archive.org/21/items/osamaalgamal_yahoo_201511/%D8%A5%D8%B3%D9%84%D8%A7%D9%85.pdf',
        'https://archive.org/download/osamaalgamal_yahoo_201511/%D8%A5%D8%B3%D9%84%D8%A7%D9%85.pdf',
    ],
    'nested file path keeps the item folder' => [
        'http://ia800300.us.archive.org/1/items/folder/sub/file.mp3',
        'https://archive.org/download/folder/sub/file.mp3',
    ],
]);

it('normalises download links (www and bare, http and https) to the same canonical form', function (string $stored) {
    expect(ArchiveOrgLink::normalize($stored))->toBe('https://archive.org/download/elrafk2/Rahma.mp3');
})->with([
    'http://www.archive.org/download/elrafk2/Rahma.mp3',
    'https://www.archive.org/download/elrafk2/Rahma.mp3',
    'http://archive.org/download/elrafk2/Rahma.mp3',
    'https://archive.org/download/elrafk2/Rahma.mp3',
]);

it('leaves a details-page link unchanged — OLD\'s blind rewrite made it a 404 (mirror 8493)', function () {
    $details = 'http://archive.org/details/Han3alekRasolEllahUshtam.flv';

    expect(ArchiveOrgLink::normalize($details))->toBe($details);
});

it('leaves malformed archive.org paths unchanged', function (string $stored) {
    expect(ArchiveOrgLink::normalize($stored))->toBe($stored);
})->with([
    'no file after the folder' => 'https://ia800300.us.archive.org/1/items/folder',
    'no folder at all' => 'https://archive.org/download/',
    'no recognised segment' => 'https://archive.org/stream/folder/file.mp3',
    'host only' => 'https://archive.org',
]);

it('leaves unrelated and look-alike hosts unchanged', function (string $stored) {
    expect(ArchiveOrgLink::normalize($stored))->toBe($stored);
})->with([
    'cdn' => 'https://cdn.example.com/items/folder/a.mp3',
    'youtube' => 'https://www.youtube.com/watch?v=q1-HyMYFoGQ',
    'look-alike host' => 'https://notarchive.org/download/folder/a.mp3',
    'archive.org as a subdomain of another host' => 'https://archive.org.evil.example/download/folder/a.mp3',
    'relative path' => '/media/anasheed/a.mp3',
    'empty' => '',
]);
