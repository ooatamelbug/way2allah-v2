<?php

/*
 * Channel author UI & navigation (owner-approved, 2026-11-08) — `channel-{id}-{author}.htm` follows
 * Gen 2 `channels/author.php`: two-column `row service-box`, heading «مرئيات {author} - قناة {channel}»
 * with a three-level breadcrumb, sidebar «الملف الشخصي» → «اخترنا لك هذه المادة» → the two empty IF-012
 * boxes; sections without rows omitted (`channels/functions.php` `num_rows > 0`); group / series cards
 * link their author (group → khotab-video-{author}.htm, series → channel-{channel}-{author}.htm) through
 * the grid's opt-in `secondaryHref` without nested anchors. Normal channel pages and other grid
 * consumers keep their behaviour.
 */

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
    ]);

    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([['id' => 5, 'title' => 'الناس'], ['id' => 6, 'title' => 'Other']]);
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 9, 'name' => 'محمد حسين يعقوب', 'prename' => 'الشيخ'],
        ['id' => 42, 'name' => 'Other Shaikh', 'prename' => 'Dr.'],
    ]);
});

function calSeed(bool $withGroup = false): void
{
    $db = DB::connection('main');
    $db->table('nuke_islamic_series')->insert([
        ['id' => 31, 'channel_id' => 5, 'author_id' => 9, 'title' => 'سلسلة أولى', 'vedio' => 1, 'hidden' => 0, 'count' => 4, 'lastupdate' => 20, 'time' => 1],
        ['id' => 32, 'channel_id' => 5, 'author_id' => 9, 'title' => 'Hidden Series', 'vedio' => 1, 'hidden' => 1, 'count' => 4, 'lastupdate' => 10, 'time' => 1],
        ['id' => 33, 'channel_id' => 5, 'author_id' => 42, 'title' => 'Other Author Series', 'vedio' => 1, 'hidden' => 0, 'count' => 2, 'lastupdate' => 30, 'time' => 1],
    ]);
    if ($withGroup) {
        $db->table('nuke_islamic_groups')->insert([
            ['id' => 21, 'channel_id' => 5, 'author_id' => 9, 'title' => 'مجموعة', 'vedio' => 1, 'hidden' => 0, 'count' => 3],
            ['id' => 22, 'channel_id' => 5, 'author_id' => 42, 'title' => 'Other Group', 'vedio' => 1, 'hidden' => 0, 'count' => 3],
        ]);
    }
    $base = ['ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'hits' => 1234, 'comments' => 3, 'time' => 1700000000, 'hidden' => 0, 'frame' => 0, 'gif' => 0];
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 101, 'channel_id' => 5, 'author' => 9, 'title' => 'مادة منفردة'] + $base,
        ['id' => 102, 'channel_id' => 5, 'author' => 9, 'title' => 'Hidden Item', 'hidden' => 1] + $base,
        ['id' => 103, 'channel_id' => 5, 'author' => 42, 'title' => 'Other Author Item'] + $base,
        ['id' => 104, 'channel_id' => 6, 'author' => 42, 'title' => 'Featured Pick', 'frame' => 1] + $base,
    ]);
}

function calDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

function calText(DOMNode $node): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
}

it('returns 200 with the Gen 2 heading, three-level breadcrumb, document title and og:title', function () {
    calSeed();
    $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();
    $x = calDom($html);

    $heading = 'مرئيات الشيخ محمد حسين يعقوب - قناة الناس';
    expect(calText($x->query('//h3[@class="page-title"]')->item(0)))->toBe($heading)
        ->and($html)->toContain('<title>'.$heading.' - '.e(config('app.name')).'</title>')
        ->and($x->query('//meta[@property="og:title"]')->item(0)->getAttribute('content'))->toBe($heading);

    $crumbs = $x->query('//ul[@class="page-breadcrumb"]/li');
    expect($crumbs->length)->toBe(4); // home + 3 levels
    $levels = [];
    foreach ([1, 2, 3] as $i) {
        $a = $x->query('.//a', $crumbs->item($i))->item(0);
        $levels[] = [calText($a), $a->getAttribute('href')];
    }
    expect($levels)->toBe([
        ['القنوات الفضائية', '/channels.htm'],
        ['قناة الناس', '/channel-5.htm'],
        ['الشيخ محمد حسين يعقوب', ''],
    ]);
});

it('a nonexistent author falls back to Gen 2\'s «قناة {title}» heading with a two-level breadcrumb', function () {
    $html = $this->get('/channel-5-777.htm')->assertOk()->getContent();
    $x = calDom($html);

    expect(calText($x->query('//h3[@class="page-title"]')->item(0)))->toBe('قناة الناس')
        ->and($x->query('//ul[@class="page-breadcrumb"]/li')->length)->toBe(3);
});

it('uses the two-column structure: listing in the main column, profile → recommendation → IF-012 boxes in the sidebar', function () {
    calSeed();
    $x = calDom($this->get('/channel-5-9.htm')->assertOk()->getContent());

    $row = $x->query('//div[contains(@class,"service-box") and contains(@class,"w2a-channel-author")]');
    expect($row->length)->toBe(1);
    $main = $x->query('./div[contains(@class,"col-lg-9") and contains(@class,"col-md-8") and contains(@class,"col-sm-7")]', $row->item(0));
    $aside = $x->query('./aside[contains(@class,"col-lg-3") and contains(@class,"col-md-4") and contains(@class,"col-sm-5")]', $row->item(0));
    expect($main->length)->toBe(1)->and($aside->length)->toBe(1);

    $captions = [];
    foreach ($x->query('.//div[@class="caption"]', $aside->item(0)) as $caption) {
        $captions[] = calText($caption);
    }
    expect($captions)->toBe(['الملف الشخصي', 'اخترنا لك هذه المادة', 'الأكثر تحميلا', 'جديد المواد']);

    // Profile image inside the sidebar profile portlet, Gen 2 classes, same source.
    $img = $x->query('.//div[@class="profile-userpic"]/img[@class="img-responsive"]', $aside->item(0));
    expect($img->length)->toBe(1)->and($img->item(0)->getAttribute('src'))->toBe('/media/authors/sq/9.png');

    // Exactly one featured item, in the sidebar; none in the main column.
    expect($x->query('.//article[contains(@class,"w2a-featured-item-card")]', $aside->item(0))->length)->toBe(1)
        ->and($x->query('.//*[contains(@class,"w2a-featured-item-card")]', $main->item(0))->length)->toBe(0)
        ->and($x->query('.//a[@href="/khotab-item-104.htm"]', $aside->item(0))->length)->toBeGreaterThan(0);

    // IF-012: both boxes present and empty.
    foreach (['الأكثر تحميلا', 'جديد المواد'] as $title) {
        $body = $x->query('.//div[contains(@class,"portlet") and .//div[@class="caption" and normalize-space(.)="'.$title.'"]]/div[@class="portlet-body"]', $aside->item(0));
        expect($body->length)->toBe(1)->and(trim($body->item(0)->textContent))->toBe('')
            ->and($body->item(0)->childNodes->length)->toBe(0);
    }
});

it('omits empty sections and keeps populated ones (groups / series / items)', function () {
    calSeed(withGroup: false);
    $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect($html)->not->toContain('قائمة المجموعات')
        ->toContain('قائمة السلاسل')->toContain('سلسلة أولى')
        ->toContain('قائمة المواد')->toContain('مادة منفردة');

    DB::connection('main')->table('nuke_islamic_series')->delete();
    DB::connection('main')->table('nuke_islamic_khotab')->whereIn('id', [101, 102])->delete();
    $empty = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect($empty)->not->toContain('قائمة المجموعات')->not->toContain('قائمة السلاسل')->not->toContain('قائمة المواد')
        ->toContain('اخترنا لك هذه المادة');
});

it('keeps author / visibility filtering: other authors\' and hidden rows never appear', function () {
    calSeed(withGroup: true);
    $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect($html)->toContain('مجموعة')->toContain('سلسلة أولى')->toContain('مادة منفردة')
        ->not->toContain('Other Group')->not->toContain('Other Author Series')->not->toContain('Other Author Item')
        ->not->toContain('Hidden Series')->not->toContain('Hidden Item');
});

it('item rows use the full row width with no repeated author column on the author page; metadata kept', function () {
    calSeed();
    $x = calDom($this->get('/channel-5-9.htm')->assertOk()->getContent());

    $row = $x->query('//table[@id="tabelkht"]//tr')->item(0);
    $titleCol = $x->query('.//h5/div[@class="row"]/div', $row);
    expect($titleCol->length)->toBe(1)
        ->and($titleCol->item(0)->getAttribute('class'))->toBe('col-sm-12 col-lg-12')
        ->and($x->query('.//a', $titleCol->item(0))->item(0)->getAttribute('href'))->toBe('/khotab-item-101.htm');

    $rowText = calText($row);
    expect($rowText)->not->toContain('الداعية:')
        ->toContain(date('Y-m-d', 1700000000))->toContain('التعليقات: 3')->toContain('مشاهدات: 1,234');
});

it('restores Gen 2 author links: series → channel-{channel}-{author}.htm, group → khotab-video-{author}.htm; card destinations unchanged', function () {
    calSeed(withGroup: true);
    $x = calDom($this->get('/channel-5-9.htm')->assertOk()->getContent());

    $series = $x->query('//article[contains(@class,"w2a-cat-series-card--linked") and .//a[@href="/khotab-series-31.htm"]]');
    expect($series->length)->toBe(1);
    $seriesAuthor = $x->query('.//a[contains(@class,"w2a-cat-series-card__secondary")]', $series->item(0));
    expect($seriesAuthor->length)->toBe(1)
        ->and($seriesAuthor->item(0)->getAttribute('href'))->toBe('/channel-5-9.htm')
        ->and(calText($seriesAuthor->item(0)))->toBe('محمد حسين يعقوب');

    $group = $x->query('//article[contains(@class,"w2a-cat-series-card--linked") and .//a[@href="/khotab-group-21.htm"]]');
    expect($group->length)->toBe(1);
    $groupAuthor = $x->query('.//a[contains(@class,"w2a-cat-series-card__secondary")]', $group->item(0));
    expect($groupAuthor->item(0)->getAttribute('href'))->toBe('/khotab-video-9.htm');

    // Main card link: the stretched title link, to the original destination.
    $main = $x->query('.//a[contains(@class,"w2a-cat-series-card__link")]', $series->item(0));
    expect($main->length)->toBe(1)->and($main->item(0)->getAttribute('href'))->toBe('/khotab-series-31.htm')
        ->and(calText($main->item(0)))->toBe('سلسلة أولى');
});

it('emits no nested anchors anywhere on the channel and channel-author pages; author links are separate siblings', function (string $url) {
    calSeed(withGroup: true);
    $x = calDom($this->get($url)->assertOk()->getContent());

    expect($x->query('//a//a')->length)->toBe(0);
    foreach ($x->query('//a[contains(@class,"w2a-cat-series-card__secondary")]') as $link) {
        expect($x->query('ancestor::a', $link)->length)->toBe(0)
            ->and($x->query('ancestor::article[contains(@class,"w2a-cat-series-card--linked")]', $link)->length)->toBe(1);
    }
})->with(['/channel-5-9.htm', '/channel-5.htm']);

it('the normal channel page keeps its own layout, item author column and 3-column grid scope; cards link authors too', function () {
    calSeed(withGroup: true);
    $html = $this->get('/channel-5.htm')->assertOk()->getContent();
    $x = calDom($html);

    expect($x->query('//h3[@class="page-title"]')->item(0)->textContent)->toBe('قناة الناس')
        ->and($html)->toContain('بيانات القناة')
        ->and($html)->not->toContain('w2a-channel-author')
        ->and($html)->toContain('الداعية:')
        ->and($html)->toContain('href="/channel-5-9.htm">محمد حسين يعقوب</a>')
        ->and($html)->toContain('Other Author Series');

    $firstItemCols = $x->query('//table[@id="tabelkht"]//tr[1]//h5/div[@class="row"]/div');
    expect($firstItemCols->length)->toBe(2)
        ->and($firstItemCols->item(0)->getAttribute('class'))->toBe('col-sm-12 col-lg-6');

    expect($x->query('//article[contains(@class,"w2a-cat-series-card--linked")]//a[contains(@class,"__secondary") and @href="/channel-5-42.htm"]')->length)->toBe(1);
});

it('without secondaryHref the grid keeps its original single-anchor card markup (other consumers unaffected)', function (string $secondary) {
    $items = collect([(object) ['id' => 7, 'title' => 'T', 'count' => 2, 'author' => 'A', 'author_id' => 3, 'channel_id' => 5, 'channel' => 'C']]);

    $html = Blade::render('<x-content.media-collection-grid :items="$items" secondary="'.$secondary.'" />', ['items' => $items]);
    $x = calDom($html);

    expect($x->query('//article')->length)->toBe(0)
        ->and($html)->not->toContain('w2a-cat-series-card--linked')->not->toContain('__secondary')->not->toContain('__link')
        ->and($x->query('//a[@class="w2a-cat-series-card" and @href="/khotab-series-7.htm"]')->length)->toBe(1)
        ->and($x->query('//a')->length)->toBe(1)
        ->and($x->query('//a/div[@class="w2a-cat-series-body"]/span[@class="w2a-cat-series-author"]')->length)->toBe(1);
})->with(['author', 'channel']);

it('a row without a usable author falls back to the original card — no broken link', function () {
    $items = collect([(object) ['id' => 8, 'title' => 'No Author', 'count' => 2, 'author' => '', 'author_id' => null, 'channel_id' => 5]]);
    $href = fn ($row) => null;

    $html = Blade::render('<x-content.media-collection-grid :items="$items" secondary="author" :secondary-href="$href" />', ['items' => $items, 'href' => $href]);

    expect($html)->not->toContain('<article')->not->toContain('href=""')
        ->toContain('<a href="/khotab-series-8.htm" class="w2a-cat-series-card">');
});

it('the 2-column grid rule is scoped to the channel-author page; the linked-card overlay sits below the author link', function () {
    $css = (string) file_get_contents(public_path('assets/frontend/layout/css/content-refresh.css'));

    expect($css)->toContain(".w2a-channel-author .w2a-cat-series-grid {\n    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;")
        ->and($css)->toContain(".w2a-channel-author .w2a-cat-series-grid {\n        grid-template-columns: minmax(0, 1fr) !important;")
        ->and($css)->not->toMatch('/^\.w2a-cat-series-grid\s*\{[^}]*repeat\(2/m')
        ->and($css)->toMatch('/\.w2a-cat-series-card__link::after\s*\{[^}]*position:\s*absolute;[^}]*inset:\s*0;[^}]*z-index:\s*1;/s')
        ->and($css)->toMatch('/\.w2a-cat-series-card__secondary\s*\{[^}]*position:\s*relative;[^}]*z-index:\s*2;/s');
});
