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

    // Legacy-Source Reconstruction (cds-main.htm): the previous bare
    // <section aria-label="قائمة الإسطوانات"> wrapper this regex targeted
    // no longer exists — cds.php's own markup is a single portlet with no
    // sidebar at all (confirmed by a full re-read + a live raw fetch of
    // w2acd/cds.php), so a page-wide assertion is now unambiguous on its own.
    expect($content)->toContain('In Group Five')
        ->not->toContain('In Group Nine');
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

    // Scoped to the "تفاصيل الاسطوانة" portlet only — the sidebar (G-04)
    // legitimately shows this same item's thumbnail too, since
    // most_downloaded_list()/most_recent_list() never filter on `hidden`
    // either (only the detail gallery does). Legacy-Source Reconstruction:
    // the previous <section aria-label="..."> anchor this regex used is
    // gone (replaced by the real portlet markup) — re-scoped to the span
    // between the two main-column portlet captions instead.
    $detailStart = strpos($content, 'تفاصيل الاسطوانة');
    $detailEnd = strpos($content, 'روابط الاسطوانة');
    $detailSection = substr($content, $detailStart, $detailEnd - $detailStart);

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

// ---- Legacy-Source Reconstruction (cds-main.htm): page chrome, portlet, card DOM ----

it('index: renders the shared page chrome — heading differs from the document <title>, and a single-suffix title (not double)', function () {
    $response = $this->get('/cds-main.htm');

    $content = $response->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">قائمة الإسطوانات الدعوية</h3>')
        ->toContain('<title>قسم الاسطوانات الدعوية - ');
    // Single suffix: exactly one occurrence of the app name in <title>.
    $titleTag = substr($content, (int) strpos($content, '<title>'), 120);
    expect(substr_count($titleTag, config('app.name')))->toBe(1);
});

it('index: breadcrumb has one plain-href item ("الاسطوانات الدعوية"), matching cds.php\'s own url=\'\' (isset-true) breadcrumb entry', function () {
    $content = $this->get('/cds-main.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="">الاسطوانات الدعوية</a><i class=""></i></li>');
});

it('index: wraps the grid in the real w2acd_open_div() portlet — caption, double-nested portlet-body, fa-child icon', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'A CD', 'group_id' => 0]);

    $content = $this->get('/cds-main.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة الإسطوانات العامة</div>')
        ->toContain('<div class="portlet-body ">')
        ->toContain('<div class="portlet-body series-overflow series-overflow-auto">');
});

it('index: each card uses the real .var_item.cd_bg_class DOM, the cd_bg_img link class, and the confirmed malformed alt attribute', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 7, 'title' => 'Ramadan CD', 'group_id' => 0]);

    $content = $this->get('/cds-main.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('col-lg-2 col-md-3 col-sm-4 col-xs-6 text-center')
        ->toContain('<div class="var_item cd_bg_class">')
        ->toContain('<a href="/cds-item-7.htm" class="cd_bg_img">')
        // Confirmed live, byte-for-byte legacy artifact — not a typo to "fix".
        ->toContain("alt=\"إضغط لمشاهدة ''&nbsp;Ramadan CD> ''\"")
        ->toContain('<br/><span>Ramadan CD</span>');
});

// Legacy-Source Reconstruction (cds-main.htm) supersedes the two tests
// that previously stood here: `cds.php` (read in full, and confirmed
// against a live raw fetch of `w2acd/cds.php`) never calls
// `most_downloaded_recent_sidebar()`/`most_downloaded_list()`/
// `most_recent_list()` at all — that sidebar belongs to `w2acd/item.php`
// only (see this file's own `show: sidebar shows a raw...` test below,
// which correctly covers it on the page that actually has it).

it('index: renders no sidebar at all — a single, full-width portlet, matching cds.php\'s own confirmed layout', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'Only Item', 'group_id' => 0, 'hits' => 999]);

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)
        ->not->toContain('الأكثر تحميلا')
        ->not->toContain('احدث المواد')
        ->not->toContain('aria-label="الشريط الجانبي"')
        ->not->toContain('col-lg-3');
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
        ->toContain('<th class="">حفظ</th>');
});

it('show: sidebar shows a raw (non-thumbnails.php) thumbnail and subtext, same as the index page', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'Viewed', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0]);
    $db->table('nuke_w2acd_w2acd')->insert(['id' => 2, 'title' => 'Popular', 'thumbnail' => 'p.png', 'hits' => 42, 'link' => '', 'cd' => '']);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->toContain('/images/cds_image2/p.png')
        ->toContain('مرات التحميل : 42 مرة');
});

// ---- Legacy-Source Reconstruction (cds-item-{id}.htm): page chrome, portlet, date format, sidebar card DOM ----

it('show: renders the shared page chrome — heading matches the item title, and a 2-item breadcrumb linking back to cds-main.htm', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'Ramadan CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only']);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">Ramadan CD</h3>')
        ->toContain('<a href="/cds-main.htm">الاسطوانات الدعوية</a>')
        // item.php:27 — the current item's own breadcrumb entry is
        // `'url' => ''`, present-not-absent, so isset() (functions.php:530)
        // still renders a link, just to an empty href — not plain text.
        ->toContain('<a href="">Ramadan CD</a>');
    // Single-suffix title, matching cds.php's own confirmed convention.
    $titleTag = substr($content, (int) strpos($content, '<title>'), 120);
    expect(substr_count($titleTag, config('app.name')))->toBe(1);
});

it('show: wraps the detail table and mirrors list in real portlets — fa-desktop / fa-link icons, anasheed-details/anasheed-mirrors wrappers', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'A CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only']);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-desktop"></i> تفاصيل الاسطوانة</div>')
        ->toContain('<div class="anasheed-details mada-details">')
        ->toContain('<div class="caption"><i class="fa fa-link"></i> روابط الاسطوانة</div>')
        ->toContain('<div class="anasheed-mirrors table-responsive">');
});

it('show: "تاريخ التحميل" uses the real CoolShortDate() Arabic format (LegacyShortDateFormatter), not plain Y-m-d', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        'id' => 1, 'title' => 'Dated CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only',
        'mytime' => mktime(0, 0, 0, 6, 6, 2015),
    ]);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain(\App\Domain\Content\Support\LegacyShortDateFormatter::format(mktime(0, 0, 0, 6, 6, 2015)))
        ->not->toContain('2015-06-06');
});

it('show: sidebar cards use the real .list-group-item.anasheed-latest-item DOM with an <h6> title, not a bare <li>', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'title' => 'Viewed', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0],
        ['id' => 2, 'title' => 'Popular Sidebar Item', 'link' => '', 'cd' => '', 'hits' => 500],
    ]);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<li class="list-group-item anasheed-latest-item">')
        ->toContain('<div class="col-lg-3 col-md-3 col-sm-3 col-xs-4">')
        ->toContain('<div class="col-lg-9 col-md-9 col-sm-9 col-xs-8">')
        ->toContain('<h6>Popular Sidebar Item</h6>')
        ->toContain('img-responsive img-thumbnail')
        ->toContain('<ul class="recent_list">');
});

it('show: sidebar "احدث المواد" label also uses the real Arabic date format, not Y-m-d', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert([
        ['id' => 1, 'title' => 'Viewed', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 0, 'mytime' => null],
        ['id' => 2, 'title' => 'Recent Sidebar Item', 'link' => '', 'cd' => '', 'hits' => 0, 'mytime' => mktime(0, 0, 0, 6, 6, 2015)],
    ]);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('بتاريخ : '.\App\Domain\Content\Support\LegacyShortDateFormatter::format(mktime(0, 0, 0, 6, 6, 2015)))
        ->not->toContain('بتاريخ : 2015-06-06');
});

it('show: sidebar portlets use the blue top_side color variant, matching most_downloaded_list()/most_recent_list()\'s own $data', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'A CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only', 'hits' => 1]);

    $content = $this->get('/cds-item-1.htm')->assertOk()->getContent();

    expect(substr_count($content, 'class="portlet box blue top_side"'))->toBe(2);
});

// ---- Shared-nav relative-href repair (decision-log #57), sitewide audit
// finding #3, BUSINESS_REPAIR_LOW_RISK, explicitly NOT legacy parity — the
// shared nav (layouts/partials/navigation.blade.php) is rendered on this
// module's own genuinely nested-path pages (/w2acd/cds.php, /w2acd/item.php),
// where a bare-relative href resolves against /w2acd/ instead of the site
// root. See that partial's own docblock for the full evidence trail. ----

it('cds.php: the shared navigation renders no bare-relative .htm hrefs or form action — the exact defect that broke on this nested path', function () {
    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    // A bare-relative internal nav href would appear as `href="word.htm"`
    // with no leading `/` — every real nav target is now root-relative.
    expect($content)->not->toMatch('/href="[a-zA-Z][a-zA-Z0-9_.\-]*\.htm"/')
        ->and($content)->toContain('href="/categories.htm"')
        ->and($content)->toContain('href="/cds-main.htm"')
        ->and($content)->toContain('action="/search.htm"');
});

it('item.php (khid): the shared navigation is also root-relative on this second nested w2acd page', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(['id' => 1, 'title' => 'A CD', 'link' => 'https://example.com/a.mp3', 'cd' => 'Only']);

    $content = $this->get('/w2acd/item.php?khid=1')->assertOk()->getContent();

    expect($content)->not->toMatch('/href="[a-zA-Z][a-zA-Z0-9_.\-]*\.htm"/')
        ->and($content)->toContain('href="/khotab-video.htm"');
});

it('cds.php: pagination links stay intentionally local to /w2acd/ — not touched by the shared-nav repair (Class B, correctly excluded)', function () {
    DB::connection('main')->table('nuke_w2acd_w2acd')->insert(
        collect(range(1, 30))->map(fn ($i) => ['id' => $i, 'title' => "Item $i", 'group_id' => 0])->all()
    );

    $content = $this->get('/w2acd/cds.php')->assertOk()->getContent();

    expect($content)->toContain('w2acd/cds.php?page=2');
});
