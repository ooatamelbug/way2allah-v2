<?php

/*
 * B2 / F05-2 Part 2 (owner-approved Option B, 2026-11-16, decision-log #144) — on the Khotab and Variety players a
 * media link in a format no browser plays (RMVB, WMV, RM, AVI, FLV, WMA — the closed list identified in B2) answers
 * with the approved explanation instead of the generic «غير متاحة» and instead of any player. The page's existing
 * download route is offered only when that route can serve the link; otherwise the message promises no download.
 * The watch buttons, the download routes and every other format are unchanged.
 */

use App\Domain\Content\Services\MediaPlayerService;
use App\Domain\Content\Support\BrowserPlaybackFormat;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;
use Tests\Support\ProxiedDownloadProbe;

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
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'ips' => MainSchema::ips(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'الشيخ', 'prename' => 'د.']);
});

const P2_WITH_DOWNLOAD = 'هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. يمكنك تحميل المادة وتشغيلها باستخدام برنامج يدعم هذه الصيغة.';
const P2_NO_DOWNLOAD = 'هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. لا يتوفر رابط تحميل لهذه المادة حالياً.';
const P2_FORMATS = ['rmvb', 'wmv', 'rm', 'avi', 'flv', 'wma'];

function p2KhotabItem(int $id, string $link, int $vedio = 1, int $hidden = 0): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => $id, 'author' => 1, 'title' => 'خطبة', 'vedio' => $vedio, 'hidden' => $hidden, 'link' => $link,
    ]);
}

function p2KhotabMirror(int $id, int $khid, string $link, int $vedio = 1, int $hidden = 0): void
{
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => $id, 'khid' => $khid, 'comment' => 'جودة متوسطة', 'link' => $link, 'vedio' => $vedio, 'hidden' => $hidden, 'hits' => 0,
    ]);
}

function p2AnasheedItem(int $id, string $link, int $vedio = 1, int $hidden = 0): void
{
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => $id, 'title' => 'نشيد', 'vedio' => $vedio, 'hidden' => $hidden, 'link' => $link,
    ]);
}

function p2AnasheedMirror(int $id, int $khid, string $link, int $vedio = 1, int $hidden = 0): void
{
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        'id' => $id, 'khid' => $khid, 'title' => 'جودة', 'link' => $link, 'vedio' => $vedio, 'hidden' => $hidden,
    ]);
}

function p2Play(string $type, int $id): string
{
    return (string) test()->post('/media-player', ['id' => $id, 'type' => $type])->assertOk()->getContent();
}

/** Seeds one surface with a single link; returns [player type, id, expected download href]. */
function p2Seed(string $surface, string $link, int $vedio = 1): array
{
    switch ($surface) {
        case 'khotab item':
            p2KhotabItem(1, $link, $vedio);

            return ['khotab', 1, '/khotab-download-1.htm'];
        case 'khotab mirror':
            p2KhotabItem(1, 'https://archive.org/download/f/main.mp4');
            p2KhotabMirror(20, 1, $link, $vedio);

            return ['khotab_mirror', 20, '/khotab-mirror-1-20.htm'];
        case 'variety item':
            p2AnasheedItem(1, $link, $vedio);

            return ['anasheed', 1, '/var-download-1.htm'];
        default:
            p2AnasheedItem(1, 'https://archive.org/download/f/main.mp4');
            p2AnasheedMirror(30, 1, $link, $vedio);

            return ['anasheed_mirror', 30, '/var-mirror-1-30.htm'];
    }
}

/** The download anchor of a notice, or null. */
function p2DownloadHref(string $html): ?string
{
    return preg_match('~<a href="([^"]*)" download class="w2a-action-btn w2a-action-download">~', $html, $m) ? $m[1] : null;
}

dataset('p2 surfaces', ['khotab item', 'khotab mirror', 'variety item', 'variety mirror']);

// --- 1–3, 6, 7, 14. Every unsupported format, on every Khotab / Variety surface --------------------------------

it('answers each unsupported format with the approved message, the existing download route, and no player', function (string $surface, string $format) {
    [$type, $id, $href] = p2Seed($surface, 'http://ia600700.us.archive.org/12/items/w2a_folder/lecture.'.$format);

    $html = p2Play($type, $id);

    expect($html)->toContain('<div class="w2a-player-status w2a-player-unsupported" role="status"><p>'.P2_WITH_DOWNLOAD.'</p>')
        ->and(p2DownloadHref($html))->toBe($href)
        ->and($html)->toContain('<span>تحميل المادة</span>')
        ->not->toContain('<video')->not->toContain('<audio')->not->toContain('<iframe')->not->toContain('<embed')->not->toContain('<object')
        // 14. Only the site's own relative route: no direct (Archive.org or any other) URL is generated.
        ->not->toContain('http')->not->toContain('archive.org')->not->toContain('//');
})->with('p2 surfaces')->with(P2_FORMATS);

// --- 4. Audio media in unsupported formats ----------------------------------------------------------------------

it('treats audio-flagged media in unsupported formats the same way (format decides, not the flag)', function (string $surface, string $format) {
    [$type, $id, $href] = p2Seed($surface, 'https://archive.org/download/f/sound.'.$format, 0);

    $html = p2Play($type, $id);

    expect($html)->toContain(P2_WITH_DOWNLOAD)->and(p2DownloadHref($html))->toBe($href);
})->with('p2 surfaces')->with(['wma', 'rm']);

// --- 5. The watch buttons stay visible and keep their labels ----------------------------------------------------

it('keeps the main watch button on the Khotab item page; unsupported mirrors become download-only (feedback 2026-11-18)', function () {
    p2KhotabItem(1, 'https://archive.org/download/f/main.rmvb');
    p2KhotabMirror(20, 1, 'https://archive.org/download/f/medium.wmv', 1);
    p2KhotabMirror(21, 1, 'https://archive.org/download/f/sound.rm', 0);

    $html = test()->get('/khotab-item-1.htm')->assertOk()->getContent();

    expect($html)->toContain('onclick="w2a_play(1,\'khotab\')" class="w2a-action-btn w2a-action-play">')
        ->and($html)->toContain('<span>مشاهدة المادة</span>')
        // Superseded by the owner's Khotab feedback of 2026-11-18: .wmv / .rm mirror cards offer download only.
        ->and($html)->not->toContain('w2a_play(20,\'khotab_mirror\')')
        ->and($html)->not->toContain('w2a_play(21,\'khotab_mirror\')')
        ->and(substr_count($html, 'data-download-only'))->toBe(2)
        // the cards' own download links are unchanged
        ->and($html)->toContain('href="/khotab-download-1.htm"')
        ->and($html)->toContain('href="/khotab-mirror-1-20.htm" download class="w2a-quality-down-btn"');
});

it('keeps the watch buttons on the Variety item page', function () {
    p2AnasheedItem(1, 'https://archive.org/download/f/clip.flv');
    p2AnasheedMirror(30, 1, 'https://archive.org/download/f/clip.avi');
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 1)->update(['mirror' => 1]); // the page lists mirrors only then

    $html = test()->get('/var-item-1.htm')->assertOk()->getContent();

    expect($html)->toContain('onclick="w2a_play(1,\'anasheed\')"')
        ->and($html)->toContain('<span>مشاهدة المادة</span>')
        ->and($html)->toContain('w2a_play(30,\'anasheed_mirror\')')
        ->and($html)->toContain('href="/var-download-1.htm"');
});

// --- 7. The offered link is the existing route, and it works -----------------------------------------------------

it('the offered Khotab download is the existing proxied route, which serves the file', function (string $surface) {
    $dir = sys_get_temp_dir().'/p2-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $file = $dir.'/lecture.rmvb';
    file_put_contents($file, 'rmvb-bytes');
    try {
        [$type, $id, $href] = p2Seed($surface, $file);

        expect(p2DownloadHref(p2Play($type, $id)))->toBe($href);
        test()->call('HEAD', $href)->assertOk();
    } finally {
        @unlink($file);
        @rmdir($dir);
    }
})->with(['khotab item', 'khotab mirror']);

it('the offered Variety download is the same route; since F04 it downloads the canonical https Archive.org file through the protected proxy', function (string $surface) {
    [$type, $id, $href] = p2Seed($surface, 'http://ia600700.us.archive.org/12/items/w2a_folder/clip.wmv');

    expect(p2DownloadHref(p2Play($type, $id)))->toBe($href);
    $head = test()->call('HEAD', $href)->assertOk();
    expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('clip.wmv')
        ->and(ProxiedDownloadProbe::resolvedLink($href))->toBe('https://archive.org/download/w2a_folder/clip.wmv');
})->with(['variety item', 'variety mirror']);

// --- 8. Download unavailable: no action, and no promise -------------------------------------------------------

it('offers no download, and says so, when the route cannot serve the link (and the route indeed refuses)', function (string $surface, string $link, int $routeStatus) {
    [$type, $id, $href] = p2Seed($surface, $link);

    $html = p2Play($type, $id);

    expect($html)->toBe('<div class="w2a-player-status w2a-player-unsupported" role="status"><p>'.P2_NO_DOWNLOAD.'</p></div>')
        ->not->toContain('تحميل المادة وتشغيلها')
        ->and(p2DownloadHref($html))->toBeNull();

    $response = test()->call('HEAD', $href);
    expect($response->status())->toBe($routeStatus);
    if ($routeStatus === 302) {   // a host-less Variety link redirects back into this site: not a download
        expect($response->headers->get('Location'))->toStartWith('http://localhost/download/');
    }
})->with([
    'khotab item, missing local file' => ['khotab item', '/no/such/dir/lecture.rmvb', 404],
    'khotab mirror, relative path' => ['khotab mirror', 'media/lecture.avi', 404],
    // Real shape: Variety item 9870 (upper-case, host-less), one of 202 such Variety items.
    'variety item 9870, host-less' => ['variety item', '/download/heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV', 302],
    'variety mirror, host-less' => ['variety mirror', '/download/folder/clip.flv', 302],
]);

// F04 fix (2026-11-17): Khotab mirror 830 is stored with a leading space on a retired storage node. The route now
// downloads the normalised archive.org URL (as the player), so the notice offers the download and the route serves it.
it('offers and serves the download for a leading-space storage-node mirror (mirror 830), via the normalised link', function () {
    [$type, $id, $href] = p2Seed('khotab mirror', ' http://ia360637.us.archive.org/2/items/4bdawyhigh6-12-2007/wageez4.rm');

    expect(p2DownloadHref(p2Play($type, $id)))->toBe($href);
    $head = test()->call('HEAD', $href)->assertOk();
    expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('wageez4.rm')
        ->and(ProxiedDownloadProbe::resolvedLink($href))->toBe('https://archive.org/download/4bdawyhigh6-12-2007/wageez4.rm');
});

// --- 9 / 10. Supported formats and F05-2 Part 1 unchanged --------------------------------------------------------

it('keeps the existing players for MP4, MP3 and YouTube', function (string $surface, string $link, int $vedio, string $expected) {
    [$type, $id] = p2Seed($surface, $link, $vedio);

    expect(p2Play($type, $id))->toContain($expected)->not->toContain('w2a-player-unsupported');
})->with('p2 surfaces')->with([
    'mp4 video' => ['https://archive.org/download/f/a.mp4', 1, '<video controls autoplay playsinline preload="metadata"><source src="https://archive.org/download/f/a.mp4" type="video/mp4">'],
    'mp3' => ['https://archive.org/download/f/a.mp3', 0, '<audio controls autoplay><source src="https://archive.org/download/f/a.mp3" type="audio/mpeg">'],
    'youtube' => ['https://www.youtube.com/watch?v=abcdefghijk', 1, 'https://www.youtube.com/embed/abcdefghijk?autoplay=1'],
]);

it('F05-2 Part 1 still holds: an audio-flagged .mp4 mirror of a video item plays as video; its unsupported siblings get the notice', function () {
    p2KhotabItem(4337, 'https://archive.org/download/f/main.mp4');
    p2KhotabMirror(834, 4337, 'http://ia360703.us.archive.org/11/items/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4', 0);
    p2KhotabMirror(833, 4337, 'http://www.archive.org/download/wageez-4/wageez-4-hi.avi', 0);
    p2KhotabMirror(836, 4337, 'http://ia360704.us.archive.org/19/items/Mirror2_way2allah/836_2_-rabt-flash-3alet.flv', 0);

    expect(p2Play('khotab_mirror', 834))->toContain('<video controls autoplay playsinline preload="metadata"><source src="https://archive.org/download/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4" type="video/mp4">')
        ->and(p2DownloadHref(p2Play('khotab_mirror', 833)))->toBe('/khotab-mirror-4337-833.htm')
        ->and(p2DownloadHref(p2Play('khotab_mirror', 836)))->toBe('/khotab-mirror-4337-836.htm');
});

it('an .mp4 is not assumed playable beyond the existing player: no codec check, the <video> error handler still applies', function () {
    p2KhotabItem(1, 'https://archive.org/download/f/hevc.mp4');

    expect(p2Play('khotab', 1))->toContain('<video')->not->toContain('w2a-player-unsupported');
});

// --- 11 / 12. Upper-case extensions and misleading query strings ----------------------------------------------

it('recognises upper- and mixed-case extensions', function (string $surface, string $file) {
    [$type, $id, $href] = p2Seed($surface, 'https://archive.org/download/f/'.$file);

    expect(p2DownloadHref(p2Play($type, $id)))->toBe($href);
})->with('p2 surfaces')->with(['LECTURE.RMVB', 'Clip.WMV', 'a.Flv']);

it('decides by the path extension only, never by the query string or fragment', function (string $link, string $expectation) {
    p2KhotabItem(1, $link);

    $html = p2Play('khotab', 1);

    match ($expectation) {
        'video' => expect($html)->toContain('<video')->not->toContain('w2a-player-unsupported'),
        'notice' => expect($html)->toContain(P2_WITH_DOWNLOAD),
        'unchanged empty' => expect($html)->toBe(''),
    };
})->with([
    'mp4 path, wmv in the query' => ['https://archive.org/download/f/a.mp4?file=b.wmv', 'video'],
    'wmv path, mp4 in the query' => ['https://archive.org/download/f/a.wmv?file=b.mp4', 'notice'],
    'php path, rmvb in the query' => ['https://example.com/play.php?file=a.rmvb', 'unchanged empty'],
    'no extension, rm in the fragment' => ['https://example.com/media#a.rm', 'unchanged empty'],
    'rmvb only in a directory name' => ['https://archive.org/download/f.rmvb/lecture', 'unchanged empty'],
    'avi as a filename substring' => ['https://archive.org/download/f/aviation', 'unchanged empty'],
]);

// --- Closed list: unknown or other formats keep the existing behaviour ---------------------------------------------

it('does not classify formats outside the approved list as unsupported (existing empty response kept)', function (string $extension) {
    p2KhotabItem(1, 'https://archive.org/download/f/a.'.$extension);

    expect(p2Play('khotab', 1))->toBe('');
})->with(['3gp', 'ogv', 'm4v', 'ogg', 'rv', 'mkv', 'webm', 'xyz']);   // F07-A (#147) moved asf, mpg, ram, ra into the list

it('leaves the other modules (Telawat, Fatawa) untouched', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 1, 'title' => 'تلاوة', 'link' => 'https://archive.org/download/f/a.rm', 'vedio' => 0]);

    expect(p2Play('telawat', 1))->toBe('');
});

// --- 13. Visibility ------------------------------------------------------------------------------------------------

it('keeps refusing hidden content, before any format notice', function (callable $seed, string $type, int $id) {
    $seed();

    expect(p2Play($type, $id))->toBe('');
})->with([
    'hidden khotab item' => [fn () => p2KhotabItem(1, 'https://archive.org/download/f/a.rmvb', 1, 1), 'khotab', 1],
    'hidden khotab mirror' => [fn () => [p2KhotabItem(1, 'x'), p2KhotabMirror(20, 1, 'https://archive.org/download/f/a.wmv', 1, 1)], 'khotab_mirror', 20],
    'hidden variety item' => [fn () => p2AnasheedItem(1, 'https://archive.org/download/f/a.flv', 1, 1), 'anasheed', 1],
    'mirror of a hidden variety item' => [fn () => [p2AnasheedItem(1, 'x', 1, 1), p2AnasheedMirror(30, 1, 'https://archive.org/download/f/a.avi')], 'anasheed_mirror', 30],
    'hidden variety mirror' => [fn () => [p2AnasheedItem(1, 'x'), p2AnasheedMirror(30, 1, 'https://archive.org/download/f/a.avi', 1, 1)], 'anasheed_mirror', 30],
]);

it('writes nothing: no counter or flag changes when the notice is shown', function () {
    p2KhotabItem(1, 'https://archive.org/download/f/a.rmvb');
    p2KhotabMirror(20, 1, 'https://archive.org/download/f/b.wmv');
    $before = [DB::connection('main')->table('nuke_islamic_khotab')->get()->toArray(), DB::connection('main')->table('nuke_islamic_mirror')->get()->toArray()];

    p2Play('khotab', 1);
    p2Play('khotab_mirror', 20);

    expect([DB::connection('main')->table('nuke_islamic_khotab')->get()->toArray(), DB::connection('main')->table('nuke_islamic_mirror')->get()->toArray()])->toEqual($before);
});

// --- The shared helper ---------------------------------------------------------------------------------------------

it('helper: path extension and the closed list', function (string $link, string $extension, bool $unsupported) {
    expect(BrowserPlaybackFormat::pathExtension($link))->toBe($extension)
        ->and(BrowserPlaybackFormat::isUnsupported($link))->toBe($unsupported);
})->with([
    ['https://x.org/a.RMVB', 'rmvb', true],
    [' http://x.org/a.rm', 'rm', true],
    ['/download/f/a.WMV', 'wmv', true],
    ['https://x.org/a.wma?x=1#y', 'wma', true],
    ['https://x.org/a.mp4?f=b.avi', 'mp4', false],
    ['https://x.org/a.flv.mp4', 'mp4', false],
    ['https://x.org/a', '', false],
    ['http:///a.avi', '', false],
]);

it('the message constants are the owner-approved wording', function () {
    expect(MediaPlayerService::UNSUPPORTED_FORMAT_MESSAGE.' '.MediaPlayerService::UNSUPPORTED_FORMAT_DOWNLOAD_HINT)->toBe(P2_WITH_DOWNLOAD);
});
