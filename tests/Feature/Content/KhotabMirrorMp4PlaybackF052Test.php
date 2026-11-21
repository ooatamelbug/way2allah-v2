<?php

/*
 * B2 / F05-2 (owner-approved 2026-11-16, decision-log #143) — a Khotab mirror whose URL path is `.mp4` and whose
 * PARENT item is a video item plays (and is labelled) as video even when the mirror's own `vedio` flag says audio.
 * 601 such mirrors exist locally (e.g. 834 / item 4337, 564 / 3441, 799 / 4282); before this they returned no player
 * («غير متاحة للتشغيل»). Render-time only: stored flags never change. Intentionally differs from Gen 2.
 *
 * Fixtures mirror the real rows (links and labels copied from the local database, item 4337's eight mirrors in order).
 */

use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Services\MediaPlayerService;
use App\Domain\Content\Support\BrowserPlaybackFormat;
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
});

function f052Item(int $id, int $vedio = 1, int $hidden = 0): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => $id, 'author' => 1, 'title' => 'خطبة '.$id, 'vedio' => $vedio, 'hidden' => $hidden, 'link' => 'https://archive.org/download/f/main.mp4',
    ]);
}

function f052Mirror(int $id, int $khid, string $link, int $vedio = 0, string $comment = 'للجوال بجودة متوسطة', int $hidden = 0): void
{
    DB::connection('main')->table('nuke_islamic_mirror')->insert([
        'id' => $id, 'khid' => $khid, 'comment' => $comment, 'link' => $link, 'vedio' => $vedio, 'hidden' => $hidden,
        'linksize' => 1000, 'hits' => 5,
    ]);
}

function f052Play(int $mirrorId): string
{
    return (string) test()->post('/media-player', ['id' => $mirrorId, 'type' => 'khotab_mirror'])->assertOk()->getContent();
}

const F052_VIDEO = '<video controls autoplay playsinline preload="metadata"><source src="';

/** Item 4337's real mirrors, in id order: [id, vedio, comment, link]. */
const F052_ITEM_4337 = [
    [830, 0, 'جودة عالية', ' http://ia360637.us.archive.org/2/items/4bdawyhigh6-12-2007/wageez4.rm'],
    [832, 0, 'رابط صوت', ' http://ia360603.us.archive.org/0/items/wageez-4/wageez-4.mp3'],
    [833, 0, 'جودة عالية ممتازة', 'http://www.archive.org/download/wageez-4/wageez-4-hi.avi'],
    [834, 0, 'رابط جوال mp4  متوسطة', 'http://ia360703.us.archive.org/11/items/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4'],
    [836, 0, 'رابط فلاش عالية', 'http://ia360704.us.archive.org/19/items/Mirror2_way2allah/836_2_-rabt-flash-3alet.flv'],
    [838, 0, 'رابط جوال mp4 عالية', 'http://ia360704.us.archive.org/19/items/Mirror2_way2allah/838_2_-rabt-gwal-4-3alet.mp4'],
    [9504, 1, 'جودة عالية-avi', 'http://ia301514.us.archive.org/0/items/all-islamic-aansary-15/wageez-4-29-11-2007.avi'],
    [9505, 1, 'رابط صوتmp3 ', 'http://ia301514.us.archive.org/0/items/all-islamic-aansary-15/wageez-4-29-11-2007.mp3'],
];

function f052Seed4337(): void
{
    f052Item(4337);
    foreach (F052_ITEM_4337 as [$id, $vedio, $comment, $link]) {
        f052Mirror($id, 4337, $link, $vedio, $comment);
    }
}

/** The play button of one mirror card: [icon class, label]. */
function f052Button(string $html, int $mirrorId): array
{
    $pattern = '~onclick="w2a_play\('.$mirrorId.',\'khotab_mirror\'\)">\s*<i class="fa (fa-[a-z-]+)" aria-hidden="true"></i>\s*(\S+)\s*</button>~u';
    expect(preg_match($pattern, $html, $m))->toBe(1, 'play button for mirror '.$mirrorId);

    return [$m[1], $m[2]];
}

/** One mirror card's HTML (the <article> whose links name this mirror). */
function f052Card(string $html, int $mirrorId): string
{
    foreach (explode('<article class="w2a-quality-card">', $html) as $card) {
        if (preg_match('~/khotab-mirror-\d+-'.$mirrorId.'\.htm"~', $card)) {
            return explode('</article>', $card, 2)[0];
        }
    }
    throw new RuntimeException('no card for mirror '.$mirrorId);
}

/** Khotab feedback 2026-11-18: an unsupported-format card has no play button, the download note and «تحميل». */
function f052DownloadOnly(string $html, int $mirrorId): bool
{
    $card = f052Card($html, $mirrorId);

    return ! str_contains($card, 'w2a_play(') && str_contains($card, 'data-download-only')
        && str_contains($card, 'يتطلب التحميل للتشغيل') && str_contains($card, 'class="w2a-quality-down-btn"');
}

// --- 1. Video item + MP4 mirror flagged audio (the confirmed rows) -----------------------------------------------

it('plays an audio-flagged .mp4 mirror of a video item in <video>, from the normalised https URL (confirmed rows)', function (int $mirror, int $item, string $link, string $expected) {
    f052Item($item);
    f052Mirror($mirror, $item, $link);

    expect(f052Play($mirror))->toContain(F052_VIDEO.$expected.'" type="video/mp4">')
        ->not->toContain('<audio')
        ->not->toContain('us.archive.org');
})->with([
    'mirror 834 / item 4337' => [834, 4337, 'http://ia360703.us.archive.org/11/items/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4', 'https://archive.org/download/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4'],
    'mirror 564 / item 3441' => [564, 3441, 'http://ia331433.us.archive.org/3/items/islamicmedia1/564_2_d3f-alhmt-amrad-alamt-.mp4', 'https://archive.org/download/islamicmedia1/564_2_d3f-alhmt-amrad-alamt-.mp4'],
    'mirror 799 / item 4282' => [799, 4282, 'http://ia360703.us.archive.org/11/items/Mirror1_way2allah/799_2_-llgwal-bgwdt-mtwstt.mp4', 'https://archive.org/download/Mirror1_way2allah/799_2_-llgwal-bgwdt-mtwstt.mp4'],
]);

it('never writes the classification flags (render-time only)', function () {
    f052Seed4337();
    $flags = fn () => [
        DB::connection('main')->table('nuke_islamic_mirror')->orderBy('id')->pluck('vedio', 'id')->all(),
        DB::connection('main')->table('nuke_islamic_khotab')->where('id', 4337)->value('vedio'),
    ];
    $before = $flags();

    f052Play(834);
    test()->get('/khotab-item-4337.htm')->assertOk(); // (the page view's own hits / lastvisit update is pre-existing)

    expect($flags())->toBe($before)
        ->and($before[0][834])->toBe(0);
});

// --- 2. Video item + MP4 mirror correctly flagged video ------------------------------------------------------------

it('keeps a correctly flagged .mp4 video mirror unchanged (with or without a video parent)', function (int $itemVedio) {
    f052Item(1, $itemVedio);
    f052Mirror(10, 1, 'https://archive.org/download/f/hd.mp4', 1);

    expect(f052Play(10))->toContain(F052_VIDEO.'https://archive.org/download/f/hd.mp4" type="video/mp4">');
})->with(['video parent' => 1, 'audio parent' => 0]);

// --- 3. Audio item + MP4 mirror ------------------------------------------------------------------------------------

it('does NOT promote an audio-flagged .mp4 mirror of an AUDIO item (parent type and format are both required)', function () {
    f052Item(1, 0);
    f052Mirror(10, 1, 'https://archive.org/download/f/a.mp4');

    expect(f052Play(10))->toBe('');
    [$icon, $label] = f052Button(test()->get('/khotab-item-1.htm')->assertOk()->getContent(), 10);
    expect([$icon, $label])->toBe(['fa-headphones', 'استماع']);
});

it('does NOT promote an audio-flagged .mp4 mirror whose parent row is missing', function () {
    f052Mirror(10, 999, 'https://archive.org/download/f/a.mp4');

    expect(f052Play(10))->toBe('');
});

// --- 4. Video item + genuine audio mirror ---------------------------------------------------------------------------

it('keeps genuine audio mirrors of a video item as audio', function (string $link, string $expected) {
    f052Item(1);
    f052Mirror(10, 1, $link);

    expect(f052Play(10))->toContain($expected)->not->toContain('<video');
})->with([
    'mp3' => ['http://ia360603.us.archive.org/0/items/wageez-4/wageez-4.mp3', '<audio controls autoplay><source src="https://archive.org/download/wageez-4/wageez-4.mp3" type="audio/mpeg">'],
    'soundcloud' => ['https://soundcloud.com/way2allah/a-track', 'https://w.soundcloud.com/player/?url=https://soundcloud.com/way2allah/a-track'],
]);

// --- 5. Video item + unsupported video format: never promoted to a player (F05-2 Part 2 then explains it) ----------

it('never promotes unsupported formats to a player, whatever the flags (Part 2: the explanation instead)', function (string $extension, int $mirrorVedio) {
    f052Item(1);
    f052Mirror(10, 1, 'https://archive.org/download/f/lecture.'.$extension, $mirrorVedio);

    expect(f052Play(10))->toContain('w2a-player-unsupported')->not->toContain('<video')->not->toContain('<audio');
})->with(['rmvb', 'wmv', 'rm', 'avi', 'flv', 'wma'])->with(['flagged audio' => 0, 'flagged video' => 1]);

// --- 6 / 7. Query parameters and upper-case extension --------------------------------------------------------------

it('recognises .mp4 by the URL path, keeping a query string and fragment in the played source', function () {
    f052Item(1);
    f052Mirror(10, 1, 'https://archive.org/download/f/a.mp4?download=1&x=2');

    expect(f052Play(10))->toContain(F052_VIDEO.'https://archive.org/download/f/a.mp4?download=1&amp;x=2" type="video/mp4">');
});

it('recognises an upper- or mixed-case .MP4 extension', function (string $file) {
    f052Item(1);
    f052Mirror(10, 1, 'https://archive.org/download/f/'.$file);

    expect(f052Play(10))->toContain(F052_VIDEO.'https://archive.org/download/f/'.$file.'" type="video/mp4">');
})->with(['LECTURE.MP4', 'Lecture.Mp4']);

// --- 8. `mp4` only in the query, host or a filename substring -----------------------------------------------------

it('does not treat "mp4" outside the path extension as MP4', function (string $link) {
    f052Item(1);
    f052Mirror(10, 1, $link);

    expect(f052Play(10))->not->toContain('<video');
    $html = test()->get('/khotab-item-1.htm')->assertOk()->getContent();
    // Khotab feedback 2026-11-18: a link whose path extension is an unsupported format is download-only; any other
    // non-MP4 link keeps its (audio) button.
    if (BrowserPlaybackFormat::isUnsupported($link)) {
        expect(f052DownloadOnly($html, 10))->toBeTrue();
    } else {
        expect(f052Button($html, 10))->toBe(['fa-headphones', 'استماع']);
    }
})->with([
    'query parameter' => 'https://archive.org/download/f/a.rmvb?file=b.mp4',
    'query only, no extension' => 'https://archive.org/download/f/play?format=mp4',
    'fragment' => 'https://archive.org/download/f/a.wmv#b.mp4',
    'double extension' => 'https://archive.org/download/f/a.mp4.wmv',
    'filename substring' => 'https://archive.org/download/f/mp4_lecture.wmv',
    'directory named .mp4' => 'https://archive.org/download/f.mp4/lecture.avi',
    'host' => 'https://mp4.example.com/lecture.flv',
    'mp4 without the dot' => 'https://archive.org/download/f/lecturemp4',
]);

it('helper: the rule itself, case by case', function (mixed $mirrorVedio, mixed $itemVedio, string $link, bool $expected) {
    expect(MediaPlayerService::khotabMirrorPlaysAsVideo($mirrorVedio, $itemVedio, $link))->toBe($expected);
})->with([
    'mirror flagged video wins (unchanged)' => [1, 0, 'https://x.org/a.rmvb', true],
    'audio mp4 of video item' => [0, 1, 'https://x.org/a.mp4', true],
    'leading space (real data)' => [0, 1, ' http://x.org/a.mp4', true],
    'relative path' => [0, 1, 'media/a.mp4', true],
    'audio mp4 of audio item' => [0, 0, 'https://x.org/a.mp4', false],
    'unknown parent (null)' => [0, null, 'https://x.org/a.mp4', false],
    'parent string "1" from the DB' => ['0', '1', 'https://x.org/a.mp4', true],
    'mp3 of video item' => [0, 1, 'https://x.org/a.mp3', false],
    'youtube of video item (flag decides, unchanged)' => [0, 1, 'https://youtu.be/abcdefghijk', false],
    'unparseable URL' => [0, 1, 'http:///a.mp4', false],
]);

// --- 9 / 10. Mirror ordering and quality labels on the item page ----------------------------------------------------

it('keeps the mirror order, numbering, titles and format badges of item 4337; only playable formats keep a button', function () {
    f052Seed4337();

    $html = test()->get('/khotab-item-4337.htm')->assertOk()->getContent();

    preg_match_all("~w2a_play\\((\\d+),'khotab_mirror'\\)~", $html, $order);
    // Khotab feedback 2026-11-18: .rm / .avi / .flv cards (830, 833, 836, 9504) are download-only.
    expect(array_map('intval', $order[1]))->toBe([832, 834, 838, 9505]);

    preg_match_all('~<span class="w2a-quality-num">(\d+)</span>~', $html, $numbers);
    preg_match_all('~<span class="w2a-quality-badge-fmt">([^<]*)</span>~', $html, $badges);
    expect(array_map('intval', $numbers[1]))->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($badges[1])->toBe(['RM', 'MP3', 'AVI', 'MP4', 'FLV', 'MP4', 'AVI', 'MP3']);

    foreach (F052_ITEM_4337 as [$id, , $comment]) {
        expect($html)->toContain('<a class="w2a-quality-title" href="/khotab-mirror-4337-'.$id.'.htm" download>'.e($comment).'</a>');
    }

    $buttons = [];
    foreach ([832, 834, 838, 9505] as $id) {
        $buttons[$id] = f052Button($html, $id);
    }
    expect($buttons)->toBe([
        832 => ['fa-headphones', 'استماع'],   // .mp3 — unchanged
        834 => ['fa-play-circle', 'مشاهدة'],  // F05-2: .mp4 of a video item
        838 => ['fa-play-circle', 'مشاهدة'],  // F05-2: .mp4 of a video item
        9505 => ['fa-play-circle', 'مشاهدة'], // flagged video (an .mp3) — unchanged
    ]);
    foreach ([830, 833, 836, 9504] as $id) {  // .rm / .avi / .flv / .avi
        expect(f052DownloadOnly($html, $id))->toBeTrue('download-only card for mirror '.$id);
    }
});

it('the card and the player agree for every mirror of item 4337', function () {
    f052Seed4337();
    $html = test()->get('/khotab-item-4337.htm')->assertOk()->getContent();

    foreach ([834, 838] as $id) {
        expect(f052Play($id))->toContain(F052_VIDEO);
    }
    foreach ([830, 833, 836] as $id) {   // never a player; F05-2 Part 2 explains the format instead
        expect(f052Play($id))->toContain('w2a-player-unsupported')->not->toContain('<video');
    }
    // 830 is stored with a leading space on a retired storage node. Since the F04 fix (2026-11-17) the route downloads
    // the normalised archive.org URL (as the player), so the download is offered like 833's.
    expect(f052Play(830))->toContain('href="/khotab-mirror-4337-830.htm"')
        ->and(f052Play(833))->toContain('href="/khotab-mirror-4337-833.htm"');
    expect(f052Play(832))->toContain('<audio')
        ->and(f052Button($html, 834))->toBe(['fa-play-circle', 'مشاهدة']);
});

it('Variety (Anasheed) mirror cards are untouched by the Khotab rule', function () {
    $view = view('components.content.media-quality-list', [
        'items' => collect([new AnasheedMirror(['id' => 5, 'khid' => 1, 'title' => 'م', 'link' => 'https://archive.org/download/f/a.mp4', 'vedio' => 0])]),
        'module' => 'anasheed', 'parentId' => 1, 'parentVedio' => 1,
    ])->render();

    $expectedAudio = (new AnasheedMirror(['link' => 'https://archive.org/download/f/a.mp4', 'vedio' => 0]))->isAudioLike();
    expect($view)->toContain($expectedAudio ? 'fa-headphones' : 'fa-play-circle');
});

// --- 11. Download behaviour ------------------------------------------------------------------------------------------

it('the mirror download route is unchanged for an affected mirror: same bytes, video/mp4, one hit, flags untouched', function () {
    $dir = sys_get_temp_dir().'/f052-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $file = $dir.'/834_2_-rabt-gwal-4-mtwstt.mp4';
    file_put_contents($file, 'mirror-bytes');
    try {
        f052Item(4337);
        f052Mirror(834, 4337, $file);

        $response = test()->get('/khotab-mirror-4337-834.htm')->assertOk()->assertHeader('Content-Type', 'video/mp4');

        // DB state first: the streamer releases the database connection before sending the body.
        $row = DB::connection('main')->table('nuke_islamic_mirror')->find(834);
        expect($row->hits)->toBe(6)
            ->and($row->vedio)->toBe(0)
            ->and($response->streamedContent())->toBe('mirror-bytes');
    } finally {
        @unlink($file);
        @rmdir($dir);
    }
});

// --- 12. Visibility -----------------------------------------------------------------------------------------------

it('a hidden mirror is still refused, even when it would now qualify as video', function () {
    f052Item(1);
    f052Mirror(10, 1, 'https://archive.org/download/f/a.mp4', 0, 'م', 1);

    expect(f052Play(10))->toBe('');
});

it('the parent-item visibility rule is unchanged: the mirror endpoint answers exactly as for a correctly flagged mirror', function () {
    // Before F05-2 the mirror endpoint never consulted the parent's `hidden` flag (only the item PAGE 404s on it).
    // The parent's `vedio` is now read without a `hidden` filter, so an affected mirror behaves exactly like the
    // equivalent correctly flagged mirror did — no new exposure, no new refusal.
    f052Item(1, 1, 1);
    f052Mirror(10, 1, 'https://archive.org/download/f/a.mp4', 0);
    f052Mirror(11, 1, 'https://archive.org/download/f/a.mp4', 1);

    expect(f052Play(10))->toBe(f052Play(11))
        ->and(test()->get('/khotab-item-1.htm')->status())->toBe(404);
});

it('reads the parent item only for audio-flagged .mp4 mirrors (no extra query for the other 425k mirrors)', function (string $link, int $mirrorVedio, int $itemQueries) {
    f052Item(1);
    f052Mirror(10, 1, $link, $mirrorVedio);

    $queries = [];
    DB::connection('main')->listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    f052Play(10);

    expect(count(array_filter($queries, fn (string $sql) => str_contains($sql, 'nuke_islamic_khotab'))))->toBe($itemQueries);
})->with([
    'audio-flagged mp4' => ['https://archive.org/download/f/a.mp4', 0, 1],
    'video-flagged mp4' => ['https://archive.org/download/f/a.mp4', 1, 0],
    'audio-flagged mp3' => ['https://archive.org/download/f/a.mp3', 0, 0],
    'audio-flagged rmvb' => ['https://archive.org/download/f/a.rmvb', 0, 0],
]);
