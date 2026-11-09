<?php

/*
 * Advanced-search visitor parity (owner, 2026-11-03) — OLD `advanced_search.htm`
 * (`modules/advanced_search` `Main_search()` / `ListSeries()` / `ListKhotab()`)
 * and the sheikh pages' «بحث متقدم» form (`apply_search("author", $id)`).
 * G1 criteria kept across pages, G2 independent series / item pages,
 * G3 `/advanced_search.htm`, G4 the sheikh-page form, G5 items by weight,
 * G6 25 per page, G7 «سلسلة» on the series total. Both URLs behave alike;
 * GET and the title-less search stay (owner decisions).
 */

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
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_fatwa_general_questions' => MainSchema::nukeFatwaGeneralQuestions(),
    ]);
    DB::connection('main')->table('nuke_islamic_authors')->insert([
        ['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.'],
        ['id' => 2, 'name' => 'Other', 'prename' => ''],
    ]);
});

/** `$items` video items titled "Lesson N" (weight descending as id rises unless given), and `$series` video series. */
function aspSeed(int $items, int $series = 0, array $itemOverrides = []): void
{
    $rows = [];
    for ($id = 1; $id <= $items; $id++) {
        $rows[] = array_merge(['id' => $id, 'title' => "Lesson {$id}", 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => $id, 'time' => 1000 + $id], $itemOverrides[$id] ?? []);
    }
    if ($rows !== []) {
        DB::connection('main')->table('nuke_islamic_khotab')->insert($rows);
    }

    $seriesRows = [];
    for ($id = 1; $id <= $series; $id++) {
        $seriesRows[] = ['id' => $id, 'author_id' => 1, 'title' => "Lesson series {$id}", 'vedio' => 1, 'hidden' => 0, 'count' => 1];
    }
    if ($seriesRows !== []) {
        DB::connection('main')->table('nuke_islamic_series')->insert($seriesRows);
    }
}

/** The results area only — the site navigation carries its own series links. */
function aspResults(string $html): string
{
    $start = strpos($html, 'w2a-video-search-results');

    return $start === false ? '' : substr($html, $start);
}

/** @return list<int> item ids in page order */
function aspItemIds(string $html): array
{
    preg_match_all('#href="/khotab-item-(\d+)\.htm"#', aspResults($html), $m);

    return array_values(array_unique(array_map('intval', $m[1])));
}

/** @return list<int> series ids in page order */
function aspSeriesIds(string $html): array
{
    preg_match_all('#href="/khotab-series-(\d+)\.htm"#', aspResults($html), $m);

    return array_values(array_unique(array_map('intval', $m[1])));
}

/** @return list<string> pager hrefs, HTML-decoded */
function aspPagerLinks(string $html): array
{
    preg_match_all('#href="([^"]*(?:\?|&amp;)(?:page|series_page)=\d+[^"]*)"#', $html, $m);

    return array_map('html_entity_decode', $m[1]);
}

// ─── G3 — both URLs ──────────────────────────────────────────────────────────

it('G3: /advanced_search.htm is served again, with the same page as /video-advanced-search.htm', function () {
    aspSeed(3, 2);

    foreach (['/advanced_search.htm', '/video-advanced-search.htm'] as $url) {
        $this->get($url)->assertOk()->assertSee('البحث المتقدم في المرئيات')
            ->assertSee('name="title"', false)->assertSee('name="author"', false)->assertSee('name="channel"', false)
            ->assertSee('name="from"', false)->assertSee('name="to"', false);
    }

    $legacy = $this->get('/advanced_search.htm?title=Lesson')->assertOk()->getContent();
    $pretty = $this->get('/video-advanced-search.htm?title=Lesson')->assertOk()->getContent();

    expect(aspItemIds($legacy))->toBe(aspItemIds($pretty))->toBe([3, 2, 1])
        ->and(aspSeriesIds($legacy))->toBe(aspSeriesIds($pretty))->toBe([2, 1]);
});

// ─── G1 / G2 / G6 — paging ───────────────────────────────────────────────────

it('G6: 25 results per page in each list, the remainder on page 2', function () {
    aspSeed(30, 30);

    $page1 = $this->get('/advanced_search.htm?title=Lesson')->assertOk()->getContent();
    expect(aspItemIds($page1))->toHaveCount(25)->and(aspSeriesIds($page1))->toHaveCount(25);

    $page2 = $this->get('/advanced_search.htm?title=Lesson&page=2&series_page=2')->assertOk()->getContent();
    expect(aspItemIds($page2))->toHaveCount(5)->and(aspSeriesIds($page2))->toHaveCount(5);
});

it('G1: every pager link keeps the search criteria, and page 2 still applies them', function () {
    aspSeed(30, 30);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 99, 'title' => 'Unrelated talk', 'author' => 2, 'vedio' => 1, 'hidden' => 0, 'weight' => 500]);

    $html = $this->get('/advanced_search.htm?title=Lesson&author=1&from=1970-01-01')->assertOk()->getContent();
    $links = aspPagerLinks($html);

    expect($links)->not->toBeEmpty();
    foreach ($links as $link) {
        expect($link)->toContain('title=Lesson')->toContain('author=1')->toContain('from=1970-01-01');
    }

    $page2 = $this->get('/advanced_search.htm?title=Lesson&author=1&from=1970-01-01&page=2')->assertOk()->getContent();
    expect(aspItemIds($page2))->toHaveCount(5)->not->toContain(99);
});

it('G2: the series and the items page independently — series_page moves only the series list', function () {
    aspSeed(30, 30);

    $html = $this->get('/advanced_search.htm?title=Lesson&series_page=2')->assertOk()->getContent();

    // Series page 2 (ids 5..1, newest first), items still on page 1 (weights 30..6).
    expect(aspSeriesIds($html))->toBe([5, 4, 3, 2, 1])
        ->and(aspItemIds($html))->toHaveCount(25)->and(aspItemIds($html)[0])->toBe(30);

    $links = aspPagerLinks($html);
    expect(collect($links)->filter(fn ($l) => str_contains($l, 'series_page='))->isNotEmpty())->toBeTrue()
        // an items-pager link keeps the series list where it is
        ->and(collect($links)->contains(fn ($l) => preg_match('/[?&]page=2/', $l) && str_contains($l, 'series_page=2')))->toBeTrue();
});

// ─── G5 — OLD ordering ───────────────────────────────────────────────────────

it('G5: items are ordered by weight DESC (OLD ListKhotab), not by time; series by id DESC', function () {
    // Weight and time disagree: id 1 is newest but lightest.
    aspSeed(3, 3, [
        1 => ['weight' => 1, 'time' => 9000],
        2 => ['weight' => 30, 'time' => 2000],
        3 => ['weight' => 20, 'time' => 3000],
    ]);

    $html = $this->get('/video-advanced-search.htm?title=Lesson')->assertOk()->getContent();

    expect(aspItemIds($html))->toBe([2, 3, 1])
        ->and(aspSeriesIds($html))->toBe([3, 2, 1]);
});

// ─── G7 — totals ─────────────────────────────────────────────────────────────

it('G7: the series total reads «سلسلة» and the items total «مادة»', function () {
    aspSeed(4, 3);

    $html = $this->get('/advanced_search.htm?title=Lesson')->assertOk()->getContent();

    expect($html)->toContain('3 سلسلة')->toContain('4 مادة')->not->toContain('3 مادة');
});

// ─── compact results (owner UI refinement) ───────────────────────────────────

it('renders each result compactly — highlighted title, then sheikh, channel and count / visits on one line', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 5, 'title' => 'Channel Five']);
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 1, 'title' => 'Lesson series', 'vedio' => 1, 'hidden' => 0, 'count' => 7, 'channel_id' => 5]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'Lesson talk', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'hits' => 42, 'channel_id' => 5]);

    $results = aspResults($this->get('/advanced_search.htm?title=Lesson')->assertOk()->getContent());
    $compact = preg_replace('/\s+/u', ' ', $results);

    expect(substr_count($results, 'class="w2a-result"'))->toBe(2)
        ->and($compact)->toContain('<h3 class="w2a-result__title"> <a href="/khotab-series-1.htm"><sub class="red_sub">Lesson</sub> series</a> </h3>')
        ->toContain('<h3 class="w2a-result__title"> <a href="/khotab-item-1.htm"><sub class="red_sub">Lesson</sub> talk</a> </h3>')
        ->toContain('الداعية: <a href="/khotab-video-1.htm">Dr. Shaikh</a>')
        ->toContain('القناة: <a href="/channel-5-1.htm">')
        ->toContain('<span class="w2a-result__badge"> <i class="fa fa-play-circle-o" aria-hidden="true"></i> المواد: 7 </span>')
        ->toContain('<span class="w2a-result__badge"> <i class="fa fa-eye" aria-hidden="true"></i> الزيارات: 42 </span>')
        // the old nested rows / page-header spacing is gone
        ->not->toContain('page-header')->not->toContain('<h5>');
});

// ─── filters unchanged ───────────────────────────────────────────────────────

it('keeps OLD\'s visitor filters: video only, no hidden items, no hidden or empty series', function () {
    aspSeed(1, 1);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 2, 'title' => 'Lesson hidden', 'author' => 1, 'vedio' => 1, 'hidden' => 1, 'weight' => 9],
        ['id' => 3, 'title' => 'Lesson audio', 'author' => 1, 'vedio' => 0, 'hidden' => 0, 'weight' => 9],
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 2, 'author_id' => 1, 'title' => 'Lesson hidden series', 'vedio' => 1, 'hidden' => 1, 'count' => 3],
        ['id' => 3, 'author_id' => 1, 'title' => 'Lesson empty series', 'vedio' => 1, 'hidden' => 0, 'count' => 0],
        ['id' => 4, 'author_id' => 1, 'title' => 'Lesson audio series', 'vedio' => 0, 'hidden' => 0, 'count' => 2],
    ]);

    $html = $this->get('/advanced_search.htm?title=Lesson')->assertOk()->getContent();

    expect(aspItemIds($html))->toBe([1])->and(aspSeriesIds($html))->toBe([1]);
});

it('keeps the title-less search (owner decision): a sheikh-only search lists that sheikh\'s videos', function () {
    aspSeed(2);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 9, 'title' => 'Other sheikh talk', 'author' => 2, 'vedio' => 1, 'hidden' => 0, 'weight' => 99]);

    $html = $this->get('/advanced_search.htm?author=1')->assertOk()->getContent();

    expect(aspItemIds($html))->toBe([2, 1])->and($html)->not->toContain('أربعة أحرف');
});

// ─── G4 — sheikh pages ───────────────────────────────────────────────────────

it('G4: video and audio sheikh pages show every visitor OLD\'s «بحث متقدم» form, scoped to the sheikh and his channels', function () {
    DB::connection('main')->table('nuke_sat_channels')->insert([
        ['id' => 5, 'title' => 'Channel Five'],
        ['id' => 6, 'title' => 'Channel Six'],
        ['id' => 7, 'title' => 'Other Sheikh Channel'],
    ]);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        ['id' => 1, 'author_id' => 1, 'group_id' => 0, 'title' => 'S1', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 5],
        ['id' => 2, 'author_id' => 1, 'group_id' => 0, 'title' => 'S2', 'vedio' => 0, 'hidden' => 0, 'count' => 1, 'channel_id' => 6],
        ['id' => 3, 'author_id' => 1, 'group_id' => 0, 'title' => 'S3', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 5],
        ['id' => 4, 'author_id' => 2, 'group_id' => 0, 'title' => 'S4', 'vedio' => 1, 'hidden' => 0, 'count' => 1, 'channel_id' => 7],
    ]);

    foreach (['video', 'audio'] as $op) {
        $html = $this->get("/khotab-{$op}-1.htm")->assertOk()->getContent();

        expect($html)->toContain('data-author-advanced-search')
            ->toContain('بحث متقدم في سلاسل و مواد الداعية')
            ->toContain('action="/advanced_search.htm"')
            ->toContain('<input type="hidden" name="author" value="1">')
            ->toContain('name="title"')->toContain('name="from"')->toContain('name="to"')
            ->toContain('<option value="5">Channel Five</option>')
            ->toContain('<option value="6">Channel Six</option>')
            ->not->toContain('Other Sheikh Channel');
        // Each channel once (OLD's DISTINCT).
        expect(substr_count($html, '<option value="5">'))->toBe(1);
    }
});

it('G4: the transcripts (pdf) page, which OLD Author() never had, gets no form', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'title' => 'PDF Item', 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 0]);

    $this->get('/khotab-pdf-1.htm')->assertOk()->assertDontSee('data-author-advanced-search', false);
});

it('G4: submitting the sheikh-page form searches only that sheikh', function () {
    aspSeed(2);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 9, 'title' => 'Lesson elsewhere', 'author' => 2, 'vedio' => 1, 'hidden' => 0, 'weight' => 99]);

    $html = $this->get('/advanced_search.htm?author=1&title=Lesson&channel=0&from=&to=')->assertOk()->getContent();

    expect(aspItemIds($html))->toBe([2, 1]);
});

// ─── search.htm unchanged ────────────────────────────────────────────────────

it('leaves POST search.htm as it was: 20 per page, its own pager', function () {
    aspSeed(25);

    $html = $this->post('/search.htm', ['kh_title' => 'Lesson', 'kh_dept' => 'video'])->assertOk()->getContent();

    // search.htm has its own view (no advanced-search results wrapper): count over the page.
    preg_match_all('#href="/khotab-item-(\d+)\.htm"#', $html, $m);
    expect(array_unique($m[1]))->toHaveCount(20)->and($html)->toContain('<strong>25</strong>')
        ->and($html)->not->toContain('series_page=');
});
