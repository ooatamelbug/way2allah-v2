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
        // Fatwa Authors Batch 1: /fatawa-authors.htm (op=fatwa) now derives
        // its count and membership from the fatwa mapping tables instead of
        // the stale nuke_islamic_authors.fatwa column, so this fixture needs
        // them present. The khotab video/audio/pdf ops are unaffected.
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
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

/**
 * Link shape unchanged by Fatwa Authors Batch 1 — but the COUNT'S SOURCE is.
 *
 * The displayed number now comes from `nuke_fatwa_questions` (distinct
 * general questions), not from `nuke_islamic_authors.fatwa`. So this fixture
 * seeds 12 real distinct mappings to keep asserting "12 فتوى" — the same
 * number this test always expected, now for the right reason. The stale
 * column is deliberately set to a conflicting value to prove it is ignored.
 */
it('fatawa-authors.htm (op=fatwa) generates real khotab-fatwa-{id}.htm links, byte-faithful to authors.php:80 — not a migration typo/bug', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        'id' => 17, 'name' => 'الحويني', 'prename' => 'الشيخ', 'fatwa' => 999, 'hidden' => 0,
    ]);

    for ($i = 1; $i <= 12; $i++) {
        DB::connection('main')->table('nuke_fatwa_general_questions')->insert([
            'id' => 500 + $i, 'question_text' => 'Q' . $i, 'topic_id' => '0',
        ]);
        DB::connection('main')->table('nuke_fatwa_questions')->insert([
            'id' => $i, 'auther_id' => 17, 'general_question_id' => '|' . (500 + $i) . '|', 'question_text' => 'A' . $i,
        ]);
    }

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('href="/khotab-fatwa-17.htm"')
        ->toContain('الحويني')
        ->toContain('12 فتوى')
        ->not->toContain('999 فتوى');
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

/**
 * EXPECTATION INVERTED — Fatwa Authors Batch 1.
 *
 * This test previously asserted that `fatawa-authors.htm` lists an author
 * purely because `nuke_islamic_authors.fatwa > 0`, reproducing
 * `authors.php:24`'s literal WHERE clause. Production evidence retired that
 * rule: the stored column is not the count of anything displayable (author
 * 17 stored 12 against 126 real distinct questions; author 242 stored 289
 * against ZERO mapping rows), and nothing anywhere writes it, so it cannot
 * self-correct. The directory advertised 35 authors whose pages can only
 * ever be empty, while hiding 28 authors who do have fatwas.
 *
 * Membership and the displayed number are now both derived from
 * `nuke_fatwa_questions` (`hidden = 0 AND distinct-question count > 0`).
 * So an author with `fatwa = 5` and no mapping rows must now be ABSENT —
 * the inverse of what this test used to assert. The `hidden = 0` half of
 * the rule is unchanged.
 *
 * Full coverage of the new semantics lives in FatwaAuthorsDirectoryTest.
 */
it('fatawa-authors.htm ignores the stale fatwa column: an author with fatwa > 0 but no real mappings is now ABSENT', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Stale Counter Only', 'fatwa' => 5, 'hidden' => 0],
        ['id' => 2, 'name' => 'Real Mappings', 'fatwa' => 0, 'hidden' => 0],
    ]);
    DB::connection('main')->table('nuke_fatwa_general_questions')->insert([
        ['id' => 500, 'question_text' => 'A real general question'],
    ]);
    DB::connection('main')->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 2, 'general_question_id' => '|500|', 'question_text' => 'q'],
    ]);

    $content = $this->get('/fatawa-authors.htm')->assertOk()->getContent();

    expect($content)->toContain('Real Mappings')->not->toContain('Stale Counter Only');
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

it('group show: the series list uses premium cards without nested channel links', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 1, 'author_id' => 1, 'group_id' => 20, 'title' => 'With Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9],
        ['id' => 2, 'author_id' => 1, 'group_id' => 20, 'title' => 'No Channel', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0],
    ]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-cat-series-grid"')
        ->toContain('<a href="/khotab-series-1.htm" class="w2a-cat-series-card">')
        ->toContain('<a href="/khotab-series-2.htm" class="w2a-cat-series-card">')
        ->not->toContain('images/channels/');
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

it('group show: the redesigned series portlet uses the list icon while the other portlets remain intact', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('<i class="fa fa-list-ol" aria-hidden="true"></i> قائمة السلاسل')
        ->and(substr_count($content, 'class="portlet box blue"'))->toBe(7);
});

it('group show: the Series portlet renders a premium card with count and channel label', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="portlet-body ">')
        ->toContain('<a href="/khotab-series-30.htm" class="w2a-cat-series-card">')
        ->toContain('class="w2a-cat-series-title">Juz Tabarak</h3>')
        ->toContain('32 مادة')
        ->toContain('class="fa fa-television"')
        ->toContain('Iqraa');
});

it('group show: the Series portlet shows the real empty-state text when the group has no series, not an omitted block', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 20, 'author_id' => 1, 'title' => 'Empty Group', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)->toContain('لا توجد سلاسل مطابقة بقاعدة بيانات الموقع');
});

it('group show: the Khotab items portlet uses responsive cards with date, comments, views, channel, and duration but no author link', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-item-card-row"')
        ->toContain('class="w2a-item-card-title">Surah Al-Bayyina</a>')
        ->toContain('<i class="fa fa-calendar" aria-hidden="true"></i> 2006-12-19')
        ->toContain('<i class="fa fa-commenting-o" aria-hidden="true"></i> 1 تعليق')
        ->toContain('<i class="fa fa-eye" aria-hidden="true"></i> 2,529 مشاهدة')
        ->not->toContain('w2a-item-card-author');
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

    expect(substr_count($content, 'fa-cloud-download'))->toBeGreaterThanOrEqual(4);
    expect($content)->toContain('500 تحميل')->toContain('42 تحميل');
});

it('group show: omits DataTables assets after its last table was replaced by responsive cards', function () {
    seedKhotabGroupParityFixture();

    $content = $this->get('/khotab-group-20.htm')->assertOk()->getContent();

    expect($content)
        ->not->toContain('datatables.min.css')
        ->not->toContain('datatables.bootstrap-rtl.css')
        ->not->toContain('datatables.min.js')
        ->not->toContain('datatables.bootstrap.js')
        ->not->toContain('/scripts/khotab_tables.js')
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

it('day: renders the premium native date form without legacy datepicker assets', function () {
    $response = $this->get('/khotab-video-today.htm');
    $content = $response->assertOk()->getContent();

    expect(substr_count($content, 'portlet-title'))->toBe(4)
        ->and($content)->toContain('بحث بالتاريخ')
        ->and($content)->toContain('w2a-date-picker-form')
        ->and($content)->toContain('w2a-date-picker-submit')
        ->and($content)->toContain('type="date"')
        ->and($content)->toContain('method="get"')
        ->and($content)->not->toContain('bootstrap-datepicker')
        ->and($content)->not->toContain('scripts/khotab_date.js')
        ->and($content)->toContain('لا توجد مواد مطابقة بقاعدة بيانات الموقع');
});

it('day: native date search redirects to the canonical dated route for video and audio', function () {
    $this->get('/khotab-video-today.htm?date=2020-01-15')
        ->assertRedirect('/khotab-videodate-15-1-2020.htm');

    $this->get('/khotab-audio-today.htm?date=2020-01-15')
        ->assertRedirect('/khotab-audiodate-15-1-2020.htm');
});

it('day: invalid native date input is ignored safely', function () {
    $this->get('/khotab-video-today.htm?date=2020-02-31')
        ->assertOk()
        ->assertSee('البحث بالتاريخ');
});

it('day: "جديد المواد" box uses mode=\'time\' (a formatted date), unlike khotab-series-{id}.htm\'s always-\'hits\' boxes', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 0, 'title' => 'Recent Item', 'vedio' => 1, 'hidden' => 0,
        'time' => mktime(0, 0, 0, 6, 28, 2026), 'hits' => 5,
    ]);

    $content = $this->get('/khotab-video-today.htm')->assertOk()->getContent();

    expect($content)->toContain('الأحد 28 يونيو 2026 مـ');
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

it('author show: group and series lists use premium cards without nested channel links', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 9]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 0]);
    // groupsByAuthor() inner-joins nuke_islamic_khotab on group_id — a real row is required for the group to appear at all.
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<a href="/khotab-group-1.htm" class="w2a-cat-series-card">')
        ->toContain('<a href="/khotab-series-1.htm" class="w2a-cat-series-card">')
        ->not->toContain('images/channels/');
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

it('author show: every section remains wrapped in a modern panel while collection headings use their new icons', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 1, 'title' => 'A Group', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'group_id' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('قائمة المجموعات')
        ->and($content)->toContain('fa-folder')
        ->and($content)->toContain('قائمة السلاسل')
        ->and($content)->toContain('fa-list-ol')
        ->and($content)->toContain('قائمة المواد')
        ->and($content)->toContain('w2a-author-profile-card')
        ->and($content)->toContain('اخترنا لك هذه المادة')
        ->and($content)->toContain('الأكثر تحميلاً')
        ->and($content)->toContain('جديد المواد')
        // 7 modern refresh panels for a video/audio op with no description (plus the promo banner link)
        ->and(substr_count($content, '<section class="w2a-refresh-panel'))->toBe(7);
});

it('author show: the pdf op has no promo-banner widget (legacy author.php:110-138 only has video/audio branches)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $content = $this->get('/khotab-pdf-1.htm')->assertOk()->getContent();

    expect($content)->not->toContain('w2a-author-banner')
        // pdf op: no groups/series panels (op !== 'pdf' gate) and no
        // promo banner — just "قائمة المواد" + the 4 always-present
        // sidebar widgets (profile/"اخترنا لك"/"الأكثر تحميلاً"/"جديد المواد").
        ->and(substr_count($content, '<section class="w2a-refresh-panel'))->toBe(5);
});

it('author show: renders the video/audio promotional banner with the modern badge and self-link', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 42, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 42, 'title' => 'Video Item', 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'author' => 42, 'title' => 'Audio Item', 'vedio' => 0, 'hidden' => 0],
    ]);

    $video = $this->get('/khotab-video-42.htm')->assertOk()->getContent();
    expect($video)->toContain('w2a-author-banner--video')
        ->and($video)->toContain('مرئيات الداعية')
        ->and($video)->toContain('/khotab-video-42.htm');

    $audio = $this->get('/khotab-audio-42.htm')->assertOk()->getContent();
    expect($audio)->toContain('w2a-author-banner--audio')
        ->and($audio)->toContain('صوتيات الداعية')
        ->and($audio)->toContain('/khotab-audio-42.htm');
});

it('author show: the description block (when present) is rendered in a modern panel with biography content', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'description' => 'A biography.']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'Item', 'vedio' => 1, 'hidden' => 0]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('A biography.')
        ->and($content)->toContain('نبذة عن الداعية')
        // 7 base panels (video op) + 1 description panel = 8 panels
        ->and(substr_count($content, '<section class="w2a-refresh-panel'))->toBe(8);
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

it('author show: renders group cards with counts and channel labels without nested links', function () {
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
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Test Channel']);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<a href="/khotab-group-1.htm" class="w2a-cat-series-card">')
        ->and($content)->toContain('class="w2a-cat-series-count"')
        ->and($content)->toContain('1 مادة')
        ->and($content)->toContain('Test Channel')
        ->and($content)->not->toContain('images/channels/');
});

it('author show: renders series cards with counts and channel labels', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 3, 15, 2026);
    $lastupdate = mktime(0, 0, 0, 4, 1, 2026);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'A Series', 'vedio' => 1, 'hidden' => 0,
        'count' => 7, 'channel_id' => 9, 'time' => $time, 'lastupdate' => $lastupdate,
    ]);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Test Channel']);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('<a href="/khotab-series-1.htm" class="w2a-cat-series-card">')
        ->and($content)->toContain('7 مادة')
        ->and($content)->toContain('Test Channel')
        ->and($content)->not->toContain('images/channels/');
});

it('author show: renders the responsive item-card contract with date, comments, views, channel, and duration', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    $time = mktime(0, 0, 0, 4, 22, 2026);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'An Item', 'vedio' => 1, 'hidden' => 0,
        'comments' => 4, 'hits' => 1872, 'channel_id' => 9, 'time' => $time,
    ]);
    DB::connection('main')->table('nuke_islamic_advanced')->insert(['id' => 1, 'adur' => 3621662]);

    $content = $this->get('/khotab-video-1.htm')->assertOk()->getContent();

    expect($content)->toContain('class="w2a-item-card-row"')
        ->and($content)->toContain('<i class="fa fa-calendar" aria-hidden="true"></i>')
        ->and($content)->toContain(date('Y-m-d', $time))
        ->and($content)->toContain('<i class="fa fa-commenting-o" aria-hidden="true"></i> 4 تعليق')
        ->and($content)->toContain('<i class="fa fa-eye" aria-hidden="true"></i>')
        ->and($content)->toContain('مشاهدة')
        ->and($content)->toContain(number_format(1872))
        ->and($content)->toContain('/channel-9.htm')
        ->and($content)->toContain('<i class="fa fa-clock-o" aria-hidden="true"></i>')
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
