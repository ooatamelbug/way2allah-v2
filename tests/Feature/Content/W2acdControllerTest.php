<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForW2acd(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2acd_w2acd' => MainSchema::nukeW2acdW2acd(),
        'nuke_w2acd_groups' => MainSchema::nukeW2acdGroups(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForW2acd();
});

it('index: IF-025 fix — a specific group\'s page only lists that group\'s items', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'group_id' => 5, 'title' => 'In Group Five'],
        ['id' => 2, 'group_id' => 9, 'title' => 'In Group Nine'],
    ]);

    $content = $this->get('/w2acd/cds.php?id=5')->assertOk()->getContent();

    // The sidebar's "Most Downloaded"/"Newest" boxes are legitimately
    // group-unscoped (the legacy $Group parameter they take is confirmed
    // dead — ContentSidebarWidget's own docblock, P-016 §2), so the
    // assertion is scoped to the main list section only.
    preg_match('/قائمة الإسطوانات">(.*?)<\/section>/s', $content, $matches);
    $listSection = $matches[1] ?? '';

    expect($listSection)->toContain('In Group Five');
    expect($listSection)->not->toContain('In Group Nine');
});

it('index: IF-025 fix — visiting a group increments THAT group\'s hits, not group 0', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->insert([
        ['id' => 0, 'title' => 'Root', 'hits' => 0],
        ['id' => 5, 'title' => 'Group Five', 'hits' => 10],
    ]);

    $this->get('/w2acd/cds.php?id=5')->assertOk();

    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(5)->hits)->toBe(11);
    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(0)->hits)->toBe(0);
});

it('index: with no id, lists group 0 (the root) and does not increment any group', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->insert(['id' => 0, 'title' => 'Root', 'hits' => 5]);
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'group_id' => 0, 'title' => 'Root Item']);

    $this->get('/w2acd/cds.php')->assertOk()->assertSee('Root Item');

    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(0)->hits)->toBe(5);
});

/**
 * G-11-01 (Phase 1 audit) — cds-main.htm is a real, permanent sitewide
 * nav link (header.php's "إسطوانات دعوية" dropdown item, reproduced
 * verbatim in navigation.blade.php) with no Laravel route until now.
 * Proves it reaches the exact same W2acdController::index() behavior as
 * the raw /w2acd/cds.php path, not a duplicate implementation.
 */
it('G-11-01: GET /cds-main.htm reaches the same W2acdController::index() behavior as /w2acd/cds.php, no group id', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->insert(['id' => 0, 'title' => 'Root', 'hits' => 5]);
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'group_id' => 0, 'title' => 'Root Item']);

    $response = $this->get('/cds-main.htm');

    $response->assertOk()->assertSee('Root Item')->assertViewIs('w2acd.index');
    expect(DB::connection('main')->table('nuke_w2acd_groups')->find(0)->hits)->toBe(5);
});

it('show: renders item details, mirror links, and increments hits', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'A CD', 'link' => 'https://example.com/a.mp3,https://example.com/b.mp3',
        'cd' => 'Part 1,Part 2', 'hits' => 3,
    ]);

    $response = $this->get('/w2acd/item.php?khid=1');

    $response->assertOk()->assertSee('A CD')->assertSee('Part 1')->assertSee('Part 2');
    expect(DB::connection('main')->table('nuke_w2acd_w2acd')->find(1)->hits)->toBe(4);
});

it('show: sidebar links use the correct cds-item- prefix, not the legacy var-item- bug', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'title' => 'Viewed Item', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0],
        ['id' => 2, 'title' => 'Popular Item', 'link' => '', 'cd' => '', 'hits' => 999],
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('cds-item-2.htm');
    expect($content)->not->toContain('var-item-');
});

it('show: hidden items remain viewable but suppress the image gallery, matching legacy exactly', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Hidden CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only',
        'thumbnail' => 'a.jpg', 'hidden' => 1,
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    // Scoped to the detail section only — the sidebar (G-04) legitimately shows
    // this same item's thumbnail too, since most_downloaded_list()/most_recent_list()
    // never filter on `hidden` either (only the detail gallery does).
    preg_match('/تفاصيل الاسطوانة">(.*?)<\/section>/s', $content, $matches);
    $detailSection = $matches[1] ?? '';

    expect($content)->toContain('Hidden CD');
    expect($detailSection)->not->toContain('cds_image2/a.jpg');
});

// ---- G-04 (Migration Gap Register): thumbnails.php parity + sidebar + mirror classification ----

it('index: listing thumbnail routes through thumbnails.php at the exact legacy 104x105 dimensions', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'A CD', 'thumbnail' => 'first.png,second.png', 'group_id' => 0,
    ]);

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=104&amp;w=105&amp;src=/images/cds_image2/first.png');
});

it('index: an item with no thumbnail falls back to way2_cddefault.png, still thumbnails.php-wrapped', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'No Thumb CD', 'thumbnail' => '', 'group_id' => 0,
    ]);

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=104&amp;w=105&amp;src=/images/way2_cddefault.png');
});

it('index: loads the module CSS (gallery.css) already reachable via the existing assets symlink', function () {
    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('/assets/frontend/pages/css/gallery.css');
});

it('index: sidebar shows a raw (non-thumbnails.php) thumbnail and the exact legacy subtext for both lists', function () {
    // group_id=9 (not the default 0 the page lists) so these rows appear ONLY via the
    // group-unscoped sidebar queries, not also in the main listing section (which
    // legitimately DOES thumbnails.php-wrap — isolating the assertion to prove the
    // sidebar copy specifically stays raw).
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'group_id' => 9, 'title' => 'Popular', 'thumbnail' => 'p.png', 'hits' => 42, 'mytime' => null],
        ['id' => 2, 'group_id' => 9, 'title' => 'Newest', 'thumbnail' => 'n.png', 'hits' => 0, 'mytime' => mktime(0, 0, 0, 6, 15, 2020)],
    ]);

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('/images/cds_image2/p.png')
        ->toContain('/images/cds_image2/n.png')
        ->not->toContain('/thumbnails.php?h=104&amp;w=105&amp;src=/images/cds_image2/p.png') // sidebar copy must stay raw
        ->toContain('مرات التحميل : 42 مرة')
        ->toContain('بتاريخ : 2020-06-15');
});

it('index: sidebar falls back to tvnoise.gif when an item has no thumbnail', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'No Thumb', 'thumbnail' => '', 'hits' => 5]);

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('/images/tvnoise.gif');
});

it('show: detail images route through thumbnails.php at h=400&w=400&zc=0&q=100, first image distinct from the rest', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Gallery CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only',
        'thumbnail' => 'first.png,second.png', 'hidden' => 0,
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=400&amp;w=400&amp;zc=0&amp;q=100&amp;src=/images/cds_image2/first.png')
        ->toContain('/thumbnails.php?h=400&amp;w=400&amp;zc=0&amp;q=100&amp;src=/images/cds_image2/second.png')
        ->toContain('height="350" width="400"') // first image's distinct display size
        ->toContain('height="400" width="400"'); // subsequent images
});

it('show: mirror extension classification — "سيرفر خاص" for empty/com/html/htm/php? links, an icon otherwise', function () {
    // "http://localhost/noext" is deliberately dot-free everywhere (no TLD dot, no
    // filename dot) so getExtension()'s strrpos('.') genuinely finds nothing —
    // a domain like "example.com" would itself supply a trailing ".com/noext"
    // "extension" (real legacy behavior, not empty), which would defeat this case.
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Mixed Mirrors', 'hidden' => 0,
        'link' => 'https://forums.example.com/showthread.php?t=196342,https://example.com/file.iso,http://localhost/noext',
        'cd' => 'Forum Thread,ISO File,No Extension',
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect(substr_count($content, 'سيرفر خاص'))->toBe(2) // php? link + no-extension link
        ->and($content)->toContain('/images/ext/iso.gif')
        ->toContain('نوع الملف iso');
});

it('show: mirror save column — icon-only (no link) when the extension is empty, an anchor-wrapped icon otherwise', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Save Column CD', 'hidden' => 0,
        'link' => 'https://example.com/file.iso,http://localhost/noext',
        'cd' => 'Has Extension,No Extension',
    ]);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('/images/save.png')
        ->toContain('/images/2.png')
        ->toContain('<th>حفظ</th>');
});

it('show: sidebar shows a raw (non-thumbnails.php) thumbnail and subtext, same as the index page', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'Viewed', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0]);
    $db->table('nuke_w2acd_w2acd')->insert(['id' => 2, 'title' => 'Popular', 'thumbnail' => 'p.png', 'hits' => 42, 'link' => '', 'cd' => '']);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('/images/cds_image2/p.png')
        ->toContain('مرات التحميل : 42 مرة');
});
