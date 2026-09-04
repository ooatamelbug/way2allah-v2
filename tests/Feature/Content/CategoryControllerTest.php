<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForCategoryController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
        'series_category_index' => MainSchema::seriesCategoryIndex(),
        // G-06 additions — showAnasheed() (var-category-{id}.htm).
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategoryController();
});

it('show: renders items and series linked to the category via the junction tables', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Fiqh Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 5]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'Fiqh Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 5]);

    $response = $this->get('/category-5.htm');

    $response->assertOk()->assertSee('Fiqh Lesson')->assertSee('Fiqh Series');
});

it('show: G-13-12 — series/item rows show a channel icon only when channel_id is set, matching categories/functions.php\'s own ListSeries()/ListKhotab()', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Fiqh Lesson', 'vedio' => 1, 'hidden' => 0, 'channel_id' => 9,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 5]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'Fiqh Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3, 'channel_id' => 0,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)->toContain('/images/channels/9.png')
        ->and(substr_count($content, 'images/channels/'))->toBe(1);
});

it('show: items linked to a different category are excluded', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0],
        ['id' => 6, 'title' => 'Aqeedah', 'main_cat' => 0],
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Aqeedah Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 6]);

    $this->get('/category-5.htm')->assertOk()->assertDontSee('Aqeedah Lesson');
});

it('show: breadcrumb trail walks main_cat up to the root, ancestors first', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root', 'main_cat' => 0],
        ['id' => 2, 'title' => 'Branch', 'main_cat' => 1],
        ['id' => 3, 'title' => 'Leaf', 'main_cat' => 2],
    ]);

    $content = $this->get('/category-3.htm')->assertOk()->getContent();

    // "Leaf" (the category being viewed) also appears earlier on the page
    // (<title>, <h3 class="page-title">) than in the breadcrumb nav
    // itself, so the position check is scoped to the nav element — a
    // page-wide strpos comparison would find the wrong (earlier) "Leaf"
    // occurrence. Shared Page Chrome Parity Audit: scoped to the shared
    // <x-page-chrome> component's <ul class="page-breadcrumb"> now,
    // replacing the previous bare <nav> markup.
    preg_match('/<ul class="page-breadcrumb">(.*?)<\/ul>/s', $content, $matches);
    $nav = $matches[1] ?? '';

    expect(strpos($nav, 'Root'))->toBeLessThan(strpos($nav, 'Branch'));
    expect(strpos($nav, 'Branch'))->toBeLessThan(strpos($nav, 'Leaf'));
});

it('show: 404s for a nonexistent category', function () {
    $this->get('/category-999.htm')->assertNotFound();
});

// ---- Final Conditional-Branch Audit (category-487.htm): ListMediaCoverage() ----

it('show: category 487 renders the "برامج حصرية" portlet listing its main_cat=487 sub-categories, BEFORE the Series portlet', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 487, 'title' => 'Media Coverage', 'main_cat' => 0],
        ['id' => 622, 'title' => 'Flood Coverage', 'main_cat' => 487],
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Coverage Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 487]);

    $content = $this->get('/category-487.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('برامج حصرية لشبكة الطريق إلى الله')
        ->toContain('class="w2a-exclusive-shows-grid"')
        ->toContain('class="w2a-exclusive-card"')
        ->toContain('href="/category-622.htm"')
        ->toContain('Flood Coverage');
    expect(strpos($content, 'برامج حصرية'))->toBeLessThan(strpos($content, 'قائمة المواد'));
});

it('show: category 487\'s media-coverage cards use the hardcoded logo for known sub-category ids, and the generic placeholder for any other', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 487, 'title' => 'Media Coverage', 'main_cat' => 0],
        ['id' => 613, 'title' => 'Salon', 'main_cat' => 487],
        ['id' => 999, 'title' => 'Unmapped', 'main_cat' => 487],
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Coverage Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 487]);

    $content = $this->get('/category-487.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('src="/images/logos/Salon.gif"')
        ->toContain('src="/images/tvnoise.gif"')
        ->toContain('loading="lazy"');
});

it('show: category 487 omits the media-coverage portlet entirely when it has no qualifying khotab items, even though its sub-categories exist', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 487, 'title' => 'Media Coverage', 'main_cat' => 0],
        ['id' => 622, 'title' => 'Flood Coverage', 'main_cat' => 487],
    ]);
    // No khotab_category_index row for category 487 at all.

    $content = $this->get('/category-487.htm')->assertOk()->getContent();

    // The bare phrase "برامج حصرية" alone would false-positive against
    // this app's own standing site-nav link to category-487.htm
    // (layouts/partials/navigation.blade.php:76, present on every page) —
    // check the actual portlet caption markup instead.
    expect($content)
        ->not->toContain('<div class="caption"><i class="fa fa-star" aria-hidden="true"></i> برامج حصرية لشبكة الطريق إلى الله</div>')
        ->not->toContain('Flood Coverage');
});

it('show: a DIFFERENT category never renders the media-coverage portlet, even with khotab items present', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Fiqh Lesson', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)->not->toContain('<div class="caption"><i class="fa fa-star" aria-hidden="true"></i> برامج حصرية لشبكة الطريق إلى الله</div>');
});

// ---- Full Design Parity Pass (category-{id}.htm): the real Series card-grid ----
// ListSeries()'s <table>/<tr> markup is entirely HTML-commented out in
// source and on live production — only a `.telawah-author` card survives.

it('show: renders each series as a .telawah-author card with the real static placeholder image, empty title attribute (a confirmed legacy $Item/$item bug), and the correct category-series link', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Adawy', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 42, 'author_id' => 1, 'group_id' => 0, 'title' => 'Ethics Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 42, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('col-xs-12 col-sm-6 col-md-4 col-lg-3 telawah-author')
        ->toContain('<img src="https://way2allah.com//images/tvnoise.gif" title="" width="" height="">')
        ->toContain('<a href="/category-series-42-5.htm">')
        ->toContain('Ethics Series')
        ->toContain('Sheikh Adawy');
});

it('show: omits the entire "قائمة السلاسل" portlet (not an empty-state message) when the category has no series, matching legacy\'s own num_rows>0 gate', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    // 'telawah-author' alone would false-positive against this page's own
    // page-specific <style> block (category.php:1-18), which defines that
    // selector unconditionally — check the actual card div's classlist instead.
    expect($content)->not->toContain('قائمة السلاسل')->not->toContain('col-lg-3 telawah-author');
});

it('show: the Series portlet is wrapped in the real w2a_open_div() structure, matching every other portlet on this page', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Adawy', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 42, 'author_id' => 1, 'group_id' => 0, 'title' => 'Ethics Series', 'vedio' => 1, 'hidden' => 0, 'count' => 3,
    ]);
    DB::connection('main')->table('series_category_index')->insert(['series_id' => 42, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)->toContain('<div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>');
    // Series renders before Khotab, matching category.php:84-85's real call order.
    expect(strpos($content, 'قائمة السلاسل'))->toBeLessThan(strpos($content, 'قائمة المواد'));
});

// ---- Full Design Parity Pass: the 3 sidebar portlets — real w2a_open_div() wrappers + real per-item markup ----

it('show: "اخترنا لك هذه المادة" wraps randomFeatured in a real portlet with a thumbnail/caption card per item, using khotab_frames when frame=1', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Adawy', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 500, 'author' => 1, 'title' => 'Featured Lesson', 'vedio' => 1, 'hidden' => 0, 'frame' => 1, 'gif' => 0,
    ]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    // randomitems() (functions.php:1132) echoes only $Khotab->name in the
    // <h3> — no prename, unlike topitems()'s prename+name convention
    // elsewhere on this page. Reproduced as found, not "fixed" to match.
    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>')
        ->toContain('/media/khotab_frames/0/500.jpg')
        ->toContain('<h3>Adawy</h3>')
        ->toContain('<p><a href="/khotab-item-500.htm">Featured Lesson</a></p>');
});

it('show: "الأكثر تحميلا" renders the premium top-item card with a thumbnail and download count', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 501, 'author' => 1, 'title' => 'Popular Lesson', 'vedio' => 1, 'hidden' => 0, 'hits' => 12345, 'frame' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 501, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<li class="media w2a-top-item">')
        ->toContain('class="media-object w2a-top-item-thumb"')
        ->toContain('<h5 class="media-heading w2a-top-item-title">Popular Lesson</h5>')
        ->toContain('12,345 تحميل')
        // frame=0 -> the confirmed-broken author-photo path never resolves, always the generic placeholder.
        ->toContain('/images/way2_withoutimg.png');
});

it('show: "جديد المواد" shows a CoolShortDate-formatted date label, not a download count', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 502, 'author' => 1, 'title' => 'Recent Lesson', 'vedio' => 1, 'hidden' => 0, 'time' => strtotime('2026-06-14'),
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 502, 'category_id' => 5]);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('class="w2a-top-item-badge"><i class="fa fa-clock-o"')
        ->toContain('الأحد 14 يونيو 2026 مـ');
});

it('show: the category description portlet uses the real w2a_open_div() wrapper, not a bare <section>', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 5, 'title' => 'Fiqh', 'main_cat' => 0, 'description' => 'A real description']);

    $content = $this->get('/category-5.htm')->assertOk()->getContent();

    expect($content)->toContain('<p>A real description</p>');
    expect(substr_count($content, 'class="portlet box blue"'))->toBeGreaterThan(1);
});

// ---- Shared Page Chrome Parity Audit: category.php:70-71's real heading + root "التصنيفات الموضوعية" breadcrumb item (previously a bare <nav><a>, missing Home and the root label entirely) ----

it('show: renders the heading and the "التصنيفات الموضوعية" root breadcrumb item, linked, before the ancestor chain', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Root Cat', 'main_cat' => 0],
        ['id' => 2, 'title' => 'Leaf Cat', 'main_cat' => 1],
    ]);

    $content = $this->get('/category-2.htm')->assertOk()->getContent();

    expect($content)->toContain('<h3 class="page-title">Leaf Cat</h3>');
    expect($content)
        ->toContain('<li><a href="/categories.htm">التصنيفات الموضوعية</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="/category-1.htm">Root Cat</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li>Leaf Cat<i class=""></i></li>');

    $order = ['التصنيفات الموضوعية</a>', 'Root Cat</a>', 'Leaf Cat<i'];
    $positions = array_map(fn ($needle) => strpos($content, $needle), $order);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

// Roadmap task 4.2 amendment (added post-Wave-4 — see
// docs/reviews/gap-closure-action-plan.md item 1). vars_categories/ is a
// confirmed superseded duplicate of categories/ — same category-id space
// — so its live route redirects here rather than getting its own
// controller.
it('IF-031 fix: vars-category-{id}.htm redirects to the equivalent category-{id}.htm, same id preserved', function () {
    $this->get('/vars-category-42.htm')->assertRedirect('/category-42.htm');
});

// ---- G-06 additions: the 2 remaining vars_categories redirects (IF-043) ----

it('IF-043 fix: vars-categories.htm redirects to categories.htm', function () {
    $this->get('/vars-categories.htm')->assertRedirect('/categories.htm');
});

it('IF-043 fix: vars-category-series-{id}-{id2}.htm redirects to category-series-{id}-{id2}.htm, same ids preserved', function () {
    $this->get('/vars-category-series-9-11.htm')->assertRedirect('/category-series-9-11.htm');
});

// ---- G-06 additions: CategoryController::showAnasheed() (var-category-{id}.htm) ----

it('showAnasheed: renders anasheed items linked to the category via the pipe-delimited cat_id column', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Anasheed In Category', 'cat_id' => '|7|', 'hidden' => 0,
    ]);
    DB::connection('main')->table('nuke_anasheed_advanced')->insert(['id' => 1, 'adur' => '90000']);

    $this->get('/var-category-7.htm')->assertOk()->assertSee('Anasheed In Category');
});

it('showAnasheed: items linked to a different category are excluded (LIKE match is exact-id-scoped, not a prefix match)', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert([
        ['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0],
        ['id' => 70, 'title' => 'Other', 'main_cat' => 0],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Wrong Category Item', 'cat_id' => '|70|', 'hidden' => 0,
    ]);

    $this->get('/var-category-7.htm')->assertOk()->assertDontSee('Wrong Category Item');
});

it('IF-036: showAnasheed() shows a KHOTAB sidebar (random featured/most downloaded/most recent) alongside the anasheed listing — confirmed intentional-as-written, not a porting error', function () {
    DB::connection('main')->table('nuke_w2a_cat')->insert(['id' => 7, 'title' => 'Tafsir', 'main_cat' => 0]);
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Khotab Sidebar Item', 'vedio' => 1, 'hidden' => 0,
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 7]);

    // The sidebar widgets are scoped to the SAME category id as the anasheed
    // listing (khotab_category_index, not the anasheed cat_id column) —
    // this is legacy's own real behavior (IF-036), reproduced exactly.
    $this->get('/var-category-7.htm')->assertOk()->assertSee('Khotab Sidebar Item');
});

it('showAnasheed: 404s for a nonexistent category', function () {
    $this->get('/var-category-999.htm')->assertNotFound();
});
