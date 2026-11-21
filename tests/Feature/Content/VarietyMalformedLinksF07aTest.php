<?php

/*
 * B2 / F07-A (owner-approved 2026-11-16, decision-log #147) — Variety (Anasheed) items and mirrors whose stored link
 * is not an absolute http(s) URL with a host:
 * - the player shows «رابط تشغيل هذه المادة غير صالح حاليًا…» instead of starting a <video>/<audio> that fails;
 * - the public download / save buttons are hidden when /var-download or /var-mirror cannot serve the link;
 * - the AdminCP item and mirror forms warn (never block) when the saved link has that shape;
 * - ASF, RAM, RA and MPG join the F05-2 unsupported-format list.
 * Fixture links are copied from the real malformed rows (B2 / F07 proposal §1). Isolated in-memory database only.
 */

use App\Domain\Content\Services\MediaPlayerService;
use App\Domain\Content\Support\VarietyMediaLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\ProxiedDownloadProbe;

uses(RefreshDatabase::class);

const F07A_INVALID = 'رابط تشغيل هذه المادة غير صالح حاليًا. يرجى إبلاغ إدارة الموقع بالمشكلة.';
const F07A_UNSUPPORTED_DL = 'هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. يمكنك تحميل المادة وتشغيلها باستخدام برنامج يدعم هذه الصيغة.';
const F07A_UNSUPPORTED_NODL = 'هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. لا يتوفر رابط تحميل لهذه المادة حالياً.';

/** Real malformed item links (id => link). */
const F07A_BAD_ITEMS = [
    16755 => 'new-new-aby-alhanon_x264.mp4new-new-al3awda_x264.mp4',
    16758 => 'new-new-alamana_x264.mp4new-new-alamana_x264.mp4',
    15030 => 'watha2qia-rob3iat-aldaf3-24-5-2021_x264.mp4watha2qia-rob3iat-aldaf3-epi2-24-5-2021_x264.mp4',
    12195 => 'watha2qia-al3alem-alda2i3-27-8-2016.AVIwatha2qia-alfosol-alhamraa-27-8-2016_x264.mp4',
    14823 => 'رhttp://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4',
    6485 => '',
];

beforeEach(function () {
    H::setUp();
    $this->group = H::insert('nuke_anasheed_groups', ['title' => 'كرتون', 'parent_id' => 0, 'anasheed' => 0]);
});

function f07aItem(int $id, string $link, int $vedio = 1, array $extra = []): int
{
    return H::insert('nuke_anasheed_anasheed', $extra + ['id' => $id, 'title' => 'مادة '.$id, 'group_id' => test()->group, 'parent_id' => test()->group,
        'vedio' => $vedio, 'hidden' => 0, 'link' => $link, 'mirror' => 1]);
}

function f07aMirror(int $id, int $item, string $link, int $vedio = 1, array $extra = []): int
{
    return H::insert('nuke_anasheed_mirror', $extra + ['id' => $id, 'khid' => $item, 'title' => 'جودة '.$id, 'link' => $link, 'vedio' => $vedio, 'hidden' => 0]);
}

function f07aPlay(string $type, int $id): string
{
    app()->forgetScopedInstances();

    return (string) test()->post('/media-player', ['id' => $id, 'type' => $type])->assertOk()->getContent();
}

function f07aNoPlayer(string $html): void
{
    expect($html)->not->toContain('<video')->not->toContain('<audio')->not->toContain('<iframe')->not->toContain('<source');
}

function f07aEditPayload(array $overrides = []): array
{
    return $overrides + [
        'title' => 'نشيد معدل', 'vedio' => '1', 'fixed' => '0', 'is_published' => '1', 'Anasheed_link' => 'https://archive.org/download/f/a.mp4',
        'Channel' => '', 'Author' => '', 'Anasheedsize' => '0.00', 'Des' => '', 'description' => '', 'meta_keywords' => '',
        'meta_description' => '', 'group' => (string) test()->group,
    ];
}

// --- 1 / 6–9. Known malformed items: the invalid-link notice, no player -----------------------------------------------

it('shows the approved invalid-link notice, and no player, for each real malformed Variety item', function (int $id) {
    f07aItem($id, F07A_BAD_ITEMS[$id]);

    $html = f07aPlay('anasheed', $id);

    expect($html)->toBe('<div class="w2a-player-status w2a-player-invalid-link" role="status"><p>'.F07A_INVALID.'</p></div>');
    f07aNoPlayer($html);
})->with(array_keys(F07A_BAD_ITEMS));

it('treats missing schemes, missing hosts and junk before the scheme as invalid (playable formats)', function (string $link) {
    f07aItem(1, $link);

    expect(f07aPlay('anasheed', 1))->toContain(F07A_INVALID)->not->toContain('<video');
})->with([
    'bare file name (no scheme)' => 'clip_x264.mp4',
    'relative directory path' => 'cartoon/amin8/clip.mp4',
    'root-relative path (no host)' => '/cartoon/amin8/clip.mp4',
    'scheme without host' => 'http:///cartoon/clip.mp4',
    'protocol only' => 'https://',
    'scheme-less host' => 'vars.way2allah.com/cartoon/clip.mp4',
    'text pasted into the field' => 'هل يمكن لغير الزوج عمل وليمة الزواج؟ | د. أحمد سيف',
    'whitespace only' => '   ',
    'mp3 without host' => 'a.mp3',
]);

// --- 2. Known malformed mirrors --------------------------------------------------------------------------------------

it('handles the real malformed mirrors: invalid notice, or the F05-2 explanation without download for legacy formats', function (string $link, string $expected) {
    f07aItem(1, 'https://archive.org/download/f/main.mp4');
    f07aMirror(30, 1, $link);

    $html = f07aPlay('anasheed_mirror', 30);

    expect($html)->toContain($expected)->not->toContain('href=');
    f07aNoPlayer($html);
})->with([
    'mirror 156 «16»' => ['16', F07A_INVALID],
    'mirror 11604 empty' => ['', F07A_INVALID],
    'mirror 18732 title in the link field' => ['هل يمكن لغير الزوج عمل وليمة الزواج؟ | د. أحمد سيف', F07A_INVALID],
    'mirror 9311 bare AVI name' => ['watha2qia-khat-anabib-alaska-9-12-2013.AVI', F07A_UNSUPPORTED_NODL],
    'mirror 9590 concatenated AVI' => ['cartoon-bit-sisan-lenegarib-al3ad-6-1-2014.AVIcartoon-bit-sisan-lenegarib-al3ad-6-1-2014.AVI', F07A_UNSUPPORTED_NODL],
    'mirror 15842 mp4+AVI concatenated' => ['watha2qia-al3alem-alda2i3-27-8-2016_x264.mp4watha2qia-al3alem-alda2i3-27-8-2016.AVI', F07A_UNSUPPORTED_NODL],
]);

// --- 3–5. Valid links keep the player -----------------------------------------------------------------------------------

it('keeps the player for valid absolute links, including unusual file names and encoded paths', function (string $link, string $src) {
    f07aItem(1, $link);

    expect(f07aPlay('anasheed', 1))->toContain('<video controls autoplay playsinline preload="metadata"><source src="'.$src.'" type="video/mp4">')
        ->not->toContain(F07A_INVALID);
})->with([
    'sibling of 16755' => ['http://vars.way2allah.com/cartoon/amin8/new-new-aby-alhanon_x264.mp4', 'http://vars.way2allah.com/cartoon/amin8/new-new-aby-alhanon_x264.mp4'],
    'double extension 21.mp4_x264.mp4' => ['http://vars.way2allah.com/cartoon/amin8/21.mp4_2_x264.mp4', 'http://vars.way2allah.com/cartoon/amin8/21.mp4_2_x264.mp4'],
    'dotted name' => ['http://www.archive.org/download/ma3a-al7abeb-fe.ramadan/ma3a-al7abeb-fe.ramadan.mp4', 'https://archive.org/download/ma3a-al7abeb-fe.ramadan/ma3a-al7abeb-fe.ramadan.mp4'],
    'percent-encoded Arabic (item 12580)' => ['https://archive.org/download/hhhmmm389_gmail_20170817_1703/%D8%A7%D8%B2%D8%A7%D9%8A%20%D8%A7%D8%AB%D8%A8%D8%AA.mp4', 'https://archive.org/download/hhhmmm389_gmail_20170817_1703/%D8%A7%D8%B2%D8%A7%D9%8A%20%D8%A7%D8%AB%D8%A8%D8%AA.mp4'],
    'query string' => ['https://cdn.example.com/a.mp4?x=1', 'https://cdn.example.com/a.mp4?x=1'],
    'upper-case scheme' => ['HTTPS://cdn.example.com/a.mp4', 'HTTPS://cdn.example.com/a.mp4'],
]);

it('keeps audio and YouTube players for valid absolute links', function () {
    f07aItem(1, 'https://archive.org/download/f/a.mp3', 0);
    f07aItem(2, 'https://www.youtube.com/watch?v=abcdefghijk');

    expect(f07aPlay('anasheed', 1))->toContain('<audio controls autoplay>')
        ->and(f07aPlay('anasheed', 2))->toContain('https://www.youtube.com/embed/abcdefghijk');
});

it('helper: playable shape and route-exact downloadability', function (?string $link, bool $playable, bool $downloadable) {
    expect(VarietyMediaLink::isPlayable($link))->toBe($playable)
        ->and(VarietyMediaLink::isDownloadable($link))->toBe($downloadable);
})->with([
    'absolute http' => ['http://vars.way2allah.com/a.mp4', true, true],
    'leading space, archive.org (normalised by the route)' => [' http://ia331419.us.archive.org/1/items/f/x.mp4', true, true],
    'leading space, other host (the route redirects into this site)' => [' http://vars.way2allah.com/a.mp4', true, false],
    'trailing space' => ['http://vars.way2allah.com/a.mp4 ', true, true],
    'host-less /download/' => ['/download/heekmaa702/t.WMV', false, false],
    'concatenated' => [F07A_BAD_ITEMS[16755], false, false],
    'junk prefix' => [F07A_BAD_ITEMS[14823], false, false],
    'empty' => ['', false, false],
    'null' => [null, false, false],
]);

// --- 10 / 11. Download buttons, routes unchanged ------------------------------------------------------------------------

it('hides «حفظ المادة» and the mirror download links exactly where the route cannot serve the link', function () {
    f07aItem(16755, F07A_BAD_ITEMS[16755]);
    f07aMirror(31, 16755, 'http://vars.way2allah.com/cartoon/amin8/new-new-al3awda_x264.mp4');   // valid mirror
    f07aMirror(32, 16755, '16');                                                                 // broken mirror
    f07aItem(2, 'http://vars.way2allah.com/cartoon/amin8/ok_x264.mp4');

    $broken = test()->get('/var-item-16755.htm')->assertOk()->getContent();
    expect($broken)->not->toContain('href="/var-download-16755.htm"')->not->toContain('حفظ المادة')
        ->and($broken)->toContain('onclick="w2a_play(16755,\'anasheed\')"')                      // the watch button stays
        ->and($broken)->toContain('href="/var-mirror-16755-31.htm" download')                    // valid mirror keeps both links
        ->and(substr_count($broken, 'href="/var-mirror-16755-31.htm"'))->toBe(2)
        ->and($broken)->not->toContain('href="/var-mirror-16755-32.htm"')                         // broken mirror: none
        ->and($broken)->toContain('<span class="w2a-quality-title">جودة 32</span>')
        ->and($broken)->toContain('w2a_play(32,\'anasheed_mirror\')');                            // its watch button stays

    $valid = test()->get('/var-item-2.htm')->assertOk()->getContent();
    expect($valid)->toContain('href="/var-download-2.htm"')->and($valid)->toContain('حفظ المادة');
});

it('hides «حفظ المادة» for a leading-space non-archive link (the route would redirect into this site)', function () {
    f07aItem(1, ' http://vars.way2allah.com/cartoon/amin8/a_x264.mp4');

    expect(test()->get('/var-item-1.htm')->assertOk()->getContent())->not->toContain('href="/var-download-1.htm"');
});

it('leaves the download routes exactly as they were (target, counter)', function () {
    f07aItem(16755, F07A_BAD_ITEMS[16755], 1, ['downcount' => 4]);
    f07aItem(6485, '', 1, ['downcount' => 0]);
    f07aItem(2, 'http://ia331419.us.archive.org/1/items/f/x.wmv', 1, ['downcount' => 9]);
    f07aMirror(32, 2, '16', 1, ['hits' => 1]);

    test()->get('/var-download-16755.htm')->assertRedirect('http://localhost/'.F07A_BAD_ITEMS[16755]);
    test()->get('/var-download-6485.htm')->assertRedirect('http://localhost');
    test()->get('/var-mirror-2-32.htm')->assertRedirect('http://localhost/16');
    // F04: a real archive.org file is now downloaded through the protected proxy (same normalised link); the malformed
    // host-less / empty links above keep their previous redirect and counter.
    expect(ProxiedDownloadProbe::resolvedLink('/var-download-2.htm'))->toBe('https://archive.org/download/f/x.wmv');

    $count = fn (string $t, int $id, string $c) => (int) DB::connection('main')->table($t)->where('id', $id)->value($c);
    expect($count('nuke_anasheed_anasheed', 16755, 'downcount'))->toBe(5)
        ->and($count('nuke_anasheed_anasheed', 2, 'downcount'))->toBe(9)
        ->and($count('nuke_anasheed_mirror', 32, 'hits'))->toBe(2);
});

// --- 15–17. F05-2 behaviour and the extended format list --------------------------------------------------------------

it('keeps the F05-2 explanation for unsupported formats (download when servable, none when not)', function () {
    f07aItem(1, 'http://ia600700.us.archive.org/12/items/w2a_folder/clip.wmv');
    f07aItem(9870, '/download/heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV');

    expect(f07aPlay('anasheed', 1))->toContain(F07A_UNSUPPORTED_DL)->toContain('href="/var-download-1.htm"')
        ->and(f07aPlay('anasheed', 9870))->toContain(F07A_UNSUPPORTED_NODL)->not->toContain('href=');
});

it('adds ASF, RAM, RA and MPG to the F05-2 explanation (Variety and Khotab)', function (string $extension) {
    f07aItem(1, 'https://archive.org/download/f/clip.'.$extension);
    H::insert('nuke_islamic_khotab', ['id' => 1, 'author' => 1, 'title' => 'خطبة', 'vedio' => 1, 'hidden' => 0, 'link' => 'https://archive.org/download/f/LECTURE.'.strtoupper($extension)]);

    expect(f07aPlay('anasheed', 1))->toContain(F07A_UNSUPPORTED_DL)->toContain('href="/var-download-1.htm"')
        ->and(f07aPlay('khotab', 1))->toContain(F07A_UNSUPPORTED_DL)->toContain('href="/khotab-download-1.htm"');
})->with(['asf', 'ram', 'ra', 'mpg']);

it('does not add 3GP, OGV, M4V or OGG (existing empty response kept)', function (string $extension) {
    f07aItem(1, 'https://archive.org/download/f/clip.'.$extension);

    expect(f07aPlay('anasheed', 1))->toBe('');
})->with(['3gp', 'ogv', 'm4v', 'ogg']);

it('leaves Khotab links outside the Variety rule (a host-less Khotab .mp4 keeps its existing player)', function () {
    H::insert('nuke_islamic_khotab', ['id' => 1, 'author' => 1, 'title' => 'خطبة', 'vedio' => 1, 'hidden' => 0, 'link' => 'a.mp4']);

    expect(f07aPlay('khotab', 1))->toContain('<video')->not->toContain(F07A_INVALID);
});

// --- 18. Visibility and permissions --------------------------------------------------------------------------------------

it('keeps hidden Variety content refused before any notice', function () {
    f07aItem(1, F07A_BAD_ITEMS[16755], 1, ['hidden' => 1]);
    f07aItem(2, 'https://archive.org/download/f/main.mp4', 1, ['hidden' => 1]);
    f07aMirror(30, 2, '16');
    f07aItem(3, 'https://archive.org/download/f/main.mp4');
    f07aMirror(31, 3, '16', 1, ['hidden' => 1]);

    expect(f07aPlay('anasheed', 1))->toBe('')
        ->and(f07aPlay('anasheed_mirror', 30))->toBe('')
        ->and(f07aPlay('anasheed_mirror', 31))->toBe('');
    test()->get('/var-item-1.htm')->assertNotFound();
});

// --- 12–14. AdminCP warnings (warning only, never blocking, never rewriting) ------------------------------------------------

it('item edit: saves an invalid link unchanged and warns on the page the admin lands on', function () {
    f07aItem(1, 'https://archive.org/download/f/a.mp4');
    $session = H::session(H::admin(['anasheed.editanasheed'], 'ed'));

    $this->withSession($session)->put(route('admin.anasheed.items.update', 1), f07aEditPayload(['Anasheed_link' => F07A_BAD_ITEMS[16755]]))
        ->assertRedirect('/var-item-1.htm')
        ->assertSessionHas(VarietyMediaLink::ADMIN_WARNING_FLASH);

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 1)->value('link'))->toBe(F07A_BAD_ITEMS[16755]);

    $page = $this->withSession($session + [VarietyMediaLink::ADMIN_WARNING_FLASH => session(VarietyMediaLink::ADMIN_WARNING_FLASH)])->get('/var-item-1.htm')->assertOk()->getContent();
    expect($page)->toContain('data-admin-link-warning')->toContain('وصلة المادة #1 المحفوظة ليست رابطاً كاملاً');
});

it('item edit: no warning for a valid link', function () {
    f07aItem(1, F07A_BAD_ITEMS[16755]);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'ed'));

    $this->withSession($session)->put(route('admin.anasheed.items.update', 1), f07aEditPayload(['Anasheed_link' => 'http://vars.way2allah.com/cartoon/amin8/21.mp4_2_x264.mp4']))
        ->assertRedirect('/var-item-1.htm')->assertSessionMissing(VarietyMediaLink::ADMIN_WARNING_FLASH);
});

it('item edit form: the inline warning follows the stored link and survives a validation redisplay', function () {
    f07aItem(1, F07A_BAD_ITEMS[14823]);
    f07aItem(2, 'https://archive.org/download/f/a.mp4');
    $session = H::session(H::admin(['anasheed.editanasheed'], 'ed'));

    expect($this->withSession($session)->get(route('admin.anasheed.items.edit', 1))->assertOk()->getContent())->toContain('data-admin-link-warning')
        ->and($this->withSession($session)->get(route('admin.anasheed.items.edit', 2))->assertOk()->getContent())->not->toContain('data-admin-link-warning');

    // A validation error (empty title) redisplays the form with the submitted, invalid link: still warned.
    $this->withSession($session)->from(route('admin.anasheed.items.edit', 2))
        ->put(route('admin.anasheed.items.update', 2), f07aEditPayload(['title' => '', 'Anasheed_link' => 'clip_x264.mp4']))
        ->assertRedirect(route('admin.anasheed.items.edit', 2))->assertSessionHasErrors('title');
    $redisplay = $this->withSession($session)->get(route('admin.anasheed.items.edit', 2))->getContent();
    expect($redisplay)->toContain('value="clip_x264.mp4"')->toContain('data-admin-link-warning')
        ->and(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 2)->value('link'))->toBe('https://archive.org/download/f/a.mp4');
});

it('item create: saves with an invalid link and warns', function () {
    $session = H::session(H::admin(['anasheed.addanasheed'], 'add'));

    $this->withSession($session)->post(route('admin.anasheed.groups.items.store', $this->group), [
        'title' => 'جديدة', 'vedio' => '1', 'Anasheedfile' => '/cartoon/amin3/x.rmvb', 'Anasheedsize' => '1',
    ])->assertRedirect("/var-group-{$this->group}.htm")->assertSessionHas(VarietyMediaLink::ADMIN_WARNING_FLASH);

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('title', 'جديدة')->value('link'))->toBe('/cartoon/amin3/x.rmvb');
});

it('mirror add and edit: save invalid links unchanged, warn after saving and on the edit form', function () {
    f07aItem(1, 'https://archive.org/download/f/a.mp4');
    $session = H::session(H::superAdmin('boss'));

    $this->withSession($session)->post(route('admin.mirrors.anasheed.store', 1), ['title' => 'بديلة', 'link' => 'cartoon.AVIcartoon.AVI', 'vedio' => '1'])
        ->assertRedirect(route('admin.mirrors.anasheed.show', 1))->assertSessionHas(VarietyMediaLink::ADMIN_WARNING_FLASH);
    $mirror = (int) DB::connection('main')->table('nuke_anasheed_mirror')->where('khid', 1)->value('id');
    expect(DB::connection('main')->table('nuke_anasheed_mirror')->where('id', $mirror)->value('link'))->toBe('cartoon.AVIcartoon.AVI');

    $shown = $this->withSession($session + [VarietyMediaLink::ADMIN_WARNING_FLASH => 'w-test'])->get(route('admin.mirrors.anasheed.show', 1))->getContent();
    expect($shown)->toContain('<div class="alert alert-warning" role="alert" data-admin-link-warning>w-test</div>');

    expect($this->withSession($session)->get(route('admin.mirrors.anasheed.edit', $mirror))->assertOk()->getContent())->toContain('data-admin-link-warning');

    $this->withSession($session)->put(route('admin.mirrors.anasheed.update', $mirror), ['title' => 'بديلة', 'link' => '16', 'vedio' => '1'])
        ->assertRedirect(route('admin.mirrors.anasheed.show', 1))->assertSessionHas(VarietyMediaLink::ADMIN_WARNING_FLASH);
    expect(DB::connection('main')->table('nuke_anasheed_mirror')->where('id', $mirror)->value('link'))->toBe('16');

    $this->withSession($session)->put(route('admin.mirrors.anasheed.update', $mirror), ['title' => 'بديلة', 'link' => 'https://archive.org/download/f/b.mp4', 'vedio' => '1'])
        ->assertSessionMissing(VarietyMediaLink::ADMIN_WARNING_FLASH);
    expect($this->withSession($session)->get(route('admin.mirrors.anasheed.edit', $mirror))->getContent())->not->toContain('data-admin-link-warning');
});

it('the warning is admin-only: visitors never see it, and an unauthorized admin cannot save', function () {
    f07aItem(1, F07A_BAD_ITEMS[16755]);

    expect(test()->get('/var-item-1.htm')->assertOk()->getContent())->not->toContain('data-admin-link-warning');

    $session = H::session(H::admin(['anasheed.addmirror'], 'weak'));
    $this->withSession($session)->put(route('admin.anasheed.items.update', 1), f07aEditPayload(['Anasheed_link' => 'x.mp4']))->assertForbidden();
    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 1)->value('link'))->toBe(F07A_BAD_ITEMS[16755]);
});

it('the message constant is the owner-approved wording', function () {
    expect(MediaPlayerService::INVALID_LINK_MESSAGE)->toBe(F07A_INVALID);
});
