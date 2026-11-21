<?php

/*
 * Khotab feedback (2026-11-18) — "Unsupported Medium Quality Playback". On the Khotab item page a mirror card whose link
 * is in a format browsers cannot play (BrowserPlaybackFormat: RM / RMVB / WMV …) no longer offers «مشاهدة» (it only
 * ever opened the "unsupported format" notice); it shows «يتطلب التحميل للتشغيل» and keeps «تحميل». Supported formats,
 * the main (High) watch button, the player endpoint, URLs and download routes are unchanged. View-only change.
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'ips' => MainSchema::ips(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'الشيخ', 'prename' => 'د.']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'خطبة', 'vedio' => 1, 'hidden' => 0, 'link' => 'https://archive.org/download/f/high.mp4',
    ]);
});

function dloMirror(int $id, string $link, int $vedio = 1, string $comment = 'جودة متوسطة'): void
{
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => $id, 'khid' => 1, 'comment' => $comment, 'link' => $link, 'vedio' => $vedio, 'hidden' => 0, 'linksize' => 1000, 'hits' => 3,
    ]);
}

function dloCard(string $html, int $mirrorId): string
{
    foreach (explode('<article class="w2a-quality-card">', $html) as $card) {
        if (str_contains($card, '/khotab-mirror-1-'.$mirrorId.'.htm"')) {
            return explode('</article>', $card, 2)[0];
        }
    }
    throw new RuntimeException('no card for mirror '.$mirrorId);
}

function dloPage(): string
{
    return (string) test()->get('/khotab-item-1.htm')->assertOk()->getContent();
}

it('shows an unsupported Medium mirror as download only, keeping «تحميل»', function (string $file) {
    dloMirror(20, 'https://archive.org/download/f/'.$file);

    $card = dloCard(dloPage(), 20);

    expect($card)->not->toContain('w2a_play(')
        ->not->toContain('w2a-quality-play-btn')
        ->not->toContain('مشاهدة')
        ->toContain('data-download-only')
        ->toContain('يتطلب التحميل للتشغيل')
        ->toContain('href="/khotab-mirror-1-20.htm" download class="w2a-quality-down-btn"')
        ->toContain('تحميل');
})->with(['medium.rmvb', 'medium.wmv', 'medium.rm', 'MEDIUM.RMVB', 'medium.wmv?download=1']);

it('keeps «مشاهدة» for a supported MP4 mirror, passing its own mirror id', function () {
    dloMirror(21, 'https://archive.org/download/f/medium.mp4');

    $card = dloCard(dloPage(), 21);

    expect($card)->toMatch('~onclick="w2a_play\(21,\'khotab_mirror\'\)">\s*<i class="fa fa-play-circle" aria-hidden="true"></i> مشاهدة~u')
        ->not->toContain('data-download-only')
        ->toContain('href="/khotab-mirror-1-21.htm" download class="w2a-quality-down-btn"');
});

it('gives each playable card its own mirror id, and only the unsupported ones lose the button', function () {
    dloMirror(30, 'https://archive.org/download/f/a.mp4');
    dloMirror(31, 'https://archive.org/download/f/b.rmvb');
    dloMirror(32, 'https://archive.org/download/f/c.mp3', 0, 'رابط صوت');
    dloMirror(33, 'https://archive.org/download/f/d.wmv');
    dloMirror(34, 'https://archive.org/download/f/e.mp4');

    $html = dloPage();
    preg_match_all("~w2a_play\\((\\d+),'khotab_mirror'\\)~", $html, $ids);

    expect(array_map('intval', $ids[1]))->toBe([30, 32, 34])
        ->and(dloCard($html, 32))->toContain('استماع')
        ->and(substr_count($html, 'data-download-only'))->toBe(2);
    foreach ([30, 31, 32, 33, 34] as $id) {
        expect(dloCard($html, $id))->toContain('href="/khotab-mirror-1-'.$id.'.htm" download class="w2a-quality-down-btn"');
    }
});

it('leaves the main (High) watch button and its player unchanged', function () {
    dloMirror(20, 'https://archive.org/download/f/medium.rmvb');

    expect(dloPage())->toContain('onclick="w2a_play(1,\'khotab\')" class="w2a-action-btn w2a-action-play">')
        ->toContain('<span>مشاهدة المادة</span>')
        ->toContain('href="/khotab-download-1.htm"');
    expect((string) test()->post('/media-player', ['id' => 1, 'type' => 'khotab'])->assertOk()->getContent())
        ->toContain('<video')
        ->toContain('https://archive.org/download/f/high.mp4');
});

it('leaves the player endpoint unchanged for both mirror kinds', function () {
    dloMirror(20, 'https://archive.org/download/f/medium.rmvb');
    dloMirror(21, 'https://archive.org/download/f/medium.mp4');

    expect((string) test()->post('/media-player', ['id' => 20, 'type' => 'khotab_mirror'])->assertOk()->getContent())
        ->toContain('w2a-player-unsupported')
        ->not->toContain('<video');
    expect((string) test()->post('/media-player', ['id' => 21, 'type' => 'khotab_mirror'])->assertOk()->getContent())
        ->toContain('<video')
        ->toContain('https://archive.org/download/f/medium.mp4" type="video/mp4">');
});
