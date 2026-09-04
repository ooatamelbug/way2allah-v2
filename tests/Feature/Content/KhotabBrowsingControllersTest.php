<?php

use App\Domain\Content\Support\LegacyDurationFormatter;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabBrowsing(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabBrowsing();
});

// ---- IF-015: series.php's "Most Downloaded" sidebar now always uses the series' own author ----

it('series show: IF-015 fix — an UNGROUPED series still shows its author\'s "Most Downloaded" items, not an empty result', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'ser_id' => 10, 'group_id' => 0, 'title' => 'Series Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 0],
        ['id' => 2, 'author' => 1, 'ser_id' => 0, 'group_id' => 0, 'title' => 'Author Top Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 50],
    ]);

    $response = $this->get('/khotab-series-10.htm');

    $response->assertOk()->assertSee('Author Top Item');
});

it('series show: 404s for a hidden series', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'title' => 'Hidden', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab-series-10.htm')->assertNotFound();
});

// ---- Shared Page Chrome Parity Audit: series.php:36,62-79's heading/breadcrumb ----

it('series show: renders the heading and the full author/group/series breadcrumb chain, video op', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 5, 'title' => 'A Group']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 1, 'group_id' => 5, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-series-10.htm')->assertOk()->getContent();

    // series.php:36's real document title AND visible heading are the same string.
    expect($content)->toContain('<title>سلسلة A Series - Sheikh Author - ')
        ->and($content)->toContain('<h3 class="page-title">سلسلة A Series - Sheikh Author</h3>');

    expect($content)
        ->toContain('<li><a href="/khotab-video.htm">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/khotab-video.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/khotab-video-1.htm">Sheikh Author</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/khotab-group-5.htm">مجموعة A Group</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="">سلسلة A Series</a><i class=""></i></li>');

    // Ordering: op label, then author list, then author, then group, then series (current).
    $order = ['المرئيات</a>', 'قائمة الدعاة</a>', 'Sheikh Author</a>', 'مجموعة A Group</a>', 'سلسلة A Series</a>'];
    $positions = array_map(fn ($needle) => strpos($content, $needle), $order);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('series show: audio op uses الصوتيات/khotab-audio.htm throughout, and omits the group segment when ungrouped', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 2, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 11, 'author_id' => 2, 'group_id' => 0, 'title' => 'B Series', 'vedio' => 0, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-series-11.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<li><a href="/khotab-audio.htm">الصوتيات</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/khotab-audio-2.htm">Sheikh Author</a><i class="fa fa-angle-right"></i></li>')
        ->not->toContain('مجموعة');
});

// ---- khotab-fatwa-{id}.htm Author Route Reconciliation (decision-log
// #48): authors.php:80's per-author `<a>` uses `$op` uniformly across all
// 4 ops (video/audio/pdf/fatwa) to build `khotab-{op}-{id}.htm` — the
// Laravel reproduction below is byte-faithful to that real legacy line.
// See KhotabDeadRoutesTest.php for why the resulting `khotab-fatwa-*`
// link is real-but-terminal (SOURCE_UNRECOVERABLE), not a migration bug. ----

it('fatawa-authors.htm (op=fatwa) generates real khotab-fatwa-{id}.htm links, byte-faithful to authors.php:80 — not a migration typo/bug', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 17, 'name' => 'الحويني', 'prename' => 'الشيخ', 'fatwa' => 12, 'hidden' => 0,
    ]);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('href="/khotab-fatwa-17.htm"')
        ->toContain('الحويني')
        ->toContain('12 فتوى');
});

it('author directory: renders searchable alphabetical card groups without inline jQuery navigation', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'أحمد', 'prename' => 'الشيخ', 'vedio' => 5, 'hidden' => 0],
        ['id' => 2, 'name' => 'محمد', 'prename' => 'الدكتور', 'vedio' => 3, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($content)->toContain('id="w2a_author_search_input"')
        ->toContain('class="w2a-alphabet-nav"')
        ->toContain('class="w2a-preacher-card"')
        ->toContain('data-name="الشيخ أحمد"')
        ->toContain('5 فيديو')
        ->not->toContain("$('.abc').html");
});

it('fatawa-authors.htm only lists authors with fatwa > 0, matching authors.php:24\'s real WHERE clause', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Has Fatwa', 'fatwa' => 5, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Fatwa', 'fatwa' => 0, 'hidden' => 0],
    ]);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)->toContain('Has Fatwa')->not->toContain('No Fatwa');
});

// ---- group.php (no bug — sanity check it still renders) ----

it('group show: renders series and items scoped to the group', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'group_id' => 20, 'title' => 'Group Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $this->get('/khotab-group-20.htm')->assertOk()->assertSee('Group Item');
});

it('group show: G-13-11 — the series list shows a channel icon only when the series has a channel_id, matching ListSeries() exactly', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 1, 'author_id' => 1, 'group_id' => 20, 'title' => 'With Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9],
        ['id' => 2, 'author_id' => 1, 'group_id' => 20, 'title' => 'No Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0],
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png');
    // "No Channel" row must not accidentally render a channels/0.png link.
    expect(substr_count($content, 'images/channels/'))->toBe(1);
});

// ---- G-13-03 (media/visual parity phase): the "الملف الشخصي" author-photo box was missing entirely on series/group ----

it('series show: G-13-03 — renders the previously-missing "الملف الشخصي" author-photo box, ignoring author_image (series.php\'s own unconditional get_author_img())', function () {
    // id 999999 is deliberately far outside any real bucket in the now-populated
    // media library (authors/ only has bucket 0/), so this can't collide with a
    // genuine media/authors/sq/{id}.png and silently take the "real file" branch.
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 999999, 'name' => 'Author', 'author_image' => 'https://example.com/custom.png',
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 10, 'author_id' => 999999, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-series-10.htm')->assertOk()->getContent();

    expect($content)->toContain('الملف الشخصي')
        ->and($content)->not->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

it('group show: G-13-03 — renders the previously-missing "الملف الشخصي" author-photo box, ignoring author_image (group.php\'s own unconditional get_author_img())', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 999999, 'name' => 'Author', 'author_image' => 'https://example.com/custom.png',
    ]);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        'id' => 20, 'author_id' => 999999, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('الملف الشخصي')
        ->and($content)->not->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

// ---- Full Design Parity Pass (khotab-group-{id}.htm) ----

function seedKhotabGroupParityFixture(): void
{
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Al-Sawy', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Tafsir Group', 'vedio' => 1, 'hidden' => 0, 'description' => 'Group notes']);
    $db->table('nuke_islamic_series')->insert([
        'id' => 30, 'author_id' => 1, 'group_id' => 20, 'title' => 'Juz Tabarak', 'vedio' => 1, 'hidden' => 0,
        'count' => 32, 'time' => mktime(0, 0, 0, 11, 15, 2010), 'lastupdate' => mktime(0, 0, 0, 12, 31, 2010), 'channel_id' => 9,
    ]);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 40, 'author' => 1, 'group_id' => 20, 'ser_id' => 0, 'title' => 'Surah Al-Bayyina', 'vedio' => 1, 'hidden' => 0,
        'comments' => 1, 'hits' => 2529, 'time' => mktime(0, 0, 0, 12, 19, 2006), 'channel_id' => 9,
    ]);
    $db->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Iqraa']);
}

it('group show: renders the shared page chrome — heading includes the author name, and the real 4-item video breadcrumb', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<h3 class="page-title">مجموعة Tafsir Group - Dr. Al-Sawy</h3>')
        ->toContain('<a href="/khotab-video.htm">المرئيات</a>')
        ->toContain('<a href="/khotab-video.htm">قائمة الدعاة</a>')
        ->toContain('<a href="/khotab-video-1.htm">Dr. Al-Sawy</a>')
        ->toContain('<a href="">مجموعة Tafsir Group</a>');
});

// ---- Title Gap Closure (2026-08-22): group.php's own $title never
// includes the sitename — header.php's own unconditional append is the
// only one, exactly once. The prior test above only checked a PREFIX of
// the <title> tag, which passed regardless of a single or double suffix —
// this asserts the complete tag exactly, guarding against that regression. ----

it('group show: document title is exactly "مجموعة {group} - {author} - {sitename}", single suffix, not doubled', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 120);
    expect($titleTag)->toContain('<title>مجموعة Tafsir Group - Dr. Al-Sawy - '.config('app.name').'</title>')
        ->and(substr_count($titleTag, (string) config('app.name')))->toBe(1);
});

it('group show: audio group uses الصوتيات/khotab-audio.htm throughout the breadcrumb', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sh.']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Audio Group', 'vedio' => 0, 'hidden' => 0]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    // 'المرئيات' alone would false-positive against the sitewide nav
    // menu's own standing link — scope the check to the breadcrumb itself.
    preg_match('/<ul class="page-breadcrumb">(.*?)<\/ul>/s', $content, $matches);
    $breadcrumbHtml = $matches[1] ?? '';

    expect($breadcrumbHtml)
        ->toContain('<a href="/khotab-audio.htm">الصوتيات</a>')
        ->toContain('<a href="/khotab-audio-1.htm">Sh. Author</a>')
        ->not->toContain('المرئيات');
});

it('group show: all 6 portlets use fa-child uniformly, matching this page\'s own confirmed convention (not the varied icons other pages use)', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect(substr_count($content, '<i class="fa fa-child"></i>'))->toBe(6);
});

it('group show: the Series portlet reproduces the real double-nested portlet-body, id="tableser", and full metadata row (date/lastupdate/count/channel)', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="portlet-body ">')
        ->toContain('<div class="portlet-body series-overflow">')
        ->toContain('id="tableser"')
        ->toContain('<a href="/khotab-series-30.htm">Juz Tabarak</a>')
        ->toContain('<i class="fa fa-calendar"></i> 2010-11-15')
        ->toContain('<i class="fa fa-refresh"></i> 2010-12-31')
        ->toContain('<i class="fa fa-play-circle-o"></i> المواد: 32')
        ->toContain('<i class="fa fa-television"></i> القناة:')
        ->toContain('/images/channels/9.png');
});

it('group show: the Series portlet shows the real empty-state text when the group has no series, not an omitted block', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Empty Group', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('لا توجد سلاسل مطابقة بقاعدة بيانات الموقع');
});

it('group show: the Khotab items portlet shows date/comments/hits/channel/duration but NEVER an author link — a confirmed difference from categories\' own ListKhotab()', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('id="tabelkht"')
        ->toContain('<a href="/khotab-item-40.htm">Surah Al-Bayyina</a>')
        ->toContain('<i class="fa fa-calendar"></i> 2006-12-19')
        ->toContain('<i class="fa fa-commenting-o"></i> التعليقات: 1')
        ->toContain('<i class="fa fa-eye"></i> مشاهدات: 2,529')
        ->not->toContain('الداعية:');
});

it('group show: the Khotab items portlet shows the real empty-state text when the group has no items', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Empty Group', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('لا توجد مواد مطابقة بقاعدة بيانات الموقع');
});

it('group show: BOTH "الأكثر تحميلا" and "جديد المواد" show the download-count label, matching group.php\'s own confirmed mode=\'hits\' call for both — not a date on the second box', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Group', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Most Downloaded Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 500, 'time' => time()],
        ['id' => 2, 'author' => 1, 'title' => 'Newest Item', 'vedio' => 1, 'hidden' => 0, 'hits' => 42, 'time' => time()],
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    // Both fixture items qualify for both LIMIT-5 boxes (only 2 rows
    // exist), so the label appears once per item per box (4 total) —
    // the real point of this test is that NEITHER box ever shows a date.
    expect(substr_count($content, 'عدد مرات التحميل:'))->toBe(4);
    expect($content)->not->toContain('بتاريخ:');
});

it('group show: registers the DataTables assets (core + bootstrap plugin + khotab_tables.js), matching this page\'s own confirmed live asset profile', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('datatables.min.css')
        ->toContain('datatables.bootstrap-rtl.css')
        ->toContain('datatables.min.js')
        ->toContain('datatables.bootstrap.js')
        ->toContain('/scripts/khotab_tables.js')
        // Title/DataTables Gap Closure (2026-08-22): assets/global/scripts/
        // datatable.js investigated and confirmed CONFIGURED_BUT_INERT —
        // khotab_tables.js is fully self-contained and never references
        // the global Datatable wrapper class that file defines. Guards
        // against it being silently re-added without re-verifying that.
        ->not->toContain('global/scripts/datatable.js');
});

it('group show: the description portlet uses the real w2a_open_div() wrapper, not a bare <section>', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('<p>Group notes</p>');
});

// ---- IF-016 + IF-022: day.php ----

// ---- Title Gap Closure (2026-08-22): day.php's real $header['title'] is
// the plain, date-independent 'المرئيات '/'الصوتيات ' string (day.php:10-19,
// 24-25) — NOT the breadcrumb's date-label text, which IF-016's original
// premise had wrongly conflated with the document title. The breadcrumb
// text itself (asserted separately below) is unchanged by this fix. ----

it('day: video-today document title is the plain "المرئيات ", not a date string', function () {
    $content = $this->get('/khotab-video-today.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 60);
    expect($titleTag)->toContain('<title>المرئيات  - ')
        ->not->toContain('المواد المنشورة بتاريخ')
        ->not->toContain(date('Y-m-d'));
});

it('day: audio-today document title is the plain "الصوتيات ", not a date string', function () {
    $content = $this->get('/khotab-audio-today.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 60);
    expect($titleTag)->toContain('<title>الصوتيات  - ')
        ->not->toContain('المواد المنشورة بتاريخ');
});

it('day: explicit video/audio date routes use the same plain document title, independent of the date in the URL', function () {
    $video = $this->get('/khotab-videodate-15-1-2020.htm')->assertOk()->getContent();
    $audio = $this->get('/khotab-audiodate-15-1-2020.htm')->assertOk()->getContent();

    expect(substr($video, (int) strpos($video, '<title>'), 60))->toContain('<title>المرئيات  - ');
    expect(substr($audio, (int) strpos($audio, '<title>'), 60))->toContain('<title>الصوتيات  - ');
});

// ---- Shared Page Chrome Parity Audit: day.php:90-98's breadcrumb, heading deliberately omitted ----

it('day: renders no <h3 class="page-title"> at all — LEGACY_BUG_NOT_FOR_REPRODUCTION for the confirmed $Author-null empty-heading bug', function () {
    $content = $this->get('/khotab-video-today.htm')->assertOk()->getContent();

    expect($content)->not->toContain('page-title');
});

it('day: breadcrumb chain — المرئيات (linked) → تقسيم المواد بالتاريخ (plain) → current date label (empty-href, "اليوم - " prefixed)', function () {
    $content = $this->get('/khotab-video-today.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="/khotab-video.htm">المرئيات </a><i class="fa fa-angle-right"></i></li>');
    // "تقسيم المواد بالتاريخ" has no `url` key in legacy — plain text, not a link.
    expect($content)->not->toContain('<a href="">تقسيم المواد بالتاريخ</a>');
    expect($content)->toMatch('/<li>تقسيم المواد بالتاريخ<i class="fa fa-angle-right"><\/i><\/li>/');
    expect($content)->toContain('اليوم - ');

    $audio = $this->get('/khotab-audio-today.htm')->assertOk()->getContent();
    expect($audio)->toContain('<li><a href="/khotab-audio.htm">الصوتيات </a><i class="fa fa-angle-right"></i></li>');
});

it('day: IF-022 fix — a dated URL scopes the main list to that date\'s items, not today\'s', function () {
    // Sidebar "Most Downloaded"/"Newest" boxes are global (unscoped by
    // date), matching legacy exactly — so this asserts within the
    // "قائمة المواد" list section specifically, not the full page, since a
    // page-wide assertDontSee would be defeated by the (correctly)
    // date-unscoped sidebar showing every vedio=1 item regardless.
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);

    $oldDay = mktime(0, 0, 0, 1, 15, 2020);
    $today = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Old Day Item', 'vedio' => 1, 'hidden' => 0, 'time' => $oldDay + 100],
        ['id' => 2, 'author' => 1, 'title' => 'Today Item', 'vedio' => 1, 'hidden' => 0, 'time' => $today + 100],
    ]);

    $content = $this->get('/khotab-videodate-15-1-2020.htm')->assertOk()->getContent();

    // khotab-video-today.htm parity batch: the "قائمة المواد" portlet is now
    // the real ListKhotab()/tabelkht table markup, not a plain <section>, so
    // the boundary is "up to the sidebar <aside>" rather than "up to the
    // next </section>" — it's still the only main-content portlet on this
    // page, so this remains an unambiguous items-list-only slice.
    preg_match('/قائمة المواد.*?(?=<aside)/s', $content, $matches);
    $listSection = $matches[0] ?? '';

    expect($listSection)->toContain('Old Day Item');
    expect($listSection)->not->toContain('Today Item');
});

it('day: khotab-video-today.htm parity — 4 portlets, datepicker assets loaded, empty-state message when no items today, "newest" box shows a date not a hit count', function () {
    $response = $this->get('/khotab-video-today.htm');
    $content = $response->assertOk()->getContent();

    expect(substr_count($content, 'portlet-title'))->toBe(4)
        ->and($content)->toContain('بحث بالتاريخ')
        ->and($content)->toContain('bootstrap-datepicker3.min.css')
        ->and($content)->toContain('bootstrap-datepicker.js')
        ->and($content)->toContain('scripts/khotab_date.js')
        ->and($content)->toContain("\$('#form_datetime_1').datepicker(")
        ->and($content)->toContain('لا توجد مواد مطابقة بقاعدة بيانات الموقع');
});

it('day: "جديد المواد" box uses mode=\'time\' (a formatted date), unlike khotab-series-{id}.htm\'s always-\'hits\' boxes', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 0, 'title' => 'Recent Item', 'vedio' => 1, 'hidden' => 0,
        'time' => mktime(0, 0, 0, 6, 28, 2026), 'hits' => 5,
    ]);

    $content = $this->get('/khotab-video-today.htm')->assertOk()->getContent();

    expect($content)->toContain('بتاريخ: الأحد 28 يونيو 2026 مـ');
});

// ---- IF-017 + IF-021: news.php / author.php pdf-op sidebars ----

it('news: IF-017 fix — the pdf op\'s "Most Downloaded" sidebar is scoped to pdf content, not coerced to audio', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 5],
        ['id' => 2, 'author' => 1, 'title' => 'Unrelated Audio Item', 'vedio' => 0, 'pdf' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-pdf_news.htm');

    $response->assertOk()->assertSee('PDF Item')->assertDontSee('Unrelated Audio Item');
});

it('author show: G-13-11 — group/series lists show a channel icon only when channel_id is set, matching ListGroup()/ListSeries() exactly', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0]);
    // groupsByAuthor() inner-joins nuke_islamic_khotab on group_id — a real row is required for the group to appear at all.
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
});

it('author show: IF-021 fix — the pdf op\'s sidebar is scoped to this author\'s pdf items, not coerced to audio', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author One']);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 2, 'name' => 'Author Two']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Author One PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 5],
        ['id' => 2, 'author' => 2, 'title' => 'Author Two PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0, 'hits' => 99],
        ['id' => 3, 'author' => 1, 'title' => 'Author One Audio Item', 'vedio' => 0, 'pdf' => 0, 'hidden' => 0, 'hits' => 99],
    ]);

    $response = $this->get('/khotab-pdf-1.htm');

    $response->assertOk()
        ->assertSee('Author One PDF')
        ->assertDontSee('Author Two PDF')
        ->assertDontSee('Author One Audio Item');
});

// ---- Visual parity audit (khotab-video-17.htm, 2026-08-18): author show() Batch 1 — page-title/breadcrumb/portlet-wrapper/promo-banner restored, previously missing ----

it('author show: renders author.php:56\'s <h3 class="page-title"> and the matching <title> tag, one phrase per op (video/audio/pdf)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Video Item', 'vedio' => 1, 'pdf' => 0, 'pdf_time' => 0, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Audio Item', 'vedio' => 0, 'pdf' => 0, 'pdf_time' => 0, 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'title' => 'PDF Item', 'vedio' => 0, 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video-1.htm')->assertOk()->getContent();
    expect($video)->toContain('<h3 class="page-title">مرئيات Sheikh Author</h3>')
        ->and($video)->toContain('<title>مرئيات Sheikh Author - ')
        ->not->toContain('class=a fa-gift');

    $audio = $this->get('/khotab-audio-1.htm')->assertOk()->getContent();
    expect($audio)->toContain('<h3 class="page-title">صوتيات Sheikh Author</h3>');

    $pdf = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();
    // Legacy's own literal double space (functions.php's 'المواد المفرغة لـ  ' — not a typo, reproduced as-is).
    expect($pdf)->toContain('<h3 class="page-title">المواد المفرغة لـ  Sheikh Author</h3>');
});

it('author show: renders author.php:53-57\'s breadcrumb — first two segments both link to /khotab-{op}.htm, final segment is the author\'s own name with href=""', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<li><a href="/khotab-video.htm">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->and($content)->toContain('<li><a href="/khotab-video.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>')
        ->and($content)->toContain('<li><a href="">Sheikh Author</a><i class=""></i></li>');
});

it('author show: every section (groups/series/items + all 4 always-present sidebar widgets) is wrapped in .portlet.box.blue with the correct fa-child icon and legacy caption text', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة المجموعات</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>')
        ->and($content)->toContain('<div class="caption"><i class="fa fa-child"></i> جديد المواد</div>')
        // 7 always-present portlets for a video/audio op with no description (the promo banner is the 8th, checked separately below).
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(8);
});

it('author show: the pdf op has no promo-banner widget (legacy author.php:110-138 only has video/audio branches) — 7 portlets, not 8', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $content = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('images/video.gif')
        ->and($content)->not->toContain('images/audio.gif')
        // pdf op: no groups/series portlets (op !== 'pdf' gate) and no
        // promo banner — just "قائمة المواد" + the 4 always-present
        // sidebar widgets (profile/"اخترنا لك"/"الأكثر تحميلا"/"جديد المواد").
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(5);
});

it('author show: renders the video/audio promotional banner (author.php:110-138) with the correct image/dimensions/self-link, previously missing entirely', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 42, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 42, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 42, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video-42.htm')->assertOk()->getContent();
    expect($video)->toContain('<div class="caption"><i class="fa fa-child"></i> مرئيات الداعية</div>')
        ->and($video)->toContain('<div class="portlet-body text-center">')
        ->and($video)->toContain('<a href="/khotab-video-42.htm">')
        ->and($video)->toContain('<img border="0" src="/images/video.gif" width="192" height="71" alt="">');

    $audio = $this->get('/khotab-audio-42.htm')->assertOk()->getContent();
    expect($audio)->toContain('<div class="caption"><i class="fa fa-child"></i> صوتيات الداعية</div>')
        ->and($audio)->toContain('<a href="/khotab-audio-42.htm">')
        ->and($audio)->toContain('<img border="0" src="/images/audio.gif" width="192" height="71" alt="">');
});

it('author show: the description block (when present) is wrapped in .portlet.box.blue with NO caption/icon header, matching author.php:80-90\'s own hand-rolled markup (unlike every other portlet on this page)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'description' => 'A biography.']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('A biography.')
        // 8 base (video op, no group/series/item channel data needed here) + 1 description portlet.
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(9)
        // The description portlet has no portlet-title/caption at all — one
        // fewer portlet-title than total portlets (9 portlets, 8 titles).
        ->and(substr_count($content, 'class="portlet-title"'))->toBe(8);
});

it('author show: no duplicate element ids on the page', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    preg_match_all('/\sid="([^"]+)"/', $content, $matches);
    $ids = $matches[1];

    expect($ids)->toBe(array_unique($ids));
});

// ---- Visual parity audit (khotab-video-17.htm, 2026-08-18): author show() Batch 2 — Groups/Series/Items rich row markup restored, previously simplified ----

it('author show: renders ListGroup()\'s exact row markup (khotab/functions.php:360-402) — table#tabelgrp, count with fa-play-circle-o, channel badge only when channel_id is set', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'author_id' => 1, 'title' => 'With Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9],
        ['id' => 2, 'author_id' => 1, 'title' => 'No Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item A', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'group_id' => 1, 'title' => 'Item B', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'author' => 1, 'group_id' => 2, 'title' => 'Item C', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tabelgrp">')
        ->and($content)->toContain('<i class="fa fa-play-circle-o"></i>')
        ->and($content)->toContain('المواد:')
        ->and($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
});

it('author show: renders ListSeries()\'s exact row markup (khotab/functions.php:452-495) — table#tableser, date + last-updated + count + conditional channel badge', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 3, 15, 2026);
    $lastupdate = mktime(0, 0, 0, 4, 1, 2026);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
        'count' => 7, 'channel_id' => 9, 'time' => $time, 'lastupdate' => $lastupdate,
    ]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tableser">')
        ->and($content)->toContain('<i class="fa fa-calendar"></i>')
        ->and($content)->toContain(date('Y-m-d', $time))
        ->and($content)->toContain('<i class="fa fa-refresh"></i>')
        ->and($content)->toContain(date('Y-m-d', $lastupdate))
        ->and($content)->toContain('<i class="fa fa-play-circle-o"></i>')
        ->and($content)->toContain('المواد:')
        ->and($content)->toContain('/images/channels/9.png');
});

it('author show: renders ListKhotab()\'s exact default-branch row markup (khotab/functions.php:643-706) — table#tabelkht, date/comments/views always shown, channel badge and duration conditional', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 4, 22, 2026);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'An Item', 'vedio' => 1, 'hidden' => 0,
        'comments' => 4, 'hits' => 1872, 'channel_id' => 9, 'time' => $time,
    ]);
    DB::connection('main')->table('nuke_islamic_advanced')->insert(['id' => 1, 'adur' => 3621662]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<table class="table table-striped table-hover" id="tabelkht">')
        ->and($content)->toContain('<i class="fa fa-calendar"></i>')
        ->and($content)->toContain(date('Y-m-d', $time))
        ->and($content)->toContain('<i class="fa fa-commenting-o"></i>')
        ->and($content)->toContain('التعليقات:')
        ->and($content)->toContain('4')
        ->and($content)->toContain('<i class="fa fa-eye"></i>')
        ->and($content)->toContain('مشاهدات:')
        ->and($content)->toContain(number_format(1872))
        ->and($content)->toContain('/images/channels/9.png')
        ->and($content)->toContain('<i class="fa fa-clock-o"></i>')
        // Verified against real olddb data (khotab-item-158635, adur=3621662ms) — matches live legacy's displayed "01:00:21" exactly.
        ->and($content)->toContain('01:00:21');
});

it('author show: an item with no nuke_islamic_advanced row (adur missing) never shows a duration — LegacyDurationFormatter(0) is "00:00:00", hidden per ListKhotab()\'s own check', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'No Advanced Row', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('fa-clock-o');
});

it('LegacyDurationFormatter::format(): matches Duration() (functions.php:357-365) exactly, verified against 2 real olddb items\' raw adur values', function () {
    // khotab-item-158635 (>1hr branch, 12-hour "h" format — legacy's own date("h:i:s",...), not 24-hour).
    expect(LegacyDurationFormatter::format(3621662))->toBe('01:00:21')
        // khotab-item-158739 (<=1hr branch, "00:i:s").
        ->and(LegacyDurationFormatter::format(3405995))->toBe('00:56:45')
        ->and(LegacyDurationFormatter::format(0))->toBe('00:00:00');
});

it('author show: pdf op\'s item list still renders (khotabPdfItemsByAuthor() has no adur column) — no fatal error, duration silently omitted', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $content = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();

    expect($content)->toContain('PDF Item')
        ->and($content)->not->toContain('fa-clock-o');
});

it('authors index: lists authors with a positive count for the requested op', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Video Author', 'vedio' => 3, 'hidden' => 0],
        ['id' => 2, 'name' => 'No Video Author', 'vedio' => 0, 'hidden' => 0],
    ]);

    $response = $this->get('/khotab-video.htm');

    $response->assertOk()->assertSee('Video Author')->assertDontSee('No Video Author');
});

it('authors index: G-13-03 — each row renders a photo, preferring author_image over get_author_img() (matches authors.php:77\'s own ternary)', function () {
    // Ids deliberately far outside any real bucket in the now-populated media
    // library, so the "no custom image" row can't collide with a genuine
    // media/authors/sq/{id}.png and silently take the "real file" branch.
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 999998, 'name' => 'Has Custom Image', 'vedio' => 1, 'hidden' => 0, 'author_image' => 'https://example.com/custom.png'],
        ['id' => 999999, 'name' => 'No Custom Image', 'vedio' => 1, 'hidden' => 0, 'author_image' => null],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($content)->toContain('https://example.com/custom.png')
        ->and($content)->toContain('/media/authors/no_author_image.png');
});

// ---- Visual parity audit (khotab-video.htm, 2026-08-18): authors.php's page-title/breadcrumb/portlet/grouping/A-Z-nav/count, previously entirely missing ----

it('authors index: renders authors.php\'s page-title, breadcrumb, and portlet wrapper, op-specific per authors.php:6-27', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'vedio' => 1, 'hidden' => 0]);

    $video = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($video)->toContain('<title>قسم المرئيات - ')
        ->and($video)->toContain('<h3 class="page-title">قائمة الدعاة بقسم المرئيات</h3>')
        ->and($video)->toContain('<div class="page-bar">')
        ->and($video)->toContain('<li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>')
        ->and($video)->toContain('<li><a href="">المرئيات</a><i class="fa fa-angle-right"></i></li>')
        ->and($video)->toContain('<li><a href="">قائمة الدعاة</a><i class=""></i></li>')
        ->and($video)->toContain('<div class="portlet box blue">')
        ->and($video)->toContain('<i class="fa fa-child"></i>');

    // Malformed legacy icon (functions.php:541-543's stray \f escape) is a
    // legacy authoring bug, deliberately not reproduced.
    expect($video)->not->toContain('class=a fa-gift');

    $audio = $this->get('/khotab-audio.htm')->assertOk()->getContent();

    expect($audio)->toContain('<title>قسم الصوتيات - ')
        ->and($audio)->toContain('<h3 class="page-title">قائمة الدعاة بقسم الصوتيات</h3>')
        ->and($audio)->toContain('<li><a href="">الصوتيات</a><i class="fa fa-angle-right"></i></li>');
});

it('authors index: groups authors by first letter exactly like authors.php:58-74, including the ه→هـ normalization', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'أحمد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'أنس', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'name' => 'هشام', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect($content)->toContain('id="w2a_letter_'.md5('أ').'"')
        ->toContain('id="w2a_letter_'.md5('هـ').'"')
        ->and(substr_count($content, 'class="w2a-letter-section"'))->toBe(2);
});

it('authors index: a single shared first letter across all authors renders exactly one group, not one per author', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'خالد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'خليل', 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'name' => 'خميس', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect(substr_count($content, 'class="w2a-letter-section"'))->toBe(1)
        ->and($content)->toContain('id="w2a_letter_'.md5('خ').'"');
});

it('authors index: renders the real vedio/audio/pdf column value as the per-author count, with the op-specific label word', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Video Author', 'vedio' => 42, 'audio' => 7, 'pdf' => 3, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video.htm')->assertOk()->getContent();
    expect($video)->toContain('<span class="w2a-preacher-count">42 فيديو</span>');

    $audio = $this->get('/khotab-audio.htm')->assertOk()->getContent();
    expect($audio)->toContain('<span class="w2a-preacher-count">7 صوت</span>');

    $pdf = $this->get('/khotab-pdf.htm')->assertOk()->getContent();
    expect($pdf)->toContain('<span class="w2a-preacher-count">3 منشور</span>');
});

it('authors index: author link points at khotab-{op}-{id}.htm', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 55, 'name' => 'Author', 'vedio' => 1, 'audio' => 1, 'hidden' => 0]);

    expect($this->get('/khotab-video.htm')->assertOk()->getContent())->toContain('href="/khotab-video-55.htm"');
    expect($this->get('/khotab-audio.htm')->assertOk()->getContent())->toContain('href="/khotab-audio-55.htm"');
});

it('authors index: renders semantic alphabet navigation without inline-generated HTML', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'أحمد', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'name' => 'بلال', 'vedio' => 1, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab-video.htm')->assertOk()->getContent();

    expect(substr_count($content, 'class="w2a-alphabet-nav"'))->toBe(1)
        ->and($content)->toContain('href="#w2a_letter_'.md5('أ').'"')
        ->toContain('href="#w2a_letter_'.md5('ب').'"')
        ->not->toContain('var letterList');
});

it('dump: lists pdf items ordered by pdf_time, with a pdf-scoped sidebar', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Older PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Newer PDF', 'pdf' => 1, 'pdf_time' => 200, 'hidden' => 0],
    ]);

    $content = $this->get('/khotab/dump')->assertOk()->getContent();

    expect(strpos($content, 'Newer PDF'))->toBeLessThan(strpos($content, 'Older PDF'));
});

// ---- G-12-04 (G-12 investigation): dumped-lectures.htm is a real, live
// .htaccess rule (`.htaccess:221`) with a real homepage link
// (home_functions.php:398) — the pretty path now resolves to the exact same
// KhotabDumpController::index() as /khotab/dump, not a duplicated copy. ----

it('dumped-lectures.htm: resolves via KhotabDumpController::index(), identical to /khotab/dump', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'title' => 'Older PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0],
        ['id' => 2, 'author' => 1, 'title' => 'Newer PDF', 'pdf' => 1, 'pdf_time' => 200, 'hidden' => 0],
    ]);

    $prettyContent = $this->get('/dumped-lectures.htm')->assertOk()->getContent();
    $rawContent = $this->get('/khotab/dump')->assertOk()->getContent();

    expect($prettyContent)->toBe($rawContent);
    expect(strpos($prettyContent, 'Newer PDF'))->toBeLessThan(strpos($prettyContent, 'Older PDF'));
});

it('/khotab/dump: still resolves unchanged after dumped-lectures.htm registration', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Only PDF', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0,
    ]);

    $this->get('/khotab/dump')->assertOk()->assertSee('Only PDF');
});
