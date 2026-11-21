<?php

/*
 * B2 / F07-B2 (owner-approved 2026-11-16, decision-log #148):
 * - the Variety GetRight list (`/var-series-{group}.grx`) lists only items `/var-download` can actually serve
 *   (the F07-A rule); valid entries, order, format, headers and visibility are unchanged;
 * - `/var-mirror-{item}-{mirror}` refuses a mirror whose own `hidden` flag is set (the player already did), and the
 *   item page offers no download link for it. Visible mirrors and the Khotab mirror route are unchanged.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\ProxiedDownloadProbe;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 300, 'title' => 'سلسلة', 'parent_id' => 98]);
});

function f07dItem(int $id, string $link, int $weight, int $hidden = 0, string $title = ''): void
{
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => $id, 'title' => $title !== '' ? $title : 'مادة '.$id, 'group_id' => 300,
        'parent_id' => 300, 'hidden' => $hidden, 'weight' => $weight, 'vedio' => 1, 'mirror' => 1, 'link' => $link, 'downcount' => 0]);
}

/** The ids listed in a .grx body, in order. */
function f07dGrxIds(string $grx): array
{
    preg_match_all('#/var-download-(\d+)\.htm#', $grx, $m);

    return array_map('intval', $m[1]);
}

// --- GetRight ----------------------------------------------------------------------------------------------------------

it('lists only downloadable items, keeping their order, entries and format; hidden items stay out', function () {
    f07dItem(1, 'https://archive.org/download/f/a.mp4', 50, title: 'أولى');
    f07dItem(2, 'new-new-aby-alhanon_x264.mp4new-new-al3awda_x264.mp4', 10);                 // concatenated
    f07dItem(3, '', 20);                                                                       // empty (would be the home page)
    f07dItem(4, 'http://vars.way2allah.com/cartoon/amin8/b.mp4', 30, title: 'ثانية');
    f07dItem(5, '/cartoon/amin3/x.rmvb', 40);                                                  // host-less
    f07dItem(6, ' http://vars.way2allah.com/cartoon/3/c.rmvb', 45);                           // leading space, non-archive
    f07dItem(7, 'رhttp://vars.way2allah.com/cartoon/amin8/d.mp4', 15);                         // stray character
    f07dItem(8, 'https://archive.org/download/f/hidden.mp4', 5, 2);                            // hidden
    f07dItem(9, ' http://ia331419.us.archive.org/1/items/f/e.wmv', 60, title: 'ثالثة');      // leading space, archive.org: normalised → served

    $response = $this->get('/var-series-300.grx')->assertOk();
    $grx = $response->getContent();

    expect(f07dGrxIds($grx))->toBe([4, 1, 9])                                                  // weight ASC, as before
        ->and($grx)->toBe(
            "URL: http://localhost/var-download-4.htm\r\nFile: C:\\Way2Allah\\الأناشيد\\سلسلة\\ثانية.mp4.GetRight\r\n\r\n"
            ."URL: http://localhost/var-download-1.htm\r\nFile: C:\\Way2Allah\\الأناشيد\\سلسلة\\أولى.mp4.GetRight\r\n\r\n"
            ."URL: http://localhost/var-download-9.htm\r\nFile: C:\\Way2Allah\\الأناشيد\\سلسلة\\ثالثة.wmv.GetRight\r\n\r\n")
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('must-revalidate')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="Way2Allah-Anasheed-300.grx"');
});

it('every listed URL is one the download route serves (no entry lands on this site)', function () {
    f07dItem(1, 'https://archive.org/download/f/a.mp4', 1);
    f07dItem(2, '/download/heekmaa702/t.WMV', 2);
    f07dItem(3, 'http://vars.way2allah.com/cartoon/amin8/b.mp4', 3);

    // F04: a listed entry is now a real download (HEAD: attachment headers, no source contact), never a redirect onto
    // this site.
    foreach (f07dGrxIds($this->get('/var-series-300.grx')->getContent()) as $id) {
        $head = $this->call('HEAD', "/var-download-{$id}.htm")->assertOk();
        expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')
            ->and($head->headers->get('Location'))->toBeNull();
    }
});

it('an all-broken group yields an empty list (the route still answers as before)', function () {
    f07dItem(1, '', 1);
    f07dItem(2, '16', 2);

    expect($this->get('/var-series-300.grx')->assertOk()->getContent())->toBe('');
});

// --- Hidden mirror protection -----------------------------------------------------------------------------------------

it('refuses a hidden mirror on its direct download route (GET and HEAD), counting nothing', function () {
    f07dItem(1, 'https://archive.org/download/f/main.mp4', 1);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        ['id' => 10, 'khid' => 1, 'title' => 'ظاهرة', 'link' => 'https://archive.org/download/f/visible.mp4', 'hidden' => 0, 'hits' => 4],
        ['id' => 11, 'khid' => 1, 'title' => 'مخفية', 'link' => 'https://archive.org/download/f/hidden.mp4', 'hidden' => 1, 'hits' => 7],
    ]);

    $this->get('/var-mirror-1-11.htm')->assertNotFound();
    $this->call('HEAD', '/var-mirror-1-11.htm')->assertNotFound();
    expect((int) DB::connection('main')->table('nuke_anasheed_mirror')->where('id', 11)->value('hits'))->toBe(7);

    // A visible mirror: served (F04: through the protected proxy, same link); a refused download is not counted.
    expect(ProxiedDownloadProbe::resolvedLink('/var-mirror-1-10.htm'))->toBe('https://archive.org/download/f/visible.mp4');
    expect((int) DB::connection('main')->table('nuke_anasheed_mirror')->where('id', 10)->value('hits'))->toBe(4);
});

it('keeps refusing mirrors of hidden items and mirrors of another item', function () {
    f07dItem(1, 'https://archive.org/download/f/main.mp4', 1, 1);
    f07dItem(2, 'https://archive.org/download/f/main2.mp4', 2);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        ['id' => 10, 'khid' => 1, 'title' => 'm', 'link' => 'https://archive.org/download/f/a.mp4', 'hidden' => 0],
        ['id' => 12, 'khid' => 2, 'title' => 'm', 'link' => 'https://archive.org/download/f/b.mp4', 'hidden' => 0],
    ]);

    $this->get('/var-mirror-1-10.htm')->assertNotFound();
    $this->get('/var-mirror-1-12.htm')->assertNotFound();
    expect(ProxiedDownloadProbe::resolvedLink('/var-mirror-2-12.htm'))->toBe('https://archive.org/download/f/b.mp4');
});

it('the item page offers no download link for a hidden mirror (the player already refused it)', function () {
    f07dItem(1, 'https://archive.org/download/f/main.mp4', 1);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert([
        ['id' => 10, 'khid' => 1, 'title' => 'ظاهرة', 'link' => 'https://archive.org/download/f/visible.mp4', 'hidden' => 0],
        ['id' => 11, 'khid' => 1, 'title' => 'مخفية', 'link' => 'https://archive.org/download/f/hidden.mp4', 'hidden' => 1],
    ]);

    $html = $this->get('/var-item-1.htm')->assertOk()->getContent();

    expect($html)->toContain('href="/var-mirror-1-10.htm"')->not->toContain('href="/var-mirror-1-11.htm"');
    expect((string) $this->post('/media-player', ['id' => 11, 'type' => 'anasheed_mirror'])->assertOk()->getContent())->toBe('');
});

it('leaves the Khotab mirror route as it was (it does not consult the mirror flag)', function () {
    $file = tempnam(sys_get_temp_dir(), 'f07d');
    file_put_contents($file, 'bytes');
    try {
        H::insert('nuke_islamic_khotab', ['id' => 1, 'author' => 1, 'title' => 'خطبة', 'vedio' => 1, 'hidden' => 0, 'link' => 'x']);
        H::insert('nuke_islamic_mirror', ['id' => 5, 'khid' => 1, 'comment' => 'm', 'link' => $file, 'hidden' => 1]);

        $this->get('/khotab-mirror-1-5.htm')->assertOk();
    } finally {
        @unlink($file);
    }
});
